<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = __DIR__ . '/../database/commandcenter.db';
            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA foreign_keys=ON');
        }
        return self::$pdo;
    }

    public static function init(): void
    {
        $pdo = self::connect();
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);
    }

    public static function getProjects(): array
    {
        $pdo = self::connect();
        return $pdo->query('SELECT * FROM projects ORDER BY name')->fetchAll();
    }

    public static function getWorkspaces(?int $projectId = null): array
    {
        $pdo = self::connect();
        if ($projectId) {
            $stmt = $pdo->prepare('SELECT * FROM workspaces WHERE project_id = ? ORDER BY path');
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM workspaces ORDER BY path')->fetchAll();
    }

    public static function getSessions(?int $workspaceId = null, ?string $status = null): array
    {
        $pdo = self::connect();
        $sql = 'SELECT * FROM sessions WHERE 1=1';
        $params = [];
        if ($workspaceId) {
            $sql .= ' AND workspace_id = ?';
            $params[] = $workspaceId;
        }
        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY last_active_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getDashboard(array $expandedWsIds = []): array
    {
        $pdo = self::connect();
        $projects = self::getProjects();
        foreach ($projects as &$project) {
            $project['workspaces'] = self::getWorkspaces($project['id']);
            foreach ($project['workspaces'] as &$ws) {
                $ws['sessions'] = self::getSessions($ws['id']);
                $ws['git'] = self::getGitInfo($ws['path']);
                $limit = in_array('ws-' . $ws['id'], $expandedWsIds) ? 999 : 5;
                $ws['discovered'] = self::discoverSessions($ws['path'], $ws['id'], $limit);
            }
            usort($project['workspaces'], function ($a, $b) {
                $aTime = $a['sessions'][0]['last_active_at'] ?? '0000';
                $bTime = $b['sessions'][0]['last_active_at'] ?? '0000';
                return strcmp($bTime, $aTime);
            });
        }
        return $projects;
    }

    public static function discoverSessions(string $workspacePath, int $workspaceId, int $limit = 5): array
    {
        $claudeDir = getenv('HOME') ?: '/home/' . get_current_user();
        $encoded = str_replace('/', '-', $workspacePath);
        $projectDir = $claudeDir . '/.claude/projects/' . $encoded;

        if (!is_dir($projectDir)) return [];

        $pdo = self::connect();
        $stmt = $pdo->prepare('SELECT guid FROM sessions WHERE workspace_id = ?');
        $stmt->execute([$workspaceId]);
        $registered = array_column($stmt->fetchAll(), 'guid');

        $files = glob($projectDir . '/*.jsonl');
        if (!$files) return [];

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $discovered = [];
        $total = 0;
        foreach ($files as $f) {
            $guid = basename($f, '.jsonl');
            if (in_array($guid, $registered)) continue;
            $total++;

            if (count($discovered) < $limit) {
                $mtime = filemtime($f);
                $discovered[] = [
                    'guid' => $guid,
                    'last_active_at' => date('Y-m-d H:i:s', $mtime),
                    'age_days' => (int) ((time() - $mtime) / 86400),
                    'size_kb' => (int) (filesize($f) / 1024),
                    'registered' => false,
                ];
            }
        }

        return ['sessions' => $discovered, 'total' => $total, 'showing' => count($discovered)];
    }

    public static function getGitInfo(string $path): array
    {
        $info = ['branch' => null, 'ahead' => 0, 'behind' => 0, 'exists' => false];
        if (!is_dir($path . '/.git') && !is_dir($path)) {
            return $info;
        }
        $info['exists'] = true;
        $branch = trim(shell_exec("git -C " . escapeshellarg($path) . " branch --show-current 2>/dev/null") ?? '');
        $info['branch'] = $branch ?: '(detached)';

        $revList = trim(shell_exec("git -C " . escapeshellarg($path) . " rev-list --left-right --count @{upstream}...HEAD 2>/dev/null") ?? '');
        if (preg_match('/^(\d+)\s+(\d+)$/', $revList, $m)) {
            $info['behind'] = (int) $m[1];
            $info['ahead'] = (int) $m[2];
        }
        return $info;
    }

    public static function createProject(string $name): int
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('INSERT INTO projects (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int) $pdo->lastInsertId();
    }

    public static function createWorkspace(int $projectId, string $path): int
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('INSERT INTO workspaces (project_id, path) VALUES (?, ?)');
        $stmt->execute([$projectId, $path]);
        return (int) $pdo->lastInsertId();
    }

    public static function createSession(int $workspaceId, string $guid, ?string $label = null, ?string $status = null, ?string $lastActiveAt = null): int
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('INSERT INTO sessions (workspace_id, guid, label, status, last_active_at, started_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $workspaceId,
            $guid,
            $label,
            $status ?? 'active',
            $lastActiveAt ?? date('Y-m-d H:i:s'),
            $lastActiveAt ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateSessionLabel(string $guid, string $label): void
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('UPDATE sessions SET label = ?, last_active_at = CURRENT_TIMESTAMP WHERE guid = ?');
        $stmt->execute([$label, $guid]);
    }

    public static function updateSessionStatus(string $guid, string $status): void
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('UPDATE sessions SET status = ?, last_active_at = CURRENT_TIMESTAMP WHERE guid = ?');
        $stmt->execute([$status, $guid]);
    }

    public static function touchSession(string $guid): void
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare('UPDATE sessions SET last_active_at = CURRENT_TIMESTAMP WHERE guid = ?');
        $stmt->execute([$guid]);
    }

    public static function deleteProject(int $id): void
    {
        $pdo = self::connect();
        $pdo->prepare('DELETE FROM sessions WHERE workspace_id IN (SELECT id FROM workspaces WHERE project_id = ?)')->execute([$id]);
        $pdo->prepare('DELETE FROM workspaces WHERE project_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    }

    public static function deleteWorkspace(int $id): void
    {
        $pdo = self::connect();
        $pdo->prepare('DELETE FROM sessions WHERE workspace_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM workspaces WHERE id = ?')->execute([$id]);
    }

    public static function deleteSession(string $guid): void
    {
        $pdo = self::connect();
        $pdo->prepare('DELETE FROM sessions WHERE guid = ?')->execute([$guid]);
    }
}
