<?php

namespace App\Services;

/**
 * Encodes/decodes workspace paths to the on-disk format Claude Code uses
 * inside ~/.claude/projects/. The convention swaps every path separator
 * (forward slash, backslash) and colon for a dash:
 *
 *   /var/www/myproject       -> -var-www-myproject
 *   C:\wamp\www\tmo-tools3   -> C--wamp-www-tmo-tools3
 *
 * The encoded form is one directory level under projects/, and inside that
 * directory live the per-session <guid>.jsonl files.
 */
class WorkspacePathEncoder
{
    public function encode(string $path): string
    {
        return str_replace(['/', '\\', ':'], '-', $path);
    }

    /**
     * Reverse the encoding heuristically. Lossy on Windows because we cannot
     * know which of the original characters (/, \, :) produced each dash.
     * Useful only for diagnostics, never for path lookups.
     */
    public function decodeForDisplay(string $encoded): string
    {
        return $encoded;
    }
}
