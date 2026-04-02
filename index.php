<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '儀表板';
$pdo = db();

// ── 統計資料 ──────────────────────────────────────────────
$offlineSec  = (int)getSetting('offline_sec', 60);

$totalTags   = (int)$pdo->query("SELECT COUNT(*) FROM tags WHERE status=1")->fetchColumn();
$onlineTags  = (int)$pdo->prepare("SELECT COUNT(*) FROM tag_positions WHERE updated_at >= DATE_SUB(NOW(), INTERVAL $offlineSec SECOND)")->execute() ?: 0;
$stmt = $pdo->query("SELECT COUNT(*) FROM tag_positions WHERE updated_at >= DATE_SUB(NOW(), INTERVAL {$offlineSec} SECOND)");
$onlineTags  = (int)$stmt->fetchColumn();

$totalNodes  = (int)$pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$onlineNodes = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE status=1")->fetchColumn();
$unreadAlerts = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read=0")->fetchColumn();
$lowBatTags  = (int)$pdo->query("SELECT COUNT(*) FROM tag_positions WHERE bat > 0 AND bat < 20")->fetchColumn();

// ── 最近告警 ──────────────────────────────────────────────
$recentAlerts = $pdo->query("
    SELECT a.*, COALESCE(t.name, a.tag_id) AS tag_name, g.name AS geo_name
    FROM alerts a
    LEFT JOIN tags t ON t.tag_id = a.tag_id
    LEFT JOIN geofences g ON g.id = a.geofence_id
    ORDER BY a.triggered_at DESC LIMIT 10
")->fetchAll();

// ── 最新 TAG 狀態 ─────────────────────────────────────────
$latestTags = $pdo->query("
    SELECT tp.*, COALESCE(t.name, tp.tag_id) AS name, t.icon_color, f.name AS floor_name,
           IF(tp.updated_at >= DATE_SUB(NOW(), INTERVAL {$offlineSec} SECOND), 1, 0) AS online
    FROM tag_positions tp
    LEFT JOIN tags t ON t.tag_id = tp.tag_id
    LEFT JOIN floors f ON f.id = tp.floor_id
    ORDER BY tp.updated_at DESC LIMIT 20
")->fetchAll();

include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-grid-alt' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>儀表板</h1>
        <div class="actions">
            <span class="text-sub text-sm" id="last-update">更新中...</span>
            <a href="<?= BASE_URL ?>/map_view.php" class="btn btn-primary">
                <i class='bx bx-map'></i> 開啟地圖
            </a>
        </div>
    </div>

    <!-- ── 統計卡片 ───────────────────────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary"><i class='bx bx-purchase-tag'></i></div>
            <div>
                <div class="stat-val" id="s-online-tags"><?= $onlineTags ?></div>
                <div class="stat-label">在線 TAG</div>
                <div class="stat-trend">共 <?= $totalTags ?> 個已登記</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon secondary"><i class='bx bx-broadcast'></i></div>
            <div>
                <div class="stat-val" id="s-online-nodes"><?= $onlineNodes ?></div>
                <div class="stat-label">在線 NODE</div>
                <div class="stat-trend">共 <?= $totalNodes ?> 個錨點</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><i class='bx bx-bell'></i></div>
            <div>
                <div class="stat-val" id="s-unread-alerts"><?= $unreadAlerts ?></div>
                <div class="stat-label">未讀告警</div>
                <div class="stat-trend"><a href="<?= BASE_URL ?>/alerts.php" class="text-primary">查看全部</a></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class='bx bx-battery-low'></i></div>
            <div>
                <div class="stat-val" id="s-low-bat"><?= $lowBatTags ?></div>
                <div class="stat-label">低電量 TAG</div>
                <div class="stat-trend">電量 &lt; 20%</div>
            </div>
        </div>
    </div>

    <!-- ── 雙欄區 ──────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        <!-- 最近告警 -->
        <div class="card">
            <div class="card-title">最近告警</div>
            <div class="table-wrap" style="border:none">
                <table class="table">
                    <thead><tr>
                        <th>TAG</th><th>類型</th><th>訊息</th><th>時間</th>
                    </tr></thead>
                    <tbody id="alert-tbody">
                    <?php if (empty($recentAlerts)): ?>
                        <tr><td colspan="4" class="empty-state"><i class='bx bx-check-shield'></i><p>目前無告警</p></td></tr>
                    <?php else: foreach ($recentAlerts as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['tag_name']) ?></td>
                            <td>
                                <?php
                                $typeMap = ['enter'=>['badge-warning','進入圍欄'],
                                            'exit' =>['badge-secondary','離開圍欄'],
                                            'offline'=>['badge-danger','離線'],
                                            'low_bat'=>['badge-danger','低電量']];
                                [$cls,$lbl] = $typeMap[$a['alert_type']] ?? ['badge-neutral',$a['alert_type']];
                                ?>
                                <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                            </td>
                            <td class="text-sub text-sm"><?= htmlspecialchars($a['message'] ?? '') ?></td>
                            <td class="text-sub text-sm"><?= $a['triggered_at'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAG 狀態列表 -->
        <div class="card">
            <div class="card-title">TAG 即時狀態</div>
            <div class="table-wrap" style="border:none">
                <table class="table">
                    <thead><tr>
                        <th>TAG</th><th>樓層</th><th>電量</th><th>狀態</th>
                    </tr></thead>
                    <tbody id="tag-status-tbody">
                    <?php if (empty($latestTags)): ?>
                        <tr><td colspan="4" class="empty-state"><i class='bx bx-search'></i><p>尚無 TAG 資料</p></td></tr>
                    <?php else: foreach ($latestTags as $t): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-8">
                                    <span class="tag-dot" style="color:<?= htmlspecialchars($t['icon_color'] ?? '#38bdf8') ?>;background:<?= htmlspecialchars($t['icon_color'] ?? '#38bdf8') ?>"></span>
                                    <span><?= htmlspecialchars($t['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-sub text-sm"><?= htmlspecialchars($t['floor_name'] ?? '—') ?></td>
                            <td>
                                <?php $b = (int)$t['bat']; $bc = $b >= 50 ? 'high' : ($b >= 20 ? 'mid' : 'low'); ?>
                                <div class="bat-bar">
                                    <div class="bat-track"><div class="bat-fill <?= $bc ?>" style="width:<?= $b ?>%"></div></div>
                                    <span class="text-xs"><?= $b ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($t['online']): ?>
                                    <span class="badge badge-success">在線</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">離線</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="toast-container" id="toast-container"></div>

</div><!-- .layout-wrapper -->
</body>
<script>
// 自動更新統計（每 10 秒）
async function refreshStats() {
    try {
        const r = await fetch('<?= BASE_URL ?>/api/positions.php');
        const d = await r.json();
        if (!d.ok) return;
        document.getElementById('last-update').textContent = '最後更新：' + new Date().toLocaleTimeString('zh-TW');
    } catch(e) {}
}
refreshStats();
setInterval(refreshStats, 10000);
</script>
</html>
