<?php
/** API: 樓層列表 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

try {
    if ($method === 'GET') {
        $floors = $pdo->query(
            "SELECT *, CONCAT('" . UPLOAD_URL . "', image_path) AS image_url
             FROM floors ORDER BY sort_order, id"
        )->fetchAll();
        echo json_encode(['ok' => true, 'floors' => $floors]);
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $name = trim($data['name'] ?? '');
        if (!$name) throw new Exception('樓層名稱不可空白');
        $order = (int)($data['sort_order'] ?? 0);
        $w = (int)($data['img_width'] ?? 1000);
        $h = (int)($data['img_height'] ?? 800);

        $pdo->prepare("INSERT INTO floors (name, img_width, img_height, sort_order) VALUES (?,?,?,?)")
            ->execute([$name, $w, $h, $order]);
        $id = $pdo->lastInsertId();
        echo json_encode(['ok' => true, 'id' => $id]);
    }
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($data['id'] ?? 0);
        $pdo->prepare("UPDATE floors SET name=?, img_width=?, img_height=?, sort_order=? WHERE id=?")
            ->execute([$data['name'], $data['img_width'], $data['img_height'], $data['sort_order'], $id]);
        echo json_encode(['ok' => true]);
    }
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $pdo->prepare("SELECT image_path FROM floors WHERE id=?");
        $row->execute([$id]);
        $img = $row->fetchColumn();
        if ($img && file_exists(UPLOAD_DIR . $img)) unlink(UPLOAD_DIR . $img);
        $pdo->prepare("DELETE FROM floors WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
