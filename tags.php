<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'TAG 管理';
$pdo = db();
$tags = $pdo->query("
    SELECT t.*,
           tp.floor_id, tp.x, tp.y, tp.bat, tp.last_rssi, tp.confidence,
           tp.node_count, tp.updated_at AS last_seen,
           f.name AS floor_name,
           IF(tp.updated_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND), 1, 0) AS online
    FROM tags t
    LEFT JOIN tag_positions tp ON tp.tag_id = t.tag_id
    LEFT JOIN floors f ON f.id = tp.floor_id
    ORDER BY t.created_at DESC
")->fetchAll();

include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-purchase-tag' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>TAG 管理</h1>
        <button class="btn btn-primary" onclick="openModal()">
            <i class='bx bx-plus'></i> 新增 TAG
        </button>
    </div>

    <div class="table-wrap card" style="padding:0">
        <table class="table">
            <thead><tr>
                <th>TAG ID</th><th>名稱</th><th>位置</th><th>電量</th>
                <th>RSSI</th><th>状態</th><th>最後偵測</th><th>操作</th>
            </tr></thead>
            <tbody id="tag-tbody">
            <?php foreach ($tags as $t): ?>
            <tr id="tr-<?= $t['id'] ?>">
                <td>
                    <div class="flex items-center gap-8">
                        <span class="tag-dot" style="background:<?= htmlspecialchars($t['icon_color']) ?>;color:<?= htmlspecialchars($t['icon_color']) ?>"></span>
                        <span class="font-mono text-primary"><?= htmlspecialchars($t['tag_id']) ?></span>
                    </div>
                </td>
                <td><?= htmlspecialchars($t['name'] ?? '—') ?></td>
                <td class="text-sub text-sm">
                    <?= $t['floor_name']
                        ? htmlspecialchars($t['floor_name']) . ' ' . sprintf('(%.0f,%.0f)', $t['x'], $t['y'])
                        : '—' ?>
                </td>
                <td>
                    <?php $b = (int)$t['bat']; $bc = $b>=50?'high':($b>=20?'mid':'low'); ?>
                    <?php if ($b > 0): ?>
                    <div class="bat-bar" style="min-width:80px">
                        <div class="bat-track"><div class="bat-fill <?= $bc ?>" style="width:<?= $b ?>%"></div></div>
                        <span class="text-xs"><?= $b ?>%</span>
                    </div>
                    <?php else: ?><span class="text-sub text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="font-mono text-sm"><?= $t['last_rssi'] !== null ? $t['last_rssi'].' dBm' : '—' ?></td>
                <td>
                    <?php if (!$t['status']): ?>
                        <span class="badge badge-neutral">停用</span>
                    <?php elseif ($t['online']): ?>
                        <span class="badge badge-success">在線</span>
                    <?php else: ?>
                        <span class="badge badge-warning">離線</span>
                    <?php endif; ?>
                </td>
                <td class="text-sub text-sm"><?= $t['last_seen'] ?? '—' ?></td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-secondary btn-sm"
                            onclick='openModal(<?= json_encode([
                                "id"=>$t["id"],"tag_id"=>$t["tag_id"],"name"=>$t["name"],
                                "description"=>$t["description"],"icon_color"=>$t["icon_color"],
                                "status"=>$t["status"]
                            ]) ?>)'>
                            <i class='bx bx-edit'></i>
                        </button>
                        <button class="btn btn-danger btn-sm"
                            onclick='delTag(<?= $t["id"] ?>, "<?= htmlspecialchars($t["tag_id"]) ?>")'>
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tags)): ?>
            <tr><td colspan="8" class="empty-state">
                <i class='bx bx-purchase-tag'></i><p>尚無 TAG 資料</p>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ── Modal ──────────────────────────────────────────── -->
<div class="modal-overlay" id="tag-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">新增 TAG</span>
            <button class="modal-close" onclick="closeModal()"><i class='bx bx-x'></i></button>
        </div>
        <input type="hidden" id="t-id">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">TAG CardNo（卡號）*</label>
                <input class="form-control font-mono" id="t-tag-id" placeholder="例如：1200A3">
            </div>
            <div class="form-group">
                <label class="form-label">顯示名稱</label>
                <input class="form-control" id="t-name" placeholder="人員或物品名稱">
            </div>
            <div class="form-group">
                <label class="form-label">顯示顏色</label>
                <div class="color-preview">
                    <input type="color" id="t-color" value="#38bdf8">
                    <span id="t-color-hex" class="font-mono text-sm">#38bdf8</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">狀態</label>
                <select class="form-control" id="t-status">
                    <option value="1">啟用</option>
                    <option value="0">停用</option>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-top:12px">
            <label class="form-label">備註</label>
            <textarea class="form-control" id="t-desc" placeholder="說明用途或攜帶者"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">取消</button>
            <button class="btn btn-primary" onclick="submitTag()">
                <i class='bx bx-check'></i> 儲存
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let editId = null;

document.getElementById('t-color').addEventListener('input', e => {
    document.getElementById('t-color-hex').textContent = e.target.value;
});

function openModal(tag = null) {
    editId = tag?.id || null;
    document.getElementById('modal-title').textContent = tag ? '編輯 TAG' : '新增 TAG';
    document.getElementById('t-id').value       = tag?.id || '';
    document.getElementById('t-tag-id').value   = tag?.tag_id || '';
    document.getElementById('t-tag-id').disabled = !!tag;
    document.getElementById('t-name').value     = tag?.name || '';
    document.getElementById('t-color').value    = tag?.icon_color || '#38bdf8';
    document.getElementById('t-color-hex').textContent = tag?.icon_color || '#38bdf8';
    document.getElementById('t-status').value   = tag?.status ?? 1;
    document.getElementById('t-desc').value     = tag?.description || '';
    document.getElementById('tag-modal').classList.add('open');
}
function closeModal() { document.getElementById('tag-modal').classList.remove('open'); }

async function submitTag() {
    const tagId = document.getElementById('t-tag-id').value.trim().toUpperCase();
    const name  = document.getElementById('t-name').value.trim();
    if (!tagId) return toast('請輸入 TAG CardNo', 'warning');

    const body = {
        tag_id: tagId, name: name || tagId,
        description: document.getElementById('t-desc').value,
        icon_color: document.getElementById('t-color').value,
        status: +document.getElementById('t-status').value,
    };

    let r;
    if (editId) {
        body.id = editId;
        r = await fetch(`${BASE_URL}/api/tags.php`, {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
    } else {
        r = await fetch(`${BASE_URL}/api/tags.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
    }
    const d = await r.json();
    if (d.ok) { toast(editId ? '已更新' : '已新增', 'success'); closeModal(); location.reload(); }
    else toast(d.error, 'error');
}

async function delTag(id, tagId) {
    if (!confirm(`確定刪除 TAG ${tagId}？`)) return;
    const r = await fetch(`${BASE_URL}/api/tags.php?id=${id}`, {method:'DELETE'});
    const d = await r.json();
    if (d.ok) { document.getElementById('tr-'+id)?.remove(); toast('已刪除','success'); }
    else toast(d.error,'error');
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
