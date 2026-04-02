<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '歷史軌跡';
$pdo = db();
$tags   = $pdo->query("SELECT tag_id, name FROM tags WHERE status=1 ORDER BY name")->fetchAll();
$floors = $pdo->query("SELECT * FROM floors ORDER BY sort_order,id")->fetchAll();

$extraHead = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
.hist-layout { display:flex; flex-direction:column; height:calc(100vh - 80px); }
.hist-top    { flex:1; display:flex; gap:0; overflow:hidden; }
.hist-map    { flex:1; min-height:0; }
</style>';
include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content" style="padding:0;display:flex;flex-direction:column;height:100vh;overflow:hidden">

    <!-- ── 上方工具列 ─────────────────────────────────────── -->
    <div style="padding:16px 24px;border-bottom:1px solid var(--c-border);display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:rgba(4,9,20,0.9);flex-shrink:0">
        <h1 style="font-size:1.1rem;font-weight:600;color:var(--c-text);margin:0">
            <i class='bx bx-history' style="color:var(--c-primary)"></i> 歷史軌跡
        </h1>
        <select class="form-control" id="h-tag" style="width:180px">
            <option value="">— 選擇 TAG —</option>
            <?php foreach ($tags as $t): ?>
            <option value="<?= htmlspecialchars($t['tag_id']) ?>"><?= htmlspecialchars($t['name'] ?? $t['tag_id']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" class="form-control" id="h-date" style="width:160px"
               value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary" onclick="loadHistory()">
            <i class='bx bx-search'></i> 查詢
        </button>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <button class="btn btn-secondary" id="play-btn" onclick="togglePlay()" disabled>
                <i class='bx bx-play' id="play-icon"></i> 播放
            </button>
            <select class="form-control" id="play-speed" style="width:100px">
                <option value="500">0.5x</option>
                <option value="200" selected>1x</option>
                <option value="100">2x</option>
                <option value="50">4x</option>
            </select>
            <span class="text-sub text-sm" id="h-count">—</span>
        </div>
    </div>

    <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
        <!-- 地圖 -->
        <div style="flex:1;position:relative">
            <!-- 樓層切換 -->
            <div class="floor-tabs" id="hist-floor-tabs" style="position:absolute;top:10px;left:10px;z-index:500;background:rgba(4,9,20,0.85);border-radius:8px;border:1px solid var(--c-border);padding:6px">
                <?php foreach ($floors as $f): ?>
                <button class="floor-tab" data-fid="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <div id="hist-map" style="width:100%;height:100%"></div>
        </div>

        <!-- 時間軸 -->
        <div class="timeline-ctrl" id="timeline-ctrl">
            <button class="btn btn-secondary btn-sm" onclick="stepBack()" id="step-back" disabled>
                <i class='bx bx-skip-previous'></i>
            </button>
            <input type="range" id="timeline-slider" min="0" max="0" value="0"
                   oninput="seekTo(+this.value)" disabled>
            <div class="timeline-time" id="timeline-time">— : — : —</div>
            <button class="btn btn-secondary btn-sm" onclick="stepForward()" id="step-fwd" disabled>
                <i class='bx bx-skip-next'></i>
            </button>
        </div>
    </div>
</main>

</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const UPLOAD_URL = '<?= UPLOAD_URL ?>';

const histMap = L.map('hist-map', {
    crs: L.CRS.Simple, minZoom:-3, maxZoom:3,
    attributionControl:false, zoomControl:true
});

let histData    = [];
let currentIdx  = 0;
let playTimer   = null;
let isPlaying   = false;
let imgH = 800, imgW = 1000;
let imgLayer    = null;
let tagMarker   = null;
let pathLine    = null;
let currentFid  = null;

// 樓層切換
document.querySelectorAll('.floor-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.floor-tab').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        currentFid = btn.dataset.fid;
        loadFloorMap(btn.dataset.fid);
    });
});

async function loadFloorMap(fid) {
    const r = await fetch(`${BASE_URL}/api/floors.php`);
    const d = await r.json();
    const fl = (d.floors||[]).find(f=>f.id==fid);
    if (!fl) return;
    imgW = +fl.img_width || 1000;
    imgH = +fl.img_height || 800;
    if (imgLayer) imgLayer.remove();
    const bounds = [[0,0],[imgH,imgW]];
    if (fl.image_path) imgLayer = L.imageOverlay(`${UPLOAD_URL}${fl.image_path}`, bounds).addTo(histMap);
    histMap.fitBounds(bounds, {padding:[20,20]});
}

// 初始化第一個樓層
const firstTab = document.querySelector('.floor-tab');
if (firstTab) {
    firstTab.classList.add('active');
    currentFid = firstTab.dataset.fid;
    loadFloorMap(firstTab.dataset.fid);
}

// 查詢歷史
async function loadHistory() {
    const tagId = document.getElementById('h-tag').value;
    const date  = document.getElementById('h-date').value;
    if (!tagId) return alert('請選擇 TAG');
    if (!date)  return alert('請選擇日期');

    const from = `${date} 00:00:00`;
    const to   = `${date} 23:59:59`;
    const r    = await fetch(`${BASE_URL}/api/history.php?tag_id=${encodeURIComponent(tagId)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&limit=1000`);
    const d    = await r.json();

    histData   = d.history || [];
    currentIdx = 0;
    document.getElementById('h-count').textContent = `共 ${histData.length} 筆`;

    const slider = document.getElementById('timeline-slider');
    slider.max   = Math.max(0, histData.length - 1);
    slider.value = 0;
    slider.disabled = histData.length === 0;
    document.getElementById('play-btn').disabled = histData.length === 0;
    document.getElementById('step-back').disabled = histData.length === 0;
    document.getElementById('step-fwd').disabled  = histData.length === 0;

    // 清除舊標記和路徑
    if (tagMarker) { tagMarker.remove(); tagMarker = null; }
    if (pathLine)  { pathLine.remove();  pathLine  = null; }

    if (histData.length > 0) {
        seekTo(0);
        drawPath();
    }
}

function toLatLng(x, y) { return [imgH - y, x]; }

function drawPath() {
    if (pathLine) pathLine.remove();
    const pts = histData.map(p => toLatLng(+p.x, +p.y));
    pathLine = L.polyline(pts, {
        color: '#38bdf8', weight: 2,
        opacity: 0.5, dashArray: '5,5'
    }).addTo(histMap);
}

function seekTo(idx) {
    if (!histData[idx]) return;
    currentIdx = idx;
    const p = histData[idx];
    document.getElementById('timeline-slider').value = idx;
    document.getElementById('timeline-time').textContent =
        `${p.recorded_at}  (${idx+1}/${histData.length})`;

    const ll = toLatLng(+p.x, +p.y);
    const icon = L.divIcon({
        html:`<div style="width:16px;height:16px;border-radius:50%;background:#38bdf8;border:2px solid #fff;box-shadow:0 0 10px #38bdf8"></div>`,
        className:'', iconSize:[16,16], iconAnchor:[8,8]
    });
    if (tagMarker) tagMarker.setLatLng(ll);
    else tagMarker = L.marker(ll, {icon}).addTo(histMap);

    // 切換樓層
    if (p.floor_id != currentFid) {
        currentFid = p.floor_id;
        loadFloorMap(p.floor_id);
        document.querySelectorAll('.floor-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.fid == currentFid);
        });
    }
}

function togglePlay() {
    if (isPlaying) {
        clearInterval(playTimer);
        isPlaying = false;
        document.getElementById('play-icon').className = 'bx bx-play';
    } else {
        if (currentIdx >= histData.length - 1) currentIdx = 0;
        isPlaying = true;
        document.getElementById('play-icon').className = 'bx bx-pause';
        const speed = +document.getElementById('play-speed').value;
        playTimer = setInterval(() => {
            if (currentIdx >= histData.length - 1) {
                togglePlay(); return;
            }
            seekTo(currentIdx + 1);
        }, speed);
    }
}

function stepBack()    { if (currentIdx > 0) seekTo(currentIdx - 1); }
function stepForward() { if (currentIdx < histData.length - 1) seekTo(currentIdx + 1); }
</script>
</html>
