<?php
/**
 * 獨立的 MQTT 測試接收腳本 (只印出畫面、不寫入資料庫)
 * 執行方式： php test_recv.php
 */

require_once __DIR__ . '/vendor/autoload.php';
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// 您目前運行中的 MQTT 主機 IP
$mqttHost = '10.2.6.202';
$mqttPort = 1883;

echo "=============================================\n";
echo "   啟動 MQTT 測試接收程式\n";
echo "   目標主機: {$mqttHost}:{$mqttPort}\n";
echo "=============================================\n";

try {
    // 某些嚴格的主機可能不允許底線等符號，改用純英數字 ClientID
    $clientId = 'testRecv' . rand(1000, 9999);
    $mqtt = new MqttClient($mqttHost, $mqttPort, $clientId);

    $settings = (new ConnectionSettings())
        ->setKeepAliveInterval(60)
        ->setUsername('btciot')
        ->setPassword('tiger?iot');

    $mqtt->connect($settings, true);

    echo "✅ 連線成功！正在等待 eznode/# 與 eztag/# 封包...\n\n";

    // 處理收到訊息的回呼函式
    $messageHandler = function (string $topic, string $message, bool $retained) {
        $time = date('Y-m-d H:i:s');
        echo "[$time] 收到主題 (Topic) : $topic\n";

        // 嘗試將內容解析為陣列並格式化顯示，若不是 json 則直接顯示字串
        $payload = json_decode($message, true);
        if ($payload !== null) {
            echo "   -> 內容: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        } else {
            echo "   -> 內容: $message\n\n";
        }
    };

    // 訂閱主題
    $mqtt->subscribe('eznode/#', $messageHandler, 0);
    $mqtt->subscribe('eztag/#', $messageHandler, 0);

    // 開始無窮迴圈監聽
    $mqtt->loop(true);

    $mqtt->disconnect();
} catch (\Exception $e) {
    echo "❌ 發生錯誤: " . $e->getMessage() . "\n";
}
