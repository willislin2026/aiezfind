<?php
/** API: 告警紀錄 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

try {
    if ($method === 'GET') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $tagId = trim($_GET['tag_id'] ?? '');
        $type  = trim($_GET['type'] ?? '');

        $where = '1=1';
        $params = [];
        if ($tagId) { $where .= ' AND a.tag_id=?'; $params[] = $tagId; }
        if ($type)  { $where .= ' AND a.alert_type=?'; $params[] = $type; }

        $total = $pdo->prepare("SELECT COUNT(*) FROM alerts a WHERE $where");
        $total->execute($params);
        $total = (int)$total->fetchColumn();

        $rows = $pdo->prepare("
            SELECT a.*, COALESCE(t.name, a.tag_id) AS tag_name,
                   g.name AS geofence_name
            FROM alerts a
            LEFT JOIN tags t ON t.tag_id = a.tag_id
            LEFT JOIN geofences g ON g.id = a.geofence_id
            WHERE $where
            ORDER BY a.triggered_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $rows->execute($params);

        echo json_encode([
            'ok'     => true,
            'total'  => $total,
            'page'   => $page,
            'pages'  => (int)ceil($total / $limit),
            'alerts' => $rows->fetchAll(),
        ]);
    }
    elseif ($method === 'POST') {
        // 標記已讀
        $d = json_decode(file_get_contents('php://input'), true);
        if (isset($d['id'])) {
            $pdo->prepare("UPDATE alerts SET is_read=1 WHERE id=?")->execute([$d['id']]);
        } else {
            $pdo->exec("UPDATE alerts SET is_read=1");
        }
        echo json_encode(['ok' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
