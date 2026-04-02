<?php
/** API: 圍欄 CRUD */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

try {
    if ($method === 'GET') {
        $floorId = isset($_GET['floor_id']) ? (int)$_GET['floor_id'] : null;
        if ($floorId) {
            $rows = $pdo->prepare("SELECT * FROM geofences WHERE floor_id=? ORDER BY id");
            $rows->execute([$floorId]);
        } else {
            $rows = $pdo->query("SELECT g.*, f.name AS floor_name FROM geofences g LEFT JOIN floors f ON f.id=g.floor_id ORDER BY g.id");
        }
        echo json_encode(['ok' => true, 'geofences' => $rows->fetchAll()]);
    }
    elseif ($method === 'POST') {
        $d = json_decode(file_get_contents('php://input'), true);
        $name    = trim($d['name'] ?? '');
        $floorId = (int)($d['floor_id'] ?? 0);
        $points  = $d['points'] ?? [];
        if (!$name || !$floorId || count($points) < 3) {
            throw new Exception('名稱、樓層及至少3個頂點為必填');
        }
        $pdo->prepare(
            "INSERT INTO geofences (name, floor_id, points, color, active) VALUES (?,?,?,?,1)"
        )->execute([$name, $floorId, json_encode($points), $d['color'] ?? '#f59e0b']);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }
    elseif ($method === 'PUT') {
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = (int)($d['id'] ?? 0);
        $pdo->prepare("UPDATE geofences SET name=?, color=?, active=? WHERE id=?")
            ->execute([$d['name'], $d['color'], (int)$d['active'], $id]);
        echo json_encode(['ok' => true]);
    }
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $pdo->prepare("DELETE FROM geofences WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
