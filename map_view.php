<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '即時地圖';
$pdo = db();

// 取得所有樓層
$floors = $pdo->query("SELECT * FROM floors ORDER BY sort_order, id")->fetchAll();
$defaultFloor = $floors[0] ?? null;

// 取得所有 TAG（側欄清單用）
$allTags = $pdo->query("
    SELECT t.tag_id, t.name, t.icon_color, tp.floor_id, tp.bat, tp.last_rssi,
           IF(tp.updated_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND), 1, 0) AS online
    FROM tags t
    LEFT JOIN tag_positions tp ON tp.tag_id = t.tag_id
    WHERE t.status = 1
    ORDER BY online DESC, t.name
")->fetchAll();

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
body { overflow:hidden; }
.layout-wrapper { overflow:hidden; }
</style>';
include __DIR__ . '/includes/nav.php';
?>
<div class="map-layout content" style="padding:0;flex:1;display:flex;height:calc(100vh);">

    <!-- ── 地圖側欄 ──────────────────────────────────────── -->
    <div class="map-sidebar">
        <!-- 樓層切換 -->
        <div class="map-sidebar-header">
            樓層選擇
            <span class="badge badge-primary" id="online-count">—</span>
        </div>
        <div class="floor-tabs" id="floor-tabs">
            <?php foreach ($floors as $i => $f): ?>
            <button class="floor-tab <?= $i === 0 ? 'active' : '' ?>"
                    data-fid="<?= $f['id'] ?>"
                    data-img="<?= $f['image_path'] ? UPLOAD_URL . $f['image_path'] : '' ?>"
                    data-w="<?= $f['img_width'] ?>"
                    data-h="<?= $f['img_height'] ?>">
                <?= htmlspecialchars($f['name']) ?>
            </button>
            <?php endforeach; ?>
            <?php if (empty($floors)): ?>
                <span class="text-sub text-sm" style="padding:8px">請先新增樓層</span>
            <?php endif; ?>
        </div>

        <!-- TAG 清單 -->
        <div class="map-sidebar-header" style="border-top:1px solid var(--c-border)">TAG 清單</div>
        <div class="map-sidebar-body" id="tag-list">
            <?php foreach ($allTags as $t): ?>
            <div class="tag-list-item" data-tag="<?= htmlspecialchars($t['tag_id']) ?>"
                 id="tli-<?= htmlspecialchars($t['tag_id']) ?>">
                <span class="tag-dot"
                      style="background:<?= htmlspecialchars($t['icon_color'] ?? '#38bdf8') ?>;
                             color:<?= htmlspecialchars($t['icon_color'] ?? '#38bdf8') ?>"></span>
                <span class="tag-name"><?= htmlspecialchars($t['name'] ?? $t['tag_id']) ?></span>
                <span class="tag-rssi <?= $t['online'] ? 'text-success' : 'text-sub' ?>">
                    <?= $t['online'] ? '●' : '○' ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($allTags)): ?>
                <div class="empty-state"><i class='bx bx-purchase-tag'></i><p>尚無 TAG</p></div>
            <?php endif; ?>
        </div>

        <!-- NODE 清單 -->
        <div class="map-sidebar-header" style="border-top:1px solid var(--c-border)">
            NODE 狀態
            <span id="node-count" class="text-sub text-xs">—</span>
        </div>
        <div class="map-sidebar-body" id="node-list" style="max-height:180px;flex:none">
        </div>
    </div>

    <!-- ── 地圖區域 ──────────────────────────────────────── -->
    <div class="map-container">
        <div id="leaflet-map"></div>
        <!-- 圖例 -->
        <div style="position:absolute;bottom:20px;right:20px;z-index:500;
                    background:rgba(4,9,20,0.88);border:1px solid var(--c-border);
                    border-radius:10px;padding:12px 16px;font-size:0.78rem;backdrop-filter:blur(8px);">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="width:10px;height:10px;border-radius:50%;background:#38bdf8;display:inline-block;box-shadow:0 0 6px #38bdf8"></span>
                TAG（在線）
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="width:10px;height:10px;border-radius:50%;background:#64748b;display:inline-block"></span>
                TAG（離線）
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:10px;height:10px;border-radius:50%;background:#818cf8;display:inline-block;box-shadow:0 0 6px #818cf8"></span>
                NODE
            </div>
        </div>
        <!-- 更新時間 -->
        <div style="position:absolute;top:12px;right:12px;z-index:500;
                    font-size:0.72rem;color:var(--c-sub);background:rgba(4,9,20,0.7);
                    padding:4px 10px;border-radius:20px;backdrop-filter:blur(4px)">
            <span id="map-update-time">--:--:--</span>
        </div>
    </div>
</div>

</div><!-- .layout-wrapper -->

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const UPLOAD_URL = '<?= UPLOAD_URL ?>';

// ── Leaflet 地圖初始化 ─────────────────────────────────
const map = L.map('leaflet-map', {
    crs: L.CRS.Simple,
    minZoom: -3, maxZoom: 3,
    zoomControl: true,
    attributionControl: false,
});

let currentFloor  = null;
let imageOverlay  = null;
let imgWidth = 1000, imgHeight = 800;
let tagMarkers  = {};
let nodeMarkers = {};
let refreshTimer = null;

// ── 樓層切換 ─────────────────────────────────────────
document.querySelectorAll('.floor-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.floor-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadFloor(btn.dataset.fid, btn.dataset.img, +btn.dataset.w, +btn.dataset.h);
    });
});

function loadFloor(fid, imgUrl, w, h) {
    currentFloor = fid;
    imgWidth = w || 1000;
    imgHeight = h || 800;

    if (imageOverlay) { imageOverlay.remove(); imageOverlay = null; }
    clearMarkers();

    const bounds = [[0, 0], [imgHeight, imgWidth]];

    if (imgUrl) {
        imageOverlay = L.imageOverlay(imgUrl, bounds).addTo(map);
    }
    map.fitBounds(bounds, {padding:[20,20]});
    refreshPositions();
}

function clearMarkers() {
    Object.values(tagMarkers).forEach(m => m.remove());
    Object.values(nodeMarkers).forEach(m => m.remove());
    tagMarkers = {};
    nodeMarkers = {};
}

// ── 座標轉換（stored x/y → Leaflet lat/lng）──────────
// 儲存座標：x從左, y從上（像素）
// Leaflet CRS.Simple：lat=行(從下往上), lng=列(從左往右)
// 所以：lat = imgHeight - y, lng = x
function toLatLng(x, y) {
    return [imgHeight - y, x];
}

// ── 建立 TAG 標記 ─────────────────────────────────────
function createTagMarker(tag) {
    const color  = tag.icon_color || '#38bdf8';
    const online = +tag.online;
    const ll     = toLatLng(+tag.x, +tag.y);

    const icon = L.divIcon({
        html: `<div class="tag-marker-pin" style="background:${online ? color : '#475569'};border-color:${online ? 'rgba(255,255,255,0.5)' : '#334155'}"></div>`,
        className: '',
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });

    const marker = L.marker(ll, {icon}).addTo(map);
    const name = tag.name || tag.tag_id;
    const bat  = tag.bat || 0;
    const rssi = tag.last_rssi || '—';
    const conf = Math.round((+tag.confidence || 0) * 100);

    marker.bindPopup(`
        <div style="min-width:160px">
            <div style="font-weight:600;margin-bottom:6px;color:${color}">${name}</div>
            <div>🔋 電量：<b>${bat}%</b></div>
            <div>📶 RSSI：${rssi} dBm</div>
            <div>🎯 信心度：${conf}%</div>
            <div>⏱ ${tag.updated_at || ''}</div>
        </div>
    `);

    // 標籤
    const label = L.divIcon({
        html: `<span style="font-size:11px;color:#e2e8f0;text-shadow:0 0 3px #000;white-space:nowrap;pointer-events:none">${name}</span>`,
        className: '',
        iconAnchor: [-8, 6],
    });
    L.marker(ll, {icon: label, interactive: false}).addTo(map);

    return marker;
}

// ── 建立 NODE 標記 ────────────────────────────────────
function createNodeMarker(node) {
    const ll = toLatLng(+node.x, +node.y);
    const icon = L.divIcon({
        html: `<div class="node-marker-pin" title="${node.name || node.node_id}"></div>`,
        className: '',
        iconSize: [12, 12],
        iconAnchor: [6, 6],
    });
    const marker = L.marker(ll, {icon}).addTo(map);
    marker.bindPopup(`
        <div>
            <div style="font-weight:600;color:#818cf8;margin-bottom:4px">NODE ${node.name || node.node_id}</div>
            <div>🔋 電量：${node.bat || 0}%</div>
            <div>📍 (${(+node.x).toFixed(0)}, ${(+node.y).toFixed(0)})</div>
            <div>⏱ ${node.last_seen || '從未'}</div>
        </div>
    `);
    return marker;
}

// ── 拉取並更新位置 ────────────────────────────────────
async function refreshPositions() {
    if (!currentFloor) return;
    try {
        const r = await fetch(`${BASE_URL}/api/positions.php?floor_id=${currentFloor}`);
        const d = await r.json();
        if (!d.ok) return;

        // 更新時間戳
        document.getElementById('map-update-time').textContent =
            new Date().toLocaleTimeString('zh-TW');

        // ── TAG 標記 ──────────────────────────────────
        const seenTags = new Set();
        let onlineCnt = 0;

        (d.tags || []).forEach(tag => {
            if (tag.x === null || tag.y === null) return;
            seenTags.add(tag.tag_id);
            if (+tag.online) onlineCnt++;

            if (tagMarkers[tag.tag_id]) {
                tagMarkers[tag.tag_id].remove();
            }
            tagMarkers[tag.tag_id] = createTagMarker(tag);

            // 更新側欄狀態
            const li = document.getElementById('tli-' + tag.tag_id);
            if (li) {
                const dot = li.querySelector('.tag-rssi');
                if (dot) {
                    dot.textContent = +tag.online ? '●' : '○';
                    dot.className = 'tag-rssi ' + (+tag.online ? 'text-success' : 'text-sub');
                }
            }
        });

        // 移除已消失的 TAG 標記
        Object.keys(tagMarkers).forEach(tid => {
            if (!seenTags.has(tid)) { tagMarkers[tid].remove(); delete tagMarkers[tid]; }
        });

        document.getElementById('online-count').textContent = `${onlineCnt} 在線`;

        // ── NODE 標記 ─────────────────────────────────
        const seenNodes = new Set();
        let nodeSidebar = '';
        (d.nodes || []).forEach(node => {
            if (node.x === null || node.y === null) return;
            seenNodes.add(node.node_id);
            if (nodeMarkers[node.node_id]) {
                nodeMarkers[node.node_id].remove();
            }
            nodeMarkers[node.node_id] = createNodeMarker(node);

            const stat = node.status ? 'text-success' : 'text-sub';
            nodeSidebar += `
                <div class="tag-list-item">
                    <span class="tag-dot" style="background:#818cf8;color:#818cf8"></span>
                    <span class="tag-name text-sm">${node.name || node.node_id}</span>
                    <span class="${stat} text-xs">${node.status ? '●' : '○'}</span>
                </div>`;
        });
        Object.keys(nodeMarkers).forEach(nid => {
            if (!seenNodes.has(nid)) { nodeMarkers[nid].remove(); delete nodeMarkers[nid]; }
        });
        document.getElementById('node-count').textContent = seenNodes.size + ' 個';
        document.getElementById('node-list').innerHTML = nodeSidebar || '<div class="empty-state"><p>無NODE資料</p></div>';

    } catch(e) {
        console.error(e);
    }
}

// ── TAG 側欄點擊 → 地圖定位 ─────────────────────────
document.getElementById('tag-list').addEventListener('click', e => {
    const item = e.target.closest('.tag-list-item');
    if (!item) return;
    const tid = item.dataset.tag;
    const marker = tagMarkers[tid];
    if (marker) {
        map.panTo(marker.getLatLng());
        marker.openPopup();
    }
});

// ── 啟動 ─────────────────────────────────────────────
const firstTab = document.querySelector('.floor-tab');
if (firstTab) {
    loadFloor(firstTab.dataset.fid, firstTab.dataset.img,
              +firstTab.dataset.w, +firstTab.dataset.h);
}

// 每 2 秒自動刷新
refreshTimer = setInterval(refreshPositions, 2000);
</script>
</html>
