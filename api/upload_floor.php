<?php
/** API: 樓層圖片上傳 */
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('METHOD_NOT_ALLOWED');

    $floorId = (int)($_POST['floor_id'] ?? 0);
    if (!$floorId) throw new Exception('缺少 floor_id');

    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception('上傳失敗');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/png','image/jpeg','image/gif','image/webp'])) {
        throw new Exception('只接受 PNG/JPG/GIF/WEBP 格式');
    }

    // 建立上傳目錄
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

    // 刪除舊圖
    $old = db()->prepare("SELECT image_path FROM floors WHERE id=?");
    $old->execute([$floorId]);
    $oldPath = $old->fetchColumn();
    if ($oldPath && file_exists(UPLOAD_DIR . $oldPath)) unlink(UPLOAD_DIR . $oldPath);

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
    $filename = "floor_{$floorId}_" . time() . ".{$ext}";
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('儲存失敗');

    // 自動取得圖片尺寸
    [$w, $h] = getimagesize($dest);

    db()->prepare("UPDATE floors SET image_path=?, img_width=?, img_height=? WHERE id=?")
       ->execute([$filename, $w, $h, $floorId]);

    echo json_encode([
        'ok'  => true,
        'url' => UPLOAD_URL . $filename,
        'w'   => $w, 'h' => $h,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
