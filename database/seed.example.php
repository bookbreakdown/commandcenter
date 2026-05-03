<?php

require_once __DIR__ . '/../src/Database.php';
Database::init();

// Copy this file to seed.php and customize for your workspaces.
// seed.php is gitignored -- your project/workspace layout is PC-specific.

$projects = [
    'My Project' => [
        '/path/to/workspace-1',
        '/path/to/workspace-2',
    ],
    'Another Project' => [
        '/path/to/workspace-3',
    ],
];

foreach ($projects as $name => $workspaces) {
    try {
        $projectId = Database::createProject($name);
        echo "Created project: $name (id: $projectId)\n";
    } catch (PDOException $e) {
        echo "Project '$name' already exists, skipping.\n";
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE name = ?');
        $stmt->execute([$name]);
        $projectId = $stmt->fetchColumn();
    }

    foreach ($workspaces as $path) {
        try {
            $wsId = Database::createWorkspace($projectId, $path);
            echo "  Added workspace: $path (id: $wsId)\n";
        } catch (PDOException $e) {
            echo "  Workspace '$path' already exists, skipping.\n";
        }
    }
}

echo "\nDone. Seeded " . count($projects) . " projects.\n";
