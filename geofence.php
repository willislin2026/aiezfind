<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '電子圍欄';
$pdo = db();
$floors = $pdo->query("SELECT * FROM floors ORDER BY sort_order,id")->fetchAll();
$geofences = $pdo->query("
    SELECT g.*, f.name AS floor_name
    FROM geofences g LEFT JOIN floors f ON f.id=g.floor_id
    ORDER BY g.id
")->fetchAll();

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>body,html{overflow:hidden}</style>';
include __DIR__ . '/includes/nav.php';
?>
<div style="display:flex;height:100vh;overflow:hidden" id="main-content">

    <!-- ── 左側欄 ──────────────────────────────────────── -->
    <div class="map-sidebar" style="width:300px">
        <div class="map-sidebar-header">電子圍欄</div>

        <!-- 樓層選擇 -->
        <div style="padding:10px 12px;border-bottom:1px solid var(--c-border)">
            <select class="form-control" id="geo-floor" onchange="onFloorChange()">
                <option value="">— 選擇樓層 —</option>
                <?php foreach ($floors as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 繪製工具 -->
        <div class="geo-toolbar">
            <button class="btn btn-primary btn-sm" id="draw-btn" onclick="startDraw()" disabled>
                <i class='bx bx-vector'></i> 開始繪製
            </button>
            <button class="btn btn-secondary btn-sm" id="finish-btn" onclick="finishDraw()" disabled>
                <i class='bx bx-check'></i> 完成
            </button>
            <button class="btn btn-danger btn-sm" id="cancel-btn" onclick="cancelDraw()" disabled>
                <i class='bx bx-x'></i> 取消
            </button>
        </div>
        <div style="padding:4px 12px;font-size:0.75rem;color:var(--c-sub)">
            繪製時在地圖上點擊新增頂點，至少 3 點後可完成。
        </div>

        <!-- 圍欄清單 -->
        <div class="map-sidebar-body" id="geofence-list">
        <?php foreach ($geofences as $g): ?>
        <div class="tag-list-item" id="gf-<?= $g['id'] ?>">
            <span class="tag-dot" style="background:<?= htmlspecialchars($g['color']) ?>;color:<?= htmlspecialchars($g['color']) ?>"></span>
            <span class="tag-name text-sm"><?= htmlspecialchars($g['name']) ?></span>
            <span class="text-sub text-xs"><?= htmlspecialchars($g['floor_name'] ?? '') ?></span>
            <button class="btn btn-danger btn-sm btn-icon" style="margin-left:auto" onclick="delGeo(<?= $g['id'] ?>)">
                <i class='bx bx-trash'></i>
            </button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($geofences)): ?>
        <div class="empty-state"><i class='bx bx-shield-quarter'></i><p>尚無圍欄</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── 地圖區 ──────────────────────────────────────── -->
    <div style="flex:1;position:relative">
        <div id="geo-map" style="width:100%;height:100%"></div>
        <div style="position:absolute;top:10px;right:10px;z-index:500;font-size:0.77rem;color:var(--c-sub);background:rgba(4,9,20,0.8);padding:6px 12px;border-radius:8px">
            <span id="draw-hint"></span>
        </div>
    </div>
</div>

<!-- 命名 Modal -->
<div class="modal-overlay" id="name-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">命名圍欄</span>
            <button class="modal-close" onclick="document.getElementById('name-modal').classList.remove('open')"><i class='bx bx-x'></i></button>
        </div>
        <div class="form-group">
            <label class="form-label">圍欄名稱 *</label>
            <input class="form-control" id="geo-name" placeholder="例如：禁止進入區、機房">
        </div>
        <div class="form-group" style="margin-top:12px">
            <label class="form-label">顏色</label>
            <div class="color-preview">
                <input type="color" id="geo-color" value="#f59e0b">
                <span id="geo-color-hex" class="font-mono text-sm">#f59e0b</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('name-modal').classList.remove('open');cancelDraw()">取消</button>
            <button class="btn btn-primary" onclick="submitGeo()"><i class='bx bx-check'></i> 儲存</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const UPLOAD_URL = '<?= UPLOAD_URL ?>';

const geoMap = L.map('geo-map', {
    crs: L.CRS.Simple, minZoom:-3, maxZoom:3,
    attributionControl:false
});

let imgLayer   = null;
let imgH = 800, imgW = 1000;
let drawing    = false;
let drawPoints = [];
let drawMarkers= [];
let drawPoly   = null;
let geoLayers  = {};
let pendingPoints = null;

document.getElementById('geo-color').addEventListener('input', e => {
    document.getElementById('geo-color-hex').textContent = e.target.value;
});

async function onFloorChange() {
    const fid = document.getElementById('geo-floor').value;
    if (!fid) return;
    document.getElementById('draw-btn').disabled = false;

    const r = await fetch(`${BASE_URL}/api/floors.php`);
    const d = await r.json();
    const fl = (d.floors||[]).find(f=>f.id==fid);
    if (!fl) return;
    imgW = +fl.img_width || 1000;
    imgH = +fl.img_height || 800;

    if (imgLayer) imgLayer.remove();
    const bounds = [[0,0],[imgH,imgW]];
    if (fl.image_path) imgLayer = L.imageOverlay(`${UPLOAD_URL}${fl.image_path}`, bounds).addTo(geoMap);
    geoMap.fitBounds(bounds, {padding:[20,20]});

    // 載入此樓層的圍欄
    loadGeoFences(fid);
}

async function loadGeoFences(fid) {
    // 清除舊圍欄
    Object.values(geoLayers).forEach(l => l.remove());
    geoLayers = {};

    const r = await fetch(`${BASE_URL}/api/geofences.php?floor_id=${fid}`);
    const d = await r.json();
    (d.geofences||[]).forEach(g => {
        const pts = JSON.parse(g.points || '[]').map(p => [imgH - p[1], p[0]]);
        if (pts.length < 3) return;
        const poly = L.polygon(pts, {color: g.color, fillOpacity:0.15, weight:2}).addTo(geoMap);
        poly.bindTooltip(g.name, {permanent:true, direction:'center', className:'',
            opacity:0.8
        });
        geoLayers[g.id] = poly;
    });
}

function toLatLng(x, y) { return [imgH - y, x]; }

function startDraw() {
    if (!document.getElementById('geo-floor').value) return toast('請先選擇樓層', 'warning');
    drawing = true;
    drawPoints = [];
    drawMarkers= [];
    if (drawPoly) { drawPoly.remove(); drawPoly = null; }
    document.getElementById('draw-btn').disabled   = true;
    document.getElementById('finish-btn').disabled = false;
    document.getElementById('cancel-btn').disabled = false;
    document.getElementById('draw-hint').textContent = '點擊地圖新增頂點';
    geoMap.getContainer().style.cursor = 'crosshair';
}

geoMap.on('click', e => {
    if (!drawing) return;
    const x = Math.round(e.latlng.lng);
    const y = Math.round(imgH - e.latlng.lat);
    drawPoints.push([x, y]);
    const icon = L.divIcon({
        html:`<div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;border:1px solid #fff"></div>`,
        className:'', iconSize:[8,8], iconAnchor:[4,4]
    });
    drawMarkers.push(L.marker([e.latlng.lat, e.latlng.lng], {icon}).addTo(geoMap));
    if (drawPoints.length >= 2) {
        if (drawPoly) drawPoly.remove();
        drawPoly = L.polygon(
            drawPoints.map(p => toLatLng(p[0], p[1])),
            {color:'#f59e0b', fillOpacity:0.10, dashArray:'6,4', weight:2}
        ).addTo(geoMap);
    }
    document.getElementById('draw-hint').textContent = `已標記 ${drawPoints.length} 點`;
});

function finishDraw() {
    if (drawPoints.length < 3) return toast('至少需要 3 個頂點', 'warning');
    pendingPoints = [...drawPoints];
    document.getElementById('geo-name').value = '';
    document.getElementById('name-modal').classList.add('open');
}

function cancelDraw() {
    drawing = false;
    drawPoints = [];
    drawMarkers.forEach(m => m.remove());
    drawMarkers = [];
    if (drawPoly) { drawPoly.remove(); drawPoly = null; }
    document.getElementById('draw-btn').disabled   = false;
    document.getElementById('finish-btn').disabled = true;
    document.getElementById('cancel-btn').disabled = true;
    document.getElementById('draw-hint').textContent = '';
    geoMap.getContainer().style.cursor = '';
}

async function submitGeo() {
    const name    = document.getElementById('geo-name').value.trim();
    const color   = document.getElementById('geo-color').value;
    const floorId = document.getElementById('geo-floor').value;
    if (!name) return toast('請輸入圍欄名稱', 'warning');

    const r = await fetch(`${BASE_URL}/api/geofences.php`, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({name, floor_id:floorId, points:pendingPoints, color})
    });
    const d = await r.json();
    if (d.ok) {
        toast('圍欄已儲存', 'success');
        document.getElementById('name-modal').classList.remove('open');
        cancelDraw();
        loadGeoFences(floorId);
        location.reload();
    } else toast(d.error, 'error');
}

async function delGeo(id) {
    if (!confirm('確定刪除此圍欄？')) return;
    const r = await fetch(`${BASE_URL}/api/geofences.php?id=${id}`, {method:'DELETE'});
    const d = await r.json();
    if (d.ok) {
        if (geoLayers[id]) geoLayers[id].remove();
        document.getElementById('gf-'+id)?.remove();
        toast('已刪除', 'success');
    } else toast(d.error, 'error');
}

function toast(msg, type='success') {
    const icons = {success:'bx-check-circle',error:'bx-error',warning:'bx-error-circle'};
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class='bx ${icons[type]}'></i>${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
</html>
