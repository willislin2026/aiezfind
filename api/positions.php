<?php
/** API: 取得即時 TAG 與 NODE 位置 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$floorId = isset($_GET['floor_id']) ? (int)$_GET['floor_id'] : null;
$pdo = db();

try {
    $offlineSec = (int)getSetting('offline_sec', 60);

    // ── TAG 即時位置 ──────────────────────────────────────
    if ($floorId) {
        $tagSql = "
            SELECT tp.tag_id, tp.floor_id, tp.x, tp.y, tp.confidence,
                   tp.node_count, tp.bat, tp.last_rssi, tp.updated_at,
                   COALESCE(t.name, tp.tag_id) AS name,
                   t.icon_color,
                   IF(tp.updated_at >= DATE_SUB(NOW(), INTERVAL :sec SECOND), 1, 0) AS online
            FROM tag_positions tp
            LEFT JOIN tags t ON t.tag_id = tp.tag_id AND t.status = 1
            WHERE tp.floor_id = :fid
              AND (t.status IS NULL OR t.status = 1)
        ";
        $tagStmt = $pdo->prepare($tagSql);
        $tagStmt->execute([':fid' => $floorId, ':sec' => $offlineSec]);
    } else {
        $tagSql = "
            SELECT tp.tag_id, tp.floor_id, tp.x, tp.y, tp.confidence,
                   tp.node_count, tp.bat, tp.last_rssi, tp.updated_at,
                   COALESCE(t.name, tp.tag_id) AS name,
                   t.icon_color,
                   IF(tp.updated_at >= DATE_SUB(NOW(), INTERVAL :sec SECOND), 1, 0) AS online
            FROM tag_positions tp
            LEFT JOIN tags t ON t.tag_id = tp.tag_id
            WHERE t.status = 1 OR t.status IS NULL
        ";
        $tagStmt = $pdo->prepare($tagSql);
        $tagStmt->execute([':sec' => $offlineSec]);
    }
    $tags = $tagStmt->fetchAll();

    // ── NODE 狀態 ──────────────────────────────────────────
    $nodeSql = "SELECT node_id, name, floor_id, x, y, bat, status, last_seen
                FROM nodes WHERE floor_id = :fid";
    $nodeStmt = $pdo->prepare($nodeSql);
    $nodeStmt->execute([':fid' => $floorId ?? 0]);
    $nodes = $nodeStmt->fetchAll();

    echo json_encode([
        'ok'    => true,
        'ts'    => date('Y-m-d H:i:s'),
        'tags'  => $tags,
        'nodes' => $nodes,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
