<?php
/** API: NODE CRUD */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

try {
    if ($method === 'GET') {
        $nodes = $pdo->query("
            SELECT n.*, f.name AS floor_name
            FROM nodes n
            LEFT JOIN floors f ON f.id = n.floor_id
            ORDER BY n.node_id
        ")->fetchAll();
        echo json_encode(['ok' => true, 'nodes' => $nodes]);
    }
    elseif ($method === 'PUT') {
        $d      = json_decode(file_get_contents('php://input'), true);
        $nodeId = strtoupper(trim($d['node_id'] ?? ''));
        $name   = trim($d['name'] ?? '') ?: $nodeId;
        $floorId = isset($d['floor_id']) && $d['floor_id'] !== '' ? (int)$d['floor_id'] : null;
        $x  = isset($d['x']) && $d['x'] !== '' ? (float)$d['x'] : null;
        $y  = isset($d['y']) && $d['y'] !== '' ? (float)$d['y'] : null;

        $pdo->prepare(
            "INSERT INTO nodes (node_id, name, floor_id, x, y)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), floor_id=VALUES(floor_id),
                x=VALUES(x), y=VALUES(y)"
        )->execute([$nodeId, $name, $floorId, $x, $y]);
        echo json_encode(['ok' => true]);
    }
    elseif ($method === 'DELETE') {
        $nodeId = $_GET['node_id'] ?? '';
        $pdo->prepare("DELETE FROM nodes WHERE node_id=?")->execute([$nodeId]);
        echo json_encode(['ok' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
