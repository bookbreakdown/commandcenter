<?php

require_once __DIR__ . '/../src/Database.php';
Database::init();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ("$method:$action") {
        case 'GET:dashboard':
            $expand = isset($_GET['expand']) ? explode(',', $_GET['expand']) : [];
            echo json_encode(Database::getDashboard($expand));
            break;

        case 'GET:projects':
            echo json_encode(Database::getProjects());
            break;

        case 'POST:project':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = Database::createProject($data['name']);
            echo json_encode(['id' => $id, 'name' => $data['name']]);
            break;

        case 'POST:workspace':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = Database::createWorkspace($data['project_id'], $data['path']);
            echo json_encode(['id' => $id]);
            break;

        case 'POST:session':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = Database::createSession($data['workspace_id'], $data['guid'], $data['label'] ?? null);
            echo json_encode(['id' => $id]);
            break;

        case 'PUT:session-label':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::updateSessionLabel($data['guid'], $data['label']);
            echo json_encode(['ok' => true]);
            break;

        case 'PUT:session-status':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::updateSessionStatus($data['guid'], $data['status']);
            echo json_encode(['ok' => true]);
            break;

        case 'PUT:session-touch':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::touchSession($data['guid']);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE:project':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::deleteProject($data['id']);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE:workspace':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::deleteWorkspace($data['id']);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE:session':
            $data = json_decode(file_get_contents('php://input'), true);
            Database::deleteSession($data['guid']);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
