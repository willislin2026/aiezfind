<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '系統設定';
$pdo = db();

// 儲存設定
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $keys = ['mqtt_host','mqtt_port','mqtt_user','mqtt_pass',
             'tx_power','path_loss_n','rssi_window','hist_interval','offline_sec'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
    }
    $saved = true;
}

// 讀取所有設定
$settings = [];
foreach (['mqtt_host','mqtt_port','mqtt_user','mqtt_pass',
          'tx_power','path_loss_n','rssi_window','hist_interval','offline_sec'] as $k) {
    $settings[$k] = getSetting($k, '');
}

include __DIR__ . '/includes/nav.php';
?>
<main class="content" id="main-content">
    <div class="page-header">
        <h1><i class='bx bx-cog' style="font-size:1.4rem;margin-right:8px;color:var(--c-primary)"></i>系統設定</h1>
    </div>

    <?php if (!empty($saved)): ?>
    <div class="toast" style="background:#0a1f2e;border:1px solid var(--c-success);color:var(--c-success);display:flex;align-items:center;gap:8px;padding:12px 18px;border-radius:8px;margin-bottom:20px;max-width:400px">
        <i class='bx bx-check-circle'></i> 設定已儲存
    </div>
    <?php endif; ?>

    <form method="post" action="settings.php">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(480px,1fr));gap:24px">

            <!-- MQTT 設定 -->
            <div class="card settings-section">
                <div class="settings-section-title">MQTT Broker 設定</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Broker IP / 主機名稱</label>
                        <input class="form-control font-mono" name="mqtt_host"
                               value="<?= htmlspecialchars($settings['mqtt_host']) ?>"
                               placeholder="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Port</label>
                        <input class="form-control font-mono" name="mqtt_port" type="number"
                               value="<?= htmlspecialchars($settings['mqtt_port']) ?>"
                               placeholder="1883">
                    </div>
                    <div class="form-group">
                        <label class="form-label">帳號（可留空）</label>
                        <input class="form-control" name="mqtt_user"
                               value="<?= htmlspecialchars($settings['mqtt_user']) ?>"
                               placeholder="MQTT 帳號">
                    </div>
                    <div class="form-group">
                        <label class="form-label">密碼（可留空）</label>
                        <input class="form-control" name="mqtt_pass" type="password"
                               value="<?= htmlspecialchars($settings['mqtt_pass']) ?>"
                               placeholder="MQTT 密碼">
                    </div>
                </div>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--c-border);font-size:0.8rem;color:var(--c-sub)">
                    <i class='bx bx-info-circle'></i>
                    修改後需重新啟動 MQTT Daemon：<code style="color:var(--c-primary)">php daemon/mqtt_daemon.php</code>
                </div>
            </div>

            <!-- 定位算法設定 -->
            <div class="card settings-section">
                <div class="settings-section-title">定位算法參數</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">BLE TxPower（dBm，1公尺基準）</label>
                        <input class="form-control font-mono" name="tx_power" type="number"
                               value="<?= htmlspecialchars($settings['tx_power']) ?>"
                               placeholder="-59">
                        <span class="text-xs text-sub" style="margin-top:4px">通常為 -59 到 -65</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">路徑損耗指數 n</label>
                        <input class="form-control font-mono" name="path_loss_n" type="number" step="0.1"
                               value="<?= htmlspecialchars($settings['path_loss_n']) ?>"
                               placeholder="2.0">
                        <span class="text-xs text-sub" style="margin-top:4px">室內建議 2.0~3.5（阻礙多→調大）</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">RSSI 時間視窗（秒）</label>
                        <input class="form-control font-mono" name="rssi_window" type="number"
                               value="<?= htmlspecialchars($settings['rssi_window']) ?>"
                               placeholder="5">
                        <span class="text-xs text-sub" style="margin-top:4px">收集幾秒內的 RSSI 用於定位</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">歷史記錄間隔（秒）</label>
                        <input class="form-control font-mono" name="hist_interval" type="number"
                               value="<?= htmlspecialchars($settings['hist_interval']) ?>"
                               placeholder="30">
                    </div>
                    <div class="form-group">
                        <label class="form-label">離線判斷（超過 N 秒無訊號）</label>
                        <input class="form-control font-mono" name="offline_sec" type="number"
                               value="<?= htmlspecialchars($settings['offline_sec']) ?>"
                               placeholder="60">
                    </div>
                </div>
            </div>
        </div>

        <!-- 儲存按鈕 -->
        <div style="margin-top:24px">
            <button class="btn btn-primary" type="submit" name="save" value="1">
                <i class='bx bx-save'></i> 儲存設定
            </button>
        </div>
    </form>

    <!-- 系統資訊 -->
    <div class="card" style="margin-top:28px">
        <div class="settings-section-title">系統資訊</div>
        <div style="font-size:0.85rem;line-height:2;color:var(--c-sub)">
            <div><span style="color:var(--c-text)">版本：</span><?= APP_VERSION ?></div>
            <div><span style="color:var(--c-text)">PHP 版本：</span><?= PHP_VERSION ?></div>
            <div><span style="color:var(--c-text)">資料庫：</span><?= DB_NAME ?> @ <?= DB_HOST ?></div>
            <div><span style="color:var(--c-text)">MQTT Topics：</span><code style="color:var(--c-primary)">eznode/#</code>，<code style="color:var(--c-primary)">eztag/#</code></div>
            <div><span style="color:var(--c-text)">Daemon 啟動指令：</span>
                <code style="color:var(--c-primary)">php <?= realpath(__DIR__ . '/daemon/mqtt_daemon.php') ?></code>
            </div>
        </div>
    </div>
</main>
</div>
</html>
