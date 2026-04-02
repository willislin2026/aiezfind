<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'NODE 管理';
$pdo = db();
$floors = $pdo->query("SELECT id, name FROM floors ORDER BY sort_order, id")->fetchAll();
$nodes  = $pdo->query("
    SELECT n.*, f.name AS floor_name,
           fi.image_path, fi.img_width, fi.img_height
    FROM nodes n
    LEFT JOIN floors f ON f.id = n.floor_id
    LEFT JOIN floors fi ON fi.id = n.floor_id
    ORDER BY n.node_id
")->fetchAll();

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';

include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-broadcast' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>NODE 管理</h1>
        <div class="actions">
            <span class="text-sub text-sm">點選「設定座標」後，在地圖上點擊設定 NODE 位置</span>
        </div>
    </div>

    <div class="table-wrap card" style="padding:0">
        <table class="table">
            <thead><tr>
                <th>NODE ID</th><th>名稱</th><th>樓層</th><th>座標</th>
                <th>電量</th><th>狀態</th><th>最後心跳</th><th>操作</th>
            </tr></thead>
            <tbody id="node-tbody">
            <?php foreach ($nodes as $n): ?>
            <?php $hasCoord = $n['x'] !== null && $n['y'] !== null; ?>
            <tr id="nr-<?= htmlspecialchars($n['node_id']) ?>">
                <td><span class="font-mono text-primary"><?= htmlspecialchars($n['node_id']) ?></span></td>
                <td><?= htmlspecialchars($n['name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($n['floor_name'] ?? '未設定') ?></td>
                <td class="font-mono text-sm">
                    <?= $hasCoord
                        ? sprintf('(%.0f, %.0f)', $n['x'], $n['y'])
                        : '<span class="badge badge-danger">未設定</span>' ?>
                </td>
                <td>
                    <?php $b = (int)$n['bat']; $bc = $b>=50?'high':($b>=20?'mid':'low'); ?>
                    <div class="bat-bar" style="min-width:80px">
                        <div class="bat-track"><div class="bat-fill <?= $bc ?>" style="width:<?= $b ?>%"></div></div>
                        <span class="text-xs"><?= $b ?>%</span>
                    </div>
                </td>
                <td>
                    <?= $n['status']
                        ? '<span class="badge badge-success">在線</span>'
                        : '<span class="badge badge-neutral">離線</span>' ?>
                </td>
                <td class="text-sub text-sm"><?= $n['last_seen'] ?? '—' ?></td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-secondary btn-sm"
                            onclick='openCoordModal(<?= json_encode([
                                "node_id"=>$n["node_id"],"name"=>$n["name"],
                                "floor_id"=>$n["floor_id"],"x"=>$n["x"],"y"=>$n["y"]
                            ]) ?>)'>
                            <i class='bx bx-map-pin'></i> 設定座標
                        </button>
                        <button class="btn btn-danger btn-sm"
                            onclick='delNode("<?= htmlspecialchars($n["node_id"]) ?>")'>
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($nodes)): ?>
            <tr><td colspan="8" class="empty-state">
                <i class='bx bx-broadcast'></i><p>尚無 NODE 資料，待 DAEMON 接收後自動新增</p>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ── 設定座標 Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="coord-modal">
    <div class="modal" style="width:min(720px,96vw)">
        <div class="modal-header">
            <span class="modal-title">設定 NODE 座標 — <span id="coord-node-id"></span></span>
            <button class="modal-close" onclick="closeModal('coord-modal')"><i class='bx bx-x'></i></button>
        </div>

        <div class="form-grid" style="margin-bottom:14px">
            <div class="form-group">
                <label class="form-label">顯示名稱</label>
                <input class="form-control" id="c-name" placeholder="NODE 名稱">
            </div>
            <div class="form-group">
                <label class="form-label">樓層 *</label>
                <select class="form-control" id="c-floor" onchange="changeCoordFloor()">
                    <option value="">— 選擇樓層 —</option>
                    <?php foreach ($floors as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">X 座標（像素）</label>
                <input class="form-control font-mono" id="c-x" type="number" placeholder="由地圖點擊自動填入">
            </div>
            <div class="form-group">
                <label class="form-label">Y 座標（像素）</label>
                <input class="form-control font-mono" id="c-y" type="number" placeholder="由地圖點擊自動填入">
            </div>
        </div>

        <div style="margin-bottom:10px;font-size:0.8rem;color:var(--c-primary)">
            <i class='bx bx-info-circle'></i> 在下方地圖點擊即可設定座標
        </div>
        <div class="coord-map-wrap" id="coord-map-wrap">
            <div id="coord-map" style="width:100%;height:100%"></div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('coord-modal')">取消</button>
            <button class="btn btn-primary" onclick="submitCoord()">
                <i class='bx bx-check'></i> 儲存
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const UPLOAD_URL = '<?= UPLOAD_URL ?>';
const FLOORS     = <?= json_encode($floors) ?>;

let coordMap = null, coordMarker = null, coordNodeId = null;
let coordX = null, coordY = null, coordImgH = 800, coordImgW = 1000;

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openCoordModal(node) {
    coordNodeId = node.node_id;
    document.getElementById('coord-node-id').textContent = node.node_id;
    document.getElementById('c-name').value    = node.name || '';
    document.getElementById('c-floor').value   = node.floor_id || '';
    document.getElementById('c-x').value       = node.x !== null ? node.x : '';
    document.getElementById('c-y').value       = node.y !== null ? node.y : '';
    coordX = node.x; coordY = node.y;

    document.getElementById('coord-modal').classList.add('open');

    setTimeout(() => {
        if (!coordMap) {
            coordMap = L.map('coord-map', {
                crs: L.CRS.Simple, minZoom:-2, maxZoom:2, attributionControl:false
            });
            coordMap.on('click', e => {
                const lng = e.latlng.lng, lat = e.latlng.lat;
                coordX = Math.round(lng);
                coordY = Math.round(coordImgH - lat);
                document.getElementById('c-x').value = coordX;
                document.getElementById('c-y').value = coordY;
                placeCoordMarker(lat, lng);
            });
        }
        coordMap.invalidateSize();
        changeCoordFloor();
    }, 120);
}

function changeCoordFloor() {
    const fid = document.getElementById('c-floor').value;
    if (!fid || !coordMap) return;

    // 找到選擇的樓層圖片
    fetch(`${BASE_URL}/api/floors.php`)
        .then(r => r.json())
        .then(d => {
            const fl = (d.floors || []).find(f => f.id == fid);
            if (!fl) return;
            coordImgW = +fl.img_width || 1000;
            coordImgH = +fl.img_height || 800;
            const bounds = [[0,0],[coordImgH, coordImgW]];
            coordMap.eachLayer(l => coordMap.removeLayer(l));
            if (fl.image_path) {
                L.imageOverlay(`${UPLOAD_URL}${fl.image_path}`, bounds).addTo(coordMap);
            }
            coordMap.fitBounds(bounds, {padding:[10,10]});

            // 恢復既有標記
            const cx = +document.getElementById('c-x').value;
            const cy = +document.getElementById('c-y').value;
            if (!isNaN(cx) && !isNaN(cy)) {
                placeCoordMarker(coordImgH - cy, cx);
            }
        });
}

function placeCoordMarker(lat, lng) {
    if (coordMarker) coordMarker.remove();
    const icon = L.divIcon({
        html: `<div style="width:14px;height:14px;border-radius:50%;background:#38bdf8;border:2px solid #fff;box-shadow:0 0 8px #38bdf8"></div>`,
        className:'', iconSize:[14,14], iconAnchor:[7,7]
    });
    coordMarker = L.marker([lat, lng], {icon, draggable: true}).addTo(coordMap);
    coordMarker.on('dragend', e => {
        const {lat, lng} = e.target.getLatLng();
        coordX = Math.round(lng);
        coordY = Math.round(coordImgH - lat);
        document.getElementById('c-x').value = coordX;
        document.getElementById('c-y').value = coordY;
    });
}

async function submitCoord() {
    const payload = {
        node_id:  coordNodeId,
        name:     document.getElementById('c-name').value.trim() || coordNodeId,
        floor_id: document.getElementById('c-floor').value || null,
        x:        document.getElementById('c-x').value !== '' ? +document.getElementById('c-x').value : null,
        y:        document.getElementById('c-y').value !== '' ? +document.getElementById('c-y').value : null,
    };
    const r = await fetch(`${BASE_URL}/api/nodes.php`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const d = await r.json();
    if (d.ok) { toast('已儲存', 'success'); closeModal('coord-modal'); location.reload(); }
    else toast(d.error, 'error');
}

async function delNode(nodeId) {
    if (!confirm(`確定刪除 NODE ${nodeId}？`)) return;
    const r = await fetch(`${BASE_URL}/api/nodes.php?node_id=${encodeURIComponent(nodeId)}`, {method:'DELETE'});
    const d = await r.json();
    if (d.ok) { document.getElementById('nr-'+nodeId)?.remove(); toast('已刪除','success'); }
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
