<?php

namespace BookBreakdown\CommandCenter;

class Config
{
    public static function getDatabasePath(): string
    {
        if ($path = getenv('CC_DATABASE_PATH')) {
            return $path;
        }

        $dir = getcwd();
        while ($dir !== dirname($dir)) {
            $config = $dir . '/.commandcenter.php';
            if (file_exists($config)) {
                $cfg = require $config;
                if (isset($cfg['database_path'])) {
                    return $cfg['database_path'];
                }
            }
            $dir = dirname($dir);
        }

        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/tmp');
        $defaultDir = $home . '/.commandcenter';
        if (!is_dir($defaultDir)) {
            mkdir($defaultDir, 0755, true);
        }
        return $defaultDir . '/commandcenter.db';
    }

    public static function getSchemaPath(): string
    {
        return dirname(__DIR__) . '/database/schema.sql';
    }

    public static function getPackageRoot(): string
    {
        return dirname(__DIR__);
    }

    public static function getBinPath(): string
    {
        if ($path = getenv('CC_BIN_PATH')) {
            return $path;
        }

        foreach ([
            dirname(__DIR__, 4) . '/vendor/bin',
            dirname(__DIR__) . '/bin',
        ] as $candidate) {
            if (file_exists($candidate . '/cc-register')) {
                return $candidate;
            }
        }

        return dirname(__DIR__) . '/bin';
    }

    public static function getClaudeProjectsDir(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/tmp');
        return $home . '/.claude/projects';
    }
}
