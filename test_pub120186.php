<?php
/**
 * 測試用的 MQTT 發佈腳本 (手動發送 NODE 與 TAG 資料)
 * 執行方式： php test_pub.php [node|tag] [RSSI數值]
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
use PhpMqtt\Client\MqttClient;

$type = $argv[1] ?? 'all'; // node, tag, or all

try {
    $mqttHost = getSetting('mqtt_host', '127.0.0.1');
    $mqtt = new MqttClient($mqttHost, 1883, 'test_pub_client');
    $mqtt->connect();

    // 1. 模擬 NODE 心跳 (維持 NODE 上線狀態，電量 100%)
    if ($type === 'node' || $type === 'all') {
        $nodeTopic = 'eznode/1201A4';
        $nodePayload = json_encode([
            "ID" => "1201A4",
            "BAT" => "64", // HEX 64 = 100% 電量
            "ST" => "00"
        ]);
        $mqtt->publish($nodeTopic, $nodePayload, 0);
        echo "✅ 已發送 NODE 心跳 -> $nodeTopic : $nodePayload\n";
    }

    // 2. 模擬 TAG 被偵測到
    if ($type === 'tag' || $type === 'all') {
        $tagTopic = 'eztag/120094';

        // 接受第二個參數改變距離 (RSSI)，預設為 C1 (-63 dBm) 約 1 公尺
        // 若帶入更小的值如 D8 (-40 dBm) 會更近， B0 (-80 dBm) 會更遠
        $rssi = $argv[2] ?? "C1";

        $tagPayload = json_encode([
            "ID" => "120186", // 偵測到它的 NODE ID
            "CardNo" => "120094", // TAG 本身的 ID
            "BAT" => "4A",     // 轉換約 74%
            "RSSI" => $rssi,
        ]);
        $mqtt->publish($tagTopic, $tagPayload, 0);
        echo "✅ 已發送 TAG 偵測 -> $tagTopic : $tagPayload\n";
    }

    $mqtt->disconnect();
} catch (Exception $e) {
    echo "發送失敗: " . $e->getMessage() . "\n";
}
