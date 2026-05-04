<?php

namespace App\Services;

use App\Models\Account;

/**
 * Discovers Claude Code config directories on disk and upserts an Account row
 * for each. The convention used here:
 *
 *   $HOME/.claude          -> account label "personal"  (is_default = true)
 *   $HOME/.claude-savvior  -> account label "savvior"
 *   $HOME/.claude-foo      -> account label "foo"
 *
 * The HOME root is configurable: explicit COMMANDCENTER_HOME_ROOT (.env) wins,
 * else native HOME, else USERPROFILE (Windows). This matters because Apache
 * on Windows often runs as a service that does not see user-level shell vars,
 * so .env is the only reliable place to put a user-profile path.
 */
class ClaudeAccountDiscovery
{
    public function homeRoot(): ?string
    {
        // Read raw env at call time -- Laravel's env() caches at boot, so a
        // putenv() done in tests would be invisible to it. Order:
        //   COMMANDCENTER_HOME_ROOT (.env or shell)  -> explicit override
        //   HOME (POSIX)
        //   USERPROFILE (Windows)
        $explicit = $this->rawEnv('COMMANDCENTER_HOME_ROOT');
        if ($explicit !== null) return $explicit;

        $home = $this->rawEnv('HOME');
        if ($home) return $home;

        return $this->rawEnv('USERPROFILE');
    }

    private function rawEnv(string $key): ?string
    {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        $val = $_ENV[$key] ?? null;
        if (is_string($val) && $val !== '') return $val;
        $val = $_SERVER[$key] ?? null;
        if (is_string($val) && $val !== '') return $val;
        return null;
    }

    /**
     * @return array<int, array{label:string, claude_dir:string, is_default:bool}>
     */
    public function scan(): array
    {
        $home = $this->homeRoot();
        if (!$home || !is_dir($home)) {
            return [];
        }

        $accounts = [];
        // Scandir is cross-platform; glob with hidden-file patterns is finicky.
        foreach (scandir($home) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!str_starts_with($entry, '.claude')) continue;

            $full = $home . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($full)) continue;

            // Only treat dirs that actually have a projects/ subdir as accounts.
            // (~/.claude could exist as a file or empty stub on a fresh box.)
            if (!is_dir($full . DIRECTORY_SEPARATOR . 'projects')) continue;

            $isDefault = ($entry === '.claude');
            $label = $isDefault ? 'personal' : substr($entry, strlen('.claude-'));
            if ($label === '' || $label === false) {
                $label = 'personal';
            }

            $accounts[] = [
                'label'      => $label,
                'claude_dir' => $full,
                'is_default' => $isDefault,
            ];
        }

        return $accounts;
    }

    /**
     * Idempotent: insert any newly-found account, update label/default flags
     * for ones we already track. Returns the resulting Account collection.
     */
    public function syncToDatabase(): \Illuminate\Support\Collection
    {
        foreach ($this->scan() as $row) {
            Account::updateOrCreate(
                ['claude_dir' => $row['claude_dir']],
                ['label' => $row['label'], 'is_default' => $row['is_default']]
            );
        }
        return Account::orderBy('is_default', 'desc')->orderBy('label')->get();
    }
}
