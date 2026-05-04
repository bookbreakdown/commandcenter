<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Session;
use App\Models\Workspace;
use App\Services\ClaudeAccountDiscovery;
use App\Services\ClaudeSessionDiscovery;
use App\Services\WorkspacePathEncoder;
use Illuminate\Console\Command;

/**
 * Replacement for the old bin/cc-register: agents call this to label and
 * mark-active a session from inside the workspace they're working on.
 *
 *   php artisan cc:register --label "TMO | sync rewrite"
 *
 * Auto-detects:
 *   - workspace from getcwd() (must already exist as a Workspace row)
 *   - guid from the most recent <guid>.jsonl in any account's projects dir
 *   - account from which projects dir held that jsonl
 *
 * Sets registered=true so the dashboard can distinguish "agent self-registered
 * and gave me a label" from "this is just a stale jsonl on disk."
 */
class RegisterCommand extends Command
{
    protected $signature = 'cc:register
                            {--workspace= : Override the auto-detected workspace path}
                            {--guid= : Override the auto-detected session GUID}
                            {--account= : Pin the session to a specific account label}
                            {--label= : Human-readable description}
                            {--status= : active|paused|done}';
    protected $description = 'Register or relabel a Claude Code session in commandcenter.';

    public function handle(
        WorkspacePathEncoder $encoder,
        ClaudeAccountDiscovery $accountDiscovery,
        ClaudeSessionDiscovery $sessionDiscovery,
    ): int {
        // Refresh accounts so a freshly-installed dev box still works.
        $accountDiscovery->syncToDatabase();

        $workspacePath = $this->option('workspace') ?: getcwd();
        $workspace = Workspace::where('path', $workspacePath)->first();
        if (!$workspace) {
            $this->error("Workspace not registered: {$workspacePath}");
            $this->line('Run cc:workspace:add <project> <path> first.');
            return self::FAILURE;
        }

        [$guid, $account] = $this->resolveGuidAndAccount(
            $encoder,
            $workspace->path,
            $this->option('guid'),
            $this->option('account'),
        );

        if (!$guid) {
            $this->error('Could not auto-detect a GUID. Pass --guid explicitly.');
            return self::FAILURE;
        }

        $session = Session::firstOrNew([
            'guid'       => $guid,
            'account_id' => $account?->id,
        ]);
        $session->workspace_id = $workspace->id;
        if ($this->option('label') !== null)  $session->label = $this->option('label');
        if ($this->option('status') !== null) $session->status = $this->option('status');
        $session->registered = true;
        $session->save();

        // Run discovery once so jsonl_path/size/mtime get filled in.
        $sessionDiscovery->syncToDatabase();

        $this->info(sprintf(
            'Registered guid=%s workspace=%s account=%s label=%s',
            $guid,
            $workspace->path,
            $account?->label ?? '(unresolved)',
            $session->label ?? '(none)',
        ));
        return self::SUCCESS;
    }

    /**
     * @return array{0:?string,1:?Account}
     */
    private function resolveGuidAndAccount(
        WorkspacePathEncoder $encoder,
        string $workspacePath,
        ?string $explicitGuid,
        ?string $explicitAccountLabel,
    ): array {
        $accounts = Account::query()
            ->when($explicitAccountLabel, fn ($q) => $q->where('label', $explicitAccountLabel))
            ->orderBy('is_default', 'desc')
            ->get();

        $encoded = $encoder->encode($workspacePath);
        $bestFile = null;
        $bestMtime = -1;
        $bestAccount = null;
        $resolvedGuid = null;

        foreach ($accounts as $account) {
            $dir = $account->claude_dir . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $encoded;
            if (!is_dir($dir)) continue;

            if ($explicitGuid) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $explicitGuid . '.jsonl';
                if (is_file($candidate)) {
                    return [$explicitGuid, $account];
                }
                continue;
            }

            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.jsonl') ?: [] as $f) {
                $mt = filemtime($f) ?: 0;
                if ($mt > $bestMtime) {
                    $bestMtime = $mt;
                    $bestFile = $f;
                    $bestAccount = $account;
                }
            }
        }

        if ($explicitGuid) {
            // Caller passed a GUID we didn't find on disk; honor it but flag.
            return [$explicitGuid, $accounts->first()];
        }

        if ($bestFile) {
            $resolvedGuid = pathinfo($bestFile, PATHINFO_FILENAME);
        }
        return [$resolvedGuid, $bestAccount];
    }
}
