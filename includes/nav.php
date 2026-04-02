<?php
/**
 * AiezFind — 側邊欄導覽
 * 使用前須已定義 $pageTitle（用於 <title>）
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    ['id' => 'index',    'href' => BASE_URL . '/index.php',    'icon' => 'bx-grid-alt',       'label' => '儀表板'],
    ['id' => 'map_view', 'href' => BASE_URL . '/map_view.php', 'icon' => 'bx-map',            'label' => '即時地圖'],
    ['sep' => true, 'label' => '裝置管理'],
    ['id' => 'tags',     'href' => BASE_URL . '/tags.php',     'icon' => 'bx-purchase-tag',   'label' => 'TAG 管理'],
    ['id' => 'nodes',    'href' => BASE_URL . '/nodes.php',    'icon' => 'bx-broadcast',      'label' => 'NODE 管理'],
    ['id' => 'floors',   'href' => BASE_URL . '/floors.php',   'icon' => 'bx-layer',          'label' => '樓層管理'],
    ['sep' => true, 'label' => '分析'],
    ['id' => 'history',  'href' => BASE_URL . '/history.php',  'icon' => 'bx-history',        'label' => '歷史軌跡'],
    ['id' => 'geofence', 'href' => BASE_URL . '/geofence.php', 'icon' => 'bx-shield-quarter', 'label' => '電子圍欄'],
    ['id' => 'alerts',   'href' => BASE_URL . '/alerts.php',   'icon' => 'bx-bell',           'label' => '告警紀錄'],
    ['sep' => true, 'label' => '系統'],
    ['id' => 'settings', 'href' => BASE_URL . '/settings.php', 'icon' => 'bx-cog',            'label' => '系統設定'],
];

// 未讀告警數
$unreadCount = 0;
try {
    $unreadCount = (int)db()->query("SELECT COUNT(*) FROM alerts WHERE is_read=0")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'AiezFind') ?> — AiezFind</title>
    <meta name="description" content="AiezFind BLE TAG & NODE 室內定位系統">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<!-- ═══ 側邊欄 ════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class='bx bx-radio-circle-marked'></i>
        </div>
        <div class="logo-text">
            <span class="logo-name">AiezFind</span>
            <span class="logo-ver">v<?= APP_VERSION ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
        <?php if (!empty($item['sep'])): ?>
        <div class="nav-separator"><?= htmlspecialchars($item['label']) ?></div>
        <?php else: ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= $currentPage === $item['id'] ? 'active' : '' ?>"
           id="nav-<?= $item['id'] ?>">
            <i class='bx <?= $item['icon'] ?>'></i>
            <span><?= $item['label'] ?></span>
            <?php if ($item['id'] === 'alerts' && $unreadCount > 0): ?>
            <span class="nav-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sys-status">
            <span class="status-dot" id="mqtt-dot" title="MQTT 狀態"></span>
            <span class="status-label">MQTT</span>
        </div>
    </div>
</aside>

<!-- ═══ 主內容 ═══════════════════════════════════════════ -->
<div class="layout-wrapper">
