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

        return $session->save();
    }
}
