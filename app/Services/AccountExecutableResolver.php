<?php

namespace App\Services;

/**
 * Resolves the shell executable used to run Claude for a given account
 * label. Each PC may alias differently -- on this box "savvior" is invoked
 * as `claude-savvior`, while elsewhere it could be `savvi-claude` or just
 * `claude` with a runtime flag.
 *
 * Configured via the .env value CLAUDE_EXECUTABLES, formatted as
 * comma-separated label:executable pairs. Reads raw env (not Laravel's
 * cached env()) for the same Apache-on-Windows reasons documented on
 * ClaudeAccountDiscovery.
 */
class AccountExecutableResolver
{
    private const DEFAULT_EXECUTABLE = 'claude';

    private ?array $map = null;

    public function executableFor(string $accountLabel): string
    {
        $map = $this->parseMap();
        return $map[$accountLabel] ?? self::DEFAULT_EXECUTABLE;
    }

    /**
     * @return array<string,string>
     */
    private function parseMap(): array
    {
        if ($this->map !== null) return $this->map;

        $raw = $this->rawEnv('CLAUDE_EXECUTABLES');
        if ($raw === null) {
            return $this->map = [];
        }

        $out = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, ':')) continue;
            [$label, $exec] = explode(':', $pair, 2);
            $label = trim($label);
            $exec = trim($exec);
            if ($label !== '' && $exec !== '') {
                $out[$label] = $exec;
            }
        }
        return $this->map = $out;
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
}
