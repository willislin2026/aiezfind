#!/usr/bin/env php
<?php
/**
 * AiezFind — MQTT 訂閱守護程序 (使用純 PHP 套件 php-mqtt/client)
 *
 * 執行方式：
 *   php daemon/mqtt_daemon.php
 *
 * 訂閱：
 *   eznode/#  → NODE 心跳（更新 nodes 表狀態與電量）
 *   eztag/#   → TAG 偵測（寫入 rssi_log，計算位置，偵測圍欄）
 */

declare(strict_types=1);
set_time_limit(0);
ini_set('display_errors', '1');

// 取得專案根目錄路徑
$rootDir = dirname(__DIR__);

// 載入 Composer 自動加載檔
if (!file_exists($rootDir . '/vendor/autoload.php')) {
    die("錯誤：找不到 vendor/autoload.php，請先執行 composer require php-mqtt/client\n");
}
require_once $rootDir . '/vendor/autoload.php';

require_once $rootDir . '/includes/config.php';
require_once $rootDir . '/includes/db.php';
require_once $rootDir . '/includes/positioning.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// ── 日誌輸出 ─────────────────────────────────────────────
function logMsg(string $level, string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] [$level] $msg\n";
    flush();
}

logMsg('INFO', '=== AiezFind MQTT Daemon 啟動 ===');

// ── 讀取 MQTT 設定（優先從資料庫讀取）────────────────────
try {
    $mqttHost = getSetting('mqtt_host', MQTT_HOST);
    $mqttPort = (int) getSetting('mqtt_port', MQTT_PORT);
    $mqttUser = getSetting('mqtt_user', MQTT_USER);
    $mqttPass = getSetting('mqtt_pass', MQTT_PASS);
    logMsg('INFO', "MQTT Broker: {$mqttHost}:{$mqttPort}");
} catch (Exception $e) {
    logMsg('WARN', '無法讀取資料庫設定，使用預設值：' . $e->getMessage());
    $mqttHost = MQTT_HOST;
    $mqttPort = MQTT_PORT;
    $mqttUser = MQTT_USER;
    $mqttPass = MQTT_PASS;
}

// ── 建立 MQTT Client ─────────────────────────────────────
$clientId = MQTT_CLIENT_ID . '_' . getmypid();

try {
    $mqtt = new MqttClient($mqttHost, $mqttPort, $clientId);

    $connectionSettings = (new ConnectionSettings())
        ->setKeepAliveInterval(60);

    if ($mqttUser !== '') {
        $connectionSettings->setUsername($mqttUser)->setPassword($mqttPass);
    }

    $mqtt->connect($connectionSettings, true);
    logMsg('INFO', 'MQTT 連線成功');

    // 處理收到訊息的回呼函式
    $messageHandler = function (string $topic, string $message, bool $retained) {
        $payload = json_decode($message, true);
        if (!is_array($payload)) {
            logMsg('WARN', "無效 JSON payload — topic: $topic");
            return;
        }

        try {
            $pdo = db();

            if (str_starts_with($topic, 'eznode/')) {
                handleNodeHeartbeat($pdo, $topic, $payload);
            } elseif (str_starts_with($topic, 'eztag/')) {
                handleTagDetection($pdo, $topic, $payload);
            }
        } catch (Throwable $e) {
            logMsg('ERROR', $e->getMessage());
        }
    };

    // 訂閱主題
    $mqtt->subscribe('eznode/#', $messageHandler, 1);
    $mqtt->subscribe('eztag/#',  $messageHandler, 1);
    logMsg('INFO', '已訂閱 eznode/# 與 eztag/#');

    // 進入無窮迴圈監聽
    $mqtt->loop(true);
    
    $mqtt->disconnect();
} catch (\Exception $e) {
    logMsg('ERROR', "MQTT 發生錯誤: " . $e->getMessage());
    exit(1);
}

// ═══════════════════════════════════════════════════════════════
// 處理 NODE 心跳
// ═══════════════════════════════════════════════════════════════
function handleNodeHeartbeat(PDO $pdo, string $topic, array $payload): void
{
    $nodeId = $payload['ID'] ?? null;
    if (!$nodeId) return;

    // BAT：HEX → 十進位 %
    $bat = isset($payload['BAT']) ? hexdec($payload['BAT']) : 0;
    $bat = min(100, max(0, (int)$bat));

    // Upsert：有則更新，無則新增（不覆蓋管理員設定的 floor_id/x/y/name）
    $sql = "INSERT INTO nodes (node_id, bat, status, last_seen)
            VALUES (:nid, :bat, 1, NOW())
            ON DUPLICATE KEY UPDATE
                bat       = VALUES(bat),
                status    = 1,
                last_seen = NOW()";
    $pdo->prepare($sql)->execute([':nid' => $nodeId, ':bat' => $bat]);

    logMsg('NODE', sprintf('%s  BAT=%d%%', $nodeId, $bat));
}

// ═══════════════════════════════════════════════════════════════
// 處理 TAG 偵測
// ═══════════════════════════════════════════════════════════════
function handleTagDetection(PDO $pdo, string $topic, array $payload): void
{
    $nodeId  = $payload['ID']     ?? null;   // 偵測的 NODE
    $tagId   = $payload['CardNo'] ?? null;   // 被偵測的 TAG
    $rssiHex = $payload['RSSI']   ?? null;   // RSSI (HEX, 有符號8-bit)
    $batHex  = $payload['BAT']    ?? '00';

    if (!$nodeId || !$tagId || $rssiHex === null) {
        logMsg('WARN', "TAG 訊息欄位不完整: " . json_encode($payload));
        return;
    }

    // RSSI：HEX 有符號 8-bit → dBm
    $rssiUint = hexdec($rssiHex);
    $rssiDbm  = $rssiUint > 127 ? $rssiUint - 256 : $rssiUint;

    // TAG 電量
    $bat = min(100, max(0, (int)hexdec($batHex)));

    logMsg('TAG', sprintf('%s ← NODE %s  RSSI=%ddBm  BAT=%d%%',
        $tagId, $nodeId, $rssiDbm, $bat));

    // 1. 確保 TAG 存在（初次自動新增，名稱預設為 CardNo）
    $pdo->prepare(
        "INSERT IGNORE INTO tags (tag_id, name) VALUES (?, ?)"
    )->execute([$tagId, $tagId]);

    // 2. 寫入 rssi_log
    $pdo->prepare(
        "INSERT INTO rssi_log (node_id, tag_id, rssi) VALUES (?, ?, ?)"
    )->execute([$nodeId, $tagId, $rssiDbm]);

    // 3. 清除過舊的 rssi_log（保留最近 24 小時）
    static $lastClean = 0;
    if (time() - $lastClean > 3600) {
        $pdo->exec("DELETE FROM rssi_log WHERE received_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $lastClean = time();
        logMsg('INFO', 'rssi_log 舊資料清理完成');
    }

    // 4. WCL 定位計算
    $pos = calculatePosition($pdo, $tagId, $bat, $rssiDbm);

    if ($pos) {
        // 5. 寫入最新位置
        $pdo->prepare("
            REPLACE INTO tag_positions
                (tag_id, floor_id, x, y, confidence, node_count, bat, last_rssi, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $tagId,
            $pos['floor_id'], $pos['x'], $pos['y'],
            $pos['confidence'], $pos['node_count'],
            $bat, $rssiDbm
        ]);

        // 6. 歷史記錄（依 hist_interval 設定間隔）
        $interval = (int)getSetting('hist_interval', 30);
        $lastHistStmt = $pdo->prepare(
            "SELECT UNIX_TIMESTAMP(recorded_at) FROM position_history WHERE tag_id=? ORDER BY recorded_at DESC LIMIT 1"
        );
        $lastHistStmt->execute([$tagId]);
        $lastHistTime = (int)$lastHistStmt->fetchColumn();

        if ((time() - $lastHistTime) >= $interval) {
            $pdo->prepare("
                INSERT INTO position_history (tag_id, floor_id, x, y, confidence)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$tagId, $pos['floor_id'], $pos['x'], $pos['y'], $pos['confidence']]);
        }

        // 7. 電子圍欄偵測
        checkGeofences($pdo, $tagId, $pos);

        logMsg('POS', sprintf('%s → floor=%d x=%.1f y=%.1f conf=%.0f%% (n=%d)',
            $tagId, $pos['floor_id'], $pos['x'], $pos['y'],
            $pos['confidence'] * 100, $pos['node_count']));
    }

    // 8. 低電量告警（電量 < 20%）
    if ($bat > 0 && $bat < 20) {
        triggerAlert($pdo, $tagId, null, 'low_bat',
            "TAG {$tagId} 電量低 ({$bat}%)");
    }
}

// ═══════════════════════════════════════════════════════════════
// 電子圍欄偵測
// ═══════════════════════════════════════════════════════════════
function checkGeofences(PDO $pdo, string $tagId, array $pos): void
{
    $fences = $pdo->prepare(
        "SELECT id, name, points FROM geofences WHERE floor_id=? AND active=1"
    );
    $fences->execute([$pos['floor_id']]);
    $fences = $fences->fetchAll();

    foreach ($fences as $fence) {
        $points = json_decode($fence['points'], true);
        if (!$points) continue;

        $isInside = pointInPolygon([$pos['x'], $pos['y']], $points);

        // 讀取上次狀態（利用 alerts 最後一筆判斷）
        $lastAlert = $pdo->prepare(
            "SELECT alert_type FROM alerts WHERE tag_id=? AND geofence_id=?
             ORDER BY triggered_at DESC LIMIT 1"
        );
        $lastAlert->execute([$tagId, $fence['id']]);
        $lastType = $lastAlert->fetchColumn();

        if ($isInside && $lastType !== 'enter') {
            triggerAlert($pdo, $tagId, $fence['id'], 'enter',
                "TAG {$tagId} 進入圍欄「{$fence['name']}」");
        } elseif (!$isInside && $lastType === 'enter') {
            triggerAlert($pdo, $tagId, $fence['id'], 'exit',
                "TAG {$tagId} 離開圍欄「{$fence['name']}」");
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// 寫入告警（防止重複：60 秒內同類告警只記一次）
// ═══════════════════════════════════════════════════════════════
function triggerAlert(PDO $pdo, string $tagId, ?int $geofenceId, string $type, string $msg): void
{
    $recent = $pdo->prepare(
        "SELECT id FROM alerts WHERE tag_id=? AND geofence_id<=>? AND alert_type=?
         AND triggered_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1"
    );
    $recent->execute([$tagId, $geofenceId, $type]);
    if ($recent->fetchColumn()) return;

    $pdo->prepare(
        "INSERT INTO alerts (tag_id, geofence_id, alert_type, message) VALUES (?,?,?,?)"
    )->execute([$tagId, $geofenceId, $type, $msg]);

    logMsg('ALERT', $msg);
}
