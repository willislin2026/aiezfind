<?php
/** API: 歷史軌跡查詢 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

try {
    $tagId   = trim($_GET['tag_id'] ?? '');
    $dateFrom = $_GET['from'] ?? date('Y-m-d') . ' 00:00:00';
    $dateTo   = $_GET['to']   ?? date('Y-m-d') . ' 23:59:59';
    $limit    = min(2000, (int)($_GET['limit'] ?? 500));

    if (!$tagId) throw new Exception('缺少 tag_id');

    $rows = db()->prepare("
        SELECT ph.*, f.name AS floor_name
        FROM position_history ph
        LEFT JOIN floors f ON f.id = ph.floor_id
        WHERE ph.tag_id = ?
          AND ph.recorded_at BETWEEN ? AND ?
        ORDER BY ph.recorded_at ASC
        LIMIT ?
    ");
    $rows->execute([$tagId, $dateFrom, $dateTo, $limit]);

    echo json_encode(['ok' => true, 'history' => $rows->fetchAll()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
