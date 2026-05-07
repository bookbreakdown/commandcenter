<?php

/**
 * Builds the frontend bundle for commandcenter. Invoked by composer's
 * post-install-cmd and post-create-project-cmd hooks so co-workers do not
 * need a separate `npm install && npm run build` step after pulling.
 *
 * Behavior:
 *   - If npm is not on PATH, prints a NOTE and exits 0 (composer install
 *     should not fail just because someone is doing API-only work without
 *     a node toolchain).
 *   - Otherwise runs `npm install --no-audit --no-fund` then `npm run build`.
 *     Either failure halts with the underlying exit code so CI surfaces it.
 *
 * Cross-platform (Windows + POSIX) via PHP_OS_FAMILY checks.
 */

$isWin = PHP_OS_FAMILY === 'Windows';
$probe = $isWin ? 'where npm' : 'command -v npm';
$null = $isWin ? 'NUL' : '/dev/null';

passthru("$probe > $null 2>&1", $code);
if ($code !== 0) {
    fwrite(STDERR, "\nNOTE: npm not found on PATH. Skipping frontend build.\n");
    fwrite(STDERR, "      Install Node.js (>=18) then run 'npm install && npm run build' from the project root.\n\n");
    exit(0);
}

echo "\n> npm install\n";
passthru('npm install --no-audit --no-fund', $installCode);
if ($installCode !== 0) {
    fwrite(STDERR, "\nnpm install failed (exit $installCode); aborting frontend build.\n");
    exit($installCode);
}

echo "\n> npm run build\n";
passthru('npm run build', $buildCode);
exit($buildCode);
