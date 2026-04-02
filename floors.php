<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '樓層管理';
$pdo = db();
$floors = $pdo->query("SELECT * FROM floors ORDER BY sort_order, id")->fetchAll();

include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-layer' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>樓層管理</h1>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class='bx bx-plus'></i> 新增樓層
        </button>
    </div>

    <div class="table-wrap card" style="padding:0">
        <table class="table">
            <thead><tr>
                <th>排序</th><th>名稱</th><th>圖片</th><th>尺寸</th><th>操作</th>
            </tr></thead>
            <tbody id="floor-tbody">
            <?php foreach ($floors as $f): ?>
            <tr id="fr-<?= $f['id'] ?>">
                <td class="text-sub"><?= $f['sort_order'] ?></td>
                <td><b><?= htmlspecialchars($f['name']) ?></b></td>
                <td>
                    <?php if ($f['image_path']): ?>
                        <img src="<?= UPLOAD_URL . $f['image_path'] ?>"
                             style="height:40px;border-radius:6px;border:1px solid var(--c-border)">
                    <?php else: ?>
                        <span class="badge badge-neutral">未上傳</span>
                    <?php endif; ?>
                </td>
                <td class="text-sub text-sm"><?= $f['img_width'] ?> × <?= $f['img_height'] ?>px</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-secondary btn-sm" onclick='openUpload(<?= $f["id"] ?>,"<?= htmlspecialchars($f["name"]) ?>")'>
                            <i class='bx bx-upload'></i> 上傳圖片
                        </button>
                        <button class="btn btn-danger btn-sm" onclick='delFloor(<?= $f["id"] ?>, "<?= htmlspecialchars($f["name"]) ?>")'>
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($floors)): ?>
            <tr><td colspan="5" class="empty-state"><i class='bx bx-layer'></i><p>尚未新增樓層</p></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ── 新增樓層 Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="add-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">新增樓層</span>
            <button class="modal-close" onclick="closeModal('add-modal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">樓層名稱 *</label>
                <input class="form-control" id="f-name" placeholder="例如：1F、B1、屋頂">
            </div>
            <div class="form-group">
                <label class="form-label">排序（數字越小越前）</label>
                <input class="form-control" id="f-order" type="number" value="0">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('add-modal')">取消</button>
            <button class="btn btn-primary" onclick="submitAdd()">
                <i class='bx bx-check'></i> 新增
            </button>
        </div>
    </div>
</div>

<!-- ── 上傳圖片 Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="upload-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">上傳平面圖 — <span id="upload-floor-name"></span></span>
            <button class="modal-close" onclick="closeModal('upload-modal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="form-group">
            <label class="form-label">選擇 PNG / JPG 圖片（建議比例 4:3 或 16:9）</label>
            <input type="file" class="form-control" id="upload-file" accept="image/*">
        </div>
        <div id="upload-preview" style="margin-top:10px;display:none">
            <img id="preview-img" style="max-height:200px;border-radius:8px;border:1px solid var(--c-border)">
            <div class="text-sub text-sm" id="preview-size" style="margin-top:6px"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('upload-modal')">取消</button>
            <button class="btn btn-primary" id="upload-btn" onclick="submitUpload()">
                <i class='bx bx-upload'></i> 上傳
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let uploadFloorId = null;

function openAddModal() { document.getElementById('add-modal').classList.add('open'); }
function openUpload(id, name) {
    uploadFloorId = id;
    document.getElementById('upload-floor-name').textContent = name;
    document.getElementById('upload-preview').style.display = 'none';
    document.getElementById('upload-file').value = '';
    document.getElementById('upload-modal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// 預覽
document.getElementById('upload-file').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const img = document.getElementById('preview-img');
    img.src = url;
    img.onload = () => {
        document.getElementById('preview-size').textContent =
            `尺寸：${img.naturalWidth} × ${img.naturalHeight} px`;
    };
    document.getElementById('upload-preview').style.display = 'block';
});

async function submitAdd() {
    const name = document.getElementById('f-name').value.trim();
    const order = document.getElementById('f-order').value;
    if (!name) return toast('請輸入樓層名稱', 'warning');
    const r = await fetch(`${BASE_URL}/api/floors.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({name, sort_order: +order})
    });
    const d = await r.json();
    if (d.ok) { toast('新增成功', 'success'); location.reload(); }
    else toast(d.error, 'error');
}

async function submitUpload() {
    const file = document.getElementById('upload-file').files[0];
    if (!file) return toast('請選擇圖片', 'warning');
    const fd = new FormData();
    fd.append('floor_id', uploadFloorId);
    fd.append('image', file);
    document.getElementById('upload-btn').disabled = true;
    const r = await fetch(`${BASE_URL}/api/upload_floor.php`, {method:'POST', body:fd});
    const d = await r.json();
    document.getElementById('upload-btn').disabled = false;
    if (d.ok) { toast('上傳成功', 'success'); location.reload(); }
    else toast(d.error, 'error');
}

async function delFloor(id, name) {
    if (!confirm(`確定刪除樓層「${name}」？這將清除所在 NODE 的樓層設定。`)) return;
    const r = await fetch(`${BASE_URL}/api/floors.php?id=${id}`, {method:'DELETE'});
    const d = await r.json();
    if (d.ok) { document.getElementById('fr-'+id)?.remove(); toast('已刪除', 'success'); }
    else toast(d.error, 'error');
}

function toast(msg, type='success') {
    const icons = {success:'bx-check-circle',error:'bx-error',warning:'bx-error-circle'};
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class='bx ${icons[type]||"bx-info-circle"}'></i>${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
</html>
