<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '告警紀錄';
include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-bell' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>告警紀錄</h1>
        <div class="actions">
            <button class="btn btn-secondary" onclick="markAllRead()">
                <i class='bx bx-check-double'></i> 全部標為已讀
            </button>
        </div>
    </div>

    <!-- 篩選 -->
    <div class="card mb-24" style="padding:16px 20px">
        <div class="flex items-center gap-12 flex-wrap">
            <select class="form-control" id="f-tag" style="width:200px">
                <option value="">全部 TAG</option>
            </select>
            <select class="form-control" id="f-type" style="width:160px">
                <option value="">全部類型</option>
                <option value="enter">進入圍欄</option>
                <option value="exit">離開圍欄</option>
                <option value="offline">離線</option>
                <option value="low_bat">低電量</option>
            </select>
            <button class="btn btn-primary" onclick="loadAlerts(1)">
                <i class='bx bx-search'></i> 篩選
            </button>
            <span class="text-sub text-sm" id="total-count"></span>
        </div>
    </div>

    <div class="table-wrap card" style="padding:0">
        <table class="table">
            <thead><tr>
                <th>時間</th><th>TAG</th><th>類型</th><th>圍欄</th><th>訊息</th><th>狀態</th>
            </tr></thead>
            <tbody id="alert-tbody">
                <tr><td colspan="6" style="text-align:center;padding:30px">
                    <div class="loading-ring"></div>
                </td></tr>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="pagination"></div>
</main>

<div class="toast-container" id="toast-container"></div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let currentPage = 1;

const typeMap = {
    enter:   ['badge-warning',   '進入圍欄'],
    exit:    ['badge-secondary', '離開圍欄'],
    offline: ['badge-danger',    '離線'],
    low_bat: ['badge-danger',    '低電量'],
};

// 載入 TAG 清單
async function loadTags() {
    const r = await fetch(`${BASE_URL}/api/tags.php`);
    const d = await r.json();
    const sel = document.getElementById('f-tag');
    (d.tags||[]).forEach(t => {
        const o = document.createElement('option');
        o.value = t.tag_id;
        o.textContent = t.name || t.tag_id;
        sel.appendChild(o);
    });
}

async function loadAlerts(page = 1) {
    currentPage = page;
    const tagId = document.getElementById('f-tag').value;
    const type  = document.getElementById('f-type').value;
    let url = `${BASE_URL}/api/alerts.php?page=${page}`;
    if (tagId) url += `&tag_id=${encodeURIComponent(tagId)}`;
    if (type)  url += `&type=${encodeURIComponent(type)}`;

    const r = await fetch(url);
    const d = await r.json();
    document.getElementById('total-count').textContent = `共 ${d.total} 筆`;

    const tbody = document.getElementById('alert-tbody');
    if (!d.alerts?.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty-state">
            <i class='bx bx-check-shield'></i><p>無告警紀錄</p></td></tr>`;
    } else {
        tbody.innerHTML = d.alerts.map(a => {
            const [cls, lbl] = typeMap[a.alert_type] || ['badge-neutral', a.alert_type];
            return `<tr class="${a.is_read ? '' : 'unread'}" style="${a.is_read ? '' : 'background:rgba(56,189,248,0.04)'}">
                <td class="font-mono text-sm">${a.triggered_at}</td>
                <td><b>${escHtml(a.tag_name||a.tag_id)}</b></td>
                <td><span class="badge ${cls}">${lbl}</span></td>
                <td class="text-sub text-sm">${escHtml(a.geofence_name||'—')}</td>
                <td class="text-sub text-sm">${escHtml(a.message||'')}</td>
                <td>
                    ${a.is_read
                        ? '<span class="badge badge-neutral">已讀</span>'
                        : `<button class="btn btn-secondary btn-sm" onclick="markRead(${a.id},this)">標記已讀</button>`
                    }
                </td>
            </tr>`;
        }).join('');
    }

    // 分頁按鈕
    const pages = document.getElementById('pagination');
    pages.innerHTML = '';
    for (let i = 1; i <= (d.pages||1); i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        if (i === page) btn.className = 'active';
        btn.onclick = () => loadAlerts(i);
        pages.appendChild(btn);
    }
}

async function markRead(id, el) {
    await fetch(`${BASE_URL}/api/alerts.php`, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id})
    });
    el.closest('tr').style.background = '';
    el.outerHTML = '<span class="badge badge-neutral">已讀</span>';
    toast('已標記', 'success');
}

async function markAllRead() {
    await fetch(`${BASE_URL}/api/alerts.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({})
    });
    toast('全部已標記', 'success');
    loadAlerts(currentPage);
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function toast(msg, type='success') {
    const icons = {success:'bx-check-circle',error:'bx-error',warning:'bx-error-circle'};
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class='bx ${icons[type]||"bx-info-circle"}'></i>${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

loadTags();
loadAlerts(1);
</script>
</html>
