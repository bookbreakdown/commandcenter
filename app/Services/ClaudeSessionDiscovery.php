<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Session;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Walks each Account's projects/<encoded>/<guid>.jsonl tree and upserts a
 * claude_sessions row for every file found. Session rows survive across
 * scans -- subsequent runs only update jsonl_mtime, jsonl_size_bytes, and
 * (when the session was registered manually) leave label/status alone.
 */
class ClaudeSessionDiscovery
{
    public function __construct(
        private readonly WorkspacePathEncoder $encoder,
    ) {}

    /**
     * Scan every Account in the database. Returns the count of sessions
     * touched (inserted or updated).
     */
    public function syncToDatabase(): int
    {
        $touched = 0;
        $workspaces = Workspace::all();
        $workspacesByEncoded = $workspaces->keyBy(fn ($w) => $this->encoder->encode($w->path));

        foreach (Account::all() as $account) {
            $projectsDir = $account->claude_dir . DIRECTORY_SEPARATOR . 'projects';
            if (!is_dir($projectsDir)) continue;

            foreach (scandir($projectsDir) ?: [] as $encodedWorkspaceDir) {
                if ($encodedWorkspaceDir === '.' || $encodedWorkspaceDir === '..') continue;
                $wsDir = $projectsDir . DIRECTORY_SEPARATOR . $encodedWorkspaceDir;
                if (!is_dir($wsDir)) continue;

                // Match this on-disk encoded dir back to one of our registered
                // Workspace rows. Sessions whose workspace isn't registered are
                // still recorded (workspace_id null) so we can show "orphan"
                // sessions in the dashboard later.
                $workspace = $workspacesByEncoded->get($encodedWorkspaceDir);

                foreach (glob($wsDir . DIRECTORY_SEPARATOR . '*.jsonl') ?: [] as $jsonl) {
                    $touched += $this->upsertSession($account, $workspace, $jsonl) ? 1 : 0;
                }
            }
        }

        return $touched;
    }

    private function upsertSession(Account $account, ?Workspace $workspace, string $jsonlPath): bool
    {
        $guid = pathinfo($jsonlPath, PATHINFO_FILENAME);
        if ($guid === '') return false;

        $mtime = filemtime($jsonlPath) ?: time();
        $size = filesize($jsonlPath) ?: 0;

        $session = Session::firstOrNew([
            'guid'       => $guid,
            'account_id' => $account->id,
        ]);

        // Discovery never overwrites human-set fields; only the file metadata
        // and the workspace association (in case a workspace was added after
        // a previous scan).
        $session->jsonl_path = $jsonlPath;
        $session->jsonl_size_bytes = $size;
        $session->jsonl_mtime = Carbon::createFromTimestamp($mtime);

        if ($workspace) {
            $session->workspace_id = $workspace->id;
        }

        if (!$session->exists) {
            $session->status = 'active';
            $session->registered = false;
        }

        // Cache the first user prompt the first time we see a file. Re-extract
        // when the file grows or the column is empty (cheap recovery if a row
        // predates this feature).
        $shouldExtract = empty($session->first_user_prompt)
            || $session->jsonl_size_bytes !== $size;
        if ($shouldExtract) {
            $session->first_user_prompt = $this->extractFirstUserPrompt($jsonlPath);
        }

        return $session->save();
    }

    /**
     * Read the JSONL line-by-line until the first message that is a plain
     * human prompt (type=user, role=user, content is a string -- this skips
     * system-injected tool-result messages which use an array). Truncates to
     * 280 chars so the column stays tweet-sized for the dashboard preview.
     */
    private function extractFirstUserPrompt(string $jsonlPath): ?string
    {
        $fh = @fopen($jsonlPath, 'r');
        if (!$fh) return null;

        try {
            $linesScanned = 0;
            while (($line = fgets($fh)) !== false) {
                if (++$linesScanned > 200) break; // first prompt is always near the top
                $line = trim($line);
                if ($line === '' || $line[0] !== '{') continue;

                $obj = json_decode($line, true);
                if (!is_array($obj)) continue;
                if (($obj['type'] ?? null) !== 'user') continue;

                $content = $obj['message']['content'] ?? null;
                if (!is_string($content) || $content === '') continue;

                $clean = trim(preg_replace('/\s+/', ' ', $content) ?? '');
                if ($clean === '') continue;

                return mb_substr($clean, 0, 280);
            }
            return null;
        } finally {
            fclose($fh);
        }
    }
}
