<?php
/** API: TAG CRUD */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

try {
    if ($method === 'GET') {
        // 含最新位置資訊
        $tags = $pdo->query("
            SELECT t.*,
                   tp.floor_id, tp.x, tp.y, tp.bat, tp.last_rssi,
                   tp.confidence, tp.node_count, tp.updated_at,
                   f.name AS floor_name
            FROM tags t
            LEFT JOIN tag_positions tp ON tp.tag_id = t.tag_id
            LEFT JOIN floors f ON f.id = tp.floor_id
            ORDER BY t.created_at DESC
        ")->fetchAll();
        echo json_encode(['ok' => true, 'tags' => $tags]);
    }
    elseif ($method === 'POST') {
        $d = json_decode(file_get_contents('php://input'), true);
        $tagId = strtoupper(trim($d['tag_id'] ?? ''));
        $name  = trim($d['name'] ?? '') ?: $tagId;
        if (!$tagId) throw new Exception('tag_id 不可空白');

        $pdo->prepare(
            "INSERT INTO tags (tag_id, name, description, icon_color, status)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description),
                icon_color=VALUES(icon_color), status=VALUES(status)"
        )->execute([$tagId, $name, $d['description'] ?? '', $d['icon_color'] ?? '#38bdf8', 1]);
        echo json_encode(['ok' => true, 'tag_id' => $tagId]);
    }
    elseif ($method === 'PUT') {
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = (int)($d['id'] ?? 0);
        $pdo->prepare(
            "UPDATE tags SET name=?, description=?, icon_color=?, status=? WHERE id=?"
        )->execute([$d['name'], $d['description'] ?? '', $d['icon_color'], (int)$d['status'], $id]);
        echo json_encode(['ok' => true]);
    }
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $pdo->prepare("DELETE FROM tags WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
