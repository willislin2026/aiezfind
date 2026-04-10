<?php
/**
 * 生成 AiezFind 操作手冊 Word 檔 (.docx)
 * 執行方式：php generate_manual.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('微軟正黑體');
$phpWord->setDefaultFontSize(11);

// 定義樣式
$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 20, 'color' => '1e3a5f', 'name' => '微軟正黑體'], ['spaceAfter' => 200]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 15, 'color' => '1e3a5f', 'name' => '微軟正黑體'], ['spaceAfter' => 120, 'spaceBefore' => 240]);
$phpWord->addTitleStyle(3, ['bold' => true, 'size' => 12, 'color' => '2563eb', 'name' => '微軟正黑體'], ['spaceAfter' => 80, 'spaceBefore' => 160]);

$codeStyle = ['name' => 'Courier New', 'size' => 9, 'color' => '1a1a2e'];
$codeBg = ['bgColor' => 'f0f4f8'];
$noteStyle = ['name' => '微軟正黑體', 'size' => 9.5, 'color' => '555555', 'italic' => true];

$tableStyle = [
    'borderSize' => 6, 'borderColor' => 'cccccc',
    'cellMarginTop' => 80, 'cellMarginBottom' => 80,
    'cellMarginLeft' => 100, 'cellMarginRight' => 100,
];
$headerCellStyle = ['bgColor' => '1e3a5f'];
$headerFontStyle = ['bold' => true, 'color' => 'ffffff', 'name' => '微軟正黑體', 'size' => 10];
$cellStyle = ['bgColor' => 'f8fafc'];
$altCellStyle = ['bgColor' => 'ffffff'];

$section = $phpWord->addSection(['marginTop' => 1200, 'marginBottom' => 1200, 'marginLeft' => 1200, 'marginRight' => 1200]);

// ── 封面標題 ──────────────────────────────────────────────────
$section->addText('AiezFind', ['bold' => true, 'size' => 36, 'color' => '1e3a5f', 'name' => '微軟正黑體'], ['alignment' => 'center', 'spaceAfter' => 100]);
$section->addText('BLE 室內定位系統', ['bold' => true, 'size' => 22, 'color' => '2563eb', 'name' => '微軟正黑體'], ['alignment' => 'center', 'spaceAfter' => 100]);
$section->addText('操作手冊', ['bold' => true, 'size' => 18, 'color' => '555555', 'name' => '微軟正黑體'], ['alignment' => 'center', 'spaceAfter' => 200]);
$section->addText('版本 v1.0.0　｜　' . date('Y-m-d'), ['size' => 10, 'color' => '888888', 'name' => '微軟正黑體'], ['alignment' => 'center']);
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 1 章：系統架構總覽
// ════════════════════════════════════════════
$section->addTitle('1. 系統架構總覽', 1);
$section->addText('本系統透過固定部署的 NODE 接收器，接收 BLE TAG 發出的廣播訊號，再透過 MQTT 協議將資料彙整至後端伺服器，由 PHP Daemon 進行加權質心 (WCL) 定位計算後，呈現在平面圖上。', ['name' => '微軟正黑體']);
$section->addTextBreak(1);

$section->addTitle('資料流程', 2);
$section->addText('BLE TAG 設備  →（BLE廣播）→  NODE 固定錨點  →（MQTT發布）→  Broker (10.2.6.202)', $codeStyle, $codeBg);
$section->addText('→  PHP MQTT Daemon  →  MySQL 資料庫  →  WCL 定位算法  →  Web 地圖即時顯示', $codeStyle, $codeBg);
$section->addTextBreak(1);

$section->addTitle('MQTT 訊息格式', 2);

$section->addText('NODE 心跳 (Topic: eznode/{NodeID})', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('{"ID":"1201A2","BAT":"64","ST":"00","GP":"0000","CNT":"019FF4","CS":"AD"}', $codeStyle, $codeBg);
$section->addTextBreak(1);

$section->addText('TAG 偵測 (Topic: eztag/{CardNo})', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('{"ID":"1201A2","CardNo":"1200A3","BAT":"4A","ST":"00","RSSI":"C1","CNT":"002D04","CS":"9C"}', $codeStyle, $codeBg);
$section->addTextBreak(1);

$phpWord->addTableStyle('FieldTable', $tableStyle);
$table = $section->addTable('FieldTable');
$table->addRow();
$table->addCell(1800, $headerCellStyle)->addText('欄位', $headerFontStyle);
$table->addCell(5000, $headerCellStyle)->addText('說明', $headerFontStyle);
foreach ([
    ['ID', '偵測到此 TAG 的 NODE ID'],
    ['CardNo', 'TAG 本身的卡號'],
    ['BAT', '電量 (HEX，例如 64 = 100%)'],
    ['RSSI', '訊號強度 (HEX 有符號 8-bit，例如 C1 = -63 dBm)'],
    ['ST', 'TAG 狀態旗標'],
    ['CS', '校驗碼'],
] as $i => [$f, $d]) {
    $bg = $i % 2 === 0 ? $cellStyle : $altCellStyle;
    $table->addRow();
    $table->addCell(1800, $bg)->addText($f, $codeStyle);
    $table->addCell(5000, $bg)->addText($d, ['name' => '微軟正黑體', 'size' => 10]);
}
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 2 章：環境需求與安裝
// ════════════════════════════════════════════
$section->addTitle('2. 環境需求與安裝', 1);

$phpWord->addTableStyle('EnvTable', $tableStyle);
$table2 = $section->addTable('EnvTable');
$table2->addRow();
$table2->addCell(2200, $headerCellStyle)->addText('項目', $headerFontStyle);
$table2->addCell(4600, $headerCellStyle)->addText('規格', $headerFontStyle);
foreach ([
    ['作業系統', 'Windows 10 / 11'],
    ['Web Server', 'XAMPP (Apache + PHP 8.1 + MariaDB)'],
    ['PHP', 'PHP 8.1（含 PDO、PDO_MySQL 擴充）'],
    ['MQTT Broker', 'Mosquitto 或任何相容的 Broker'],
    ['PHP MQTT 套件', 'php-mqtt/client（透過 Composer 安裝，免 DLL）'],
] as $i => [$k, $v]) {
    $bg = $i % 2 === 0 ? $cellStyle : $altCellStyle;
    $table2->addRow();
    $table2->addCell(2200, $bg)->addText($k, ['bold' => true, 'name' => '微軟正黑體', 'size' => 10]);
    $table2->addCell(4600, $bg)->addText($v, ['name' => '微軟正黑體', 'size' => 10]);
}
$section->addTextBreak(1);

$section->addTitle('安裝步驟', 2);
$section->addListItem('開啟 XAMPP Control Panel，確認 Apache 與 MySQL 均顯示綠燈（已啟動）。', 0, ['name' => '微軟正黑體']);
$section->addListItem('開啟 PowerShell，執行以下指令建立資料庫：', 0, ['name' => '微軟正黑體']);
$section->addText('& "C:\\xampp\\mysql\\bin\\mysql.exe" -u root -pmysql_root -e "CREATE DATABASE IF NOT EXISTS aiezfind CHARACTER SET utf8mb4;"', $codeStyle, $codeBg);
$section->addListItem('匯入資料表結構：', 0, ['name' => '微軟正黑體']);
$section->addText('& "C:\\xampp\\mysql\\bin\\mysql.exe" -u root -pmysql_root --default-character-set=utf8mb4 aiezfind -e "source C:/xampp/htdocs/aiezfind/install/schema.sql"', $codeStyle, $codeBg);
$section->addListItem('安裝 PHP MQTT 套件（只需一次）：', 0, ['name' => '微軟正黑體']);
$section->addText('cd C:\\xampp\\htdocs\\aiezfind', $codeStyle, $codeBg);
$section->addText('& "C:\\xampp\\php\\php.exe" composer.phar require php-mqtt/client', $codeStyle, $codeBg);
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 3 章：初次啟動流程
// ════════════════════════════════════════════
$section->addTitle('3. 初次啟動流程', 1);
$section->addText('初次使用，請務必按照以下順序設定：', ['name' => '微軟正黑體']);
$section->addTextBreak(1);
$section->addText('啟動 Daemon → 新增樓層 → 上傳平面圖 → 設定 NODE 座標 → 建立 TAG → 開啟地圖', ['bold' => true, 'name' => '微軟正黑體', 'color' => '2563eb']);
$section->addTextBreak(1);

$section->addTitle('第一步：新增樓層並上傳平面圖', 2);
$section->addText('網址：http://localhost/aiezfind/floors.php', $codeStyle);
$section->addListItem('點擊右上角「新增樓層」，輸入名稱（例如：1F 辦公區）與排序。', 0, ['name' => '微軟正黑體']);
$section->addListItem('找到剛建立的樓層，點擊「上傳圖片」。', 0, ['name' => '微軟正黑體']);
$section->addListItem('選擇平面圖（PNG / JPG，建議解析度 1000px 以上），系統自動套用圖片寬高。', 0, ['name' => '微軟正黑體']);
$section->addTextBreak(1);

$section->addTitle('第二步：設定 NODE 座標', 2);
$section->addText('網址：http://localhost/aiezfind/nodes.php', $codeStyle);
$section->addListItem('Daemon 啟動後，收到 NODE 心跳的設備會自動出現在列表中。', 0, ['name' => '微軟正黑體']);
$section->addListItem('點擊「設定座標」，選擇所在樓層。', 0, ['name' => '微軟正黑體']);
$section->addListItem('直接在地圖預覽圖上點擊設定物理位置（支援拖曳微調）。', 0, ['name' => '微軟正黑體']);
$section->addListItem('點擊「儲存」完成設定。每台 NODE 都必須設定座標，系統才能計算定位。', 0, ['name' => '微軟正黑體']);
$section->addTextBreak(1);

$section->addTitle('第三步：建立 TAG 資料', 2);
$section->addText('網址：http://localhost/aiezfind/tags.php', $codeStyle);
$section->addListItem('點擊右上角「新增 TAG」，輸入硬體 CardNo（例如：1200A3）。', 0, ['name' => '微軟正黑體']);
$section->addListItem('填入顯示名稱（人員或物品名稱）與專屬顏色。', 0, ['name' => '微軟正黑體']);
$section->addListItem('點擊儲存。Daemon 接收到訊號也會自動建立基本資料，再進來編輯名稱即可。', 0, ['name' => '微軟正黑體']);
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 4 章：MQTT Daemon 操作說明
// ════════════════════════════════════════════
$section->addTitle('4. MQTT Daemon 操作說明', 1);
$section->addText('Daemon 是本系統的核心，負責從 MQTT Broker 接收資料並計算 TAG 位置後儲存至資料庫，必須持續在背景執行。', ['name' => '微軟正黑體']);
$section->addTextBreak(1);

$section->addTitle('啟動指令', 2);
$section->addText('正式版（串接 10.2.6.202 實機，含寫入資料庫）：', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\aiezfind\\daemon\\mqtt_daemon1.php', $codeStyle, $codeBg);
$section->addTextBreak(1);
$section->addText('本機測試版（串接 localhost）：', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\aiezfind\\daemon\\mqtt_daemon.php', $codeStyle, $codeBg);
$section->addTextBreak(1);

$section->addTitle('日誌輸出格式', 2);
$section->addText('[2026-04-07 12:00:00] [INFO] === AiezFind MQTT Daemon 啟動 ===', $codeStyle, $codeBg);
$section->addText('[2026-04-07 12:00:00] [INFO] MQTT Broker: 10.2.6.202:1883', $codeStyle, $codeBg);
$section->addText('[2026-04-07 12:00:00] [INFO] MQTT 連線成功', $codeStyle, $codeBg);
$section->addText('[2026-04-07 12:00:02] [TAG]  120094 ← NODE 120171  RSSI=-65dBm  BAT=78%', $codeStyle, $codeBg);
$section->addText('[2026-04-07 12:00:02] [POS]  120094 → floor=1 x=345.0 y=210.3 conf=89% (n=2)', $codeStyle, $codeBg);
$section->addTextBreak(1);

$phpWord->addTableStyle('LogTable', $tableStyle);
$logtable = $section->addTable('LogTable');
$logtable->addRow();
$logtable->addCell(1500, $headerCellStyle)->addText('標籤', $headerFontStyle);
$logtable->addCell(5300, $headerCellStyle)->addText('說明', $headerFontStyle);
foreach ([
    ['[INFO]',  '系統資訊（連線成功、訂閱主題）'],
    ['[NODE]',  '收到 NODE 心跳，更新電量與在線狀態'],
    ['[TAG]',   '偵測到 TAG，記錄 RSSI 訊號強度'],
    ['[POS]',   '定位計算完成，輸出座標與信心度'],
    ['[ALERT]', '触發告警（低電量、圍欄進入/離開）'],
    ['[WARN]',  '警告（封包格式異常等）'],
    ['[ERROR]', '錯誤（資料庫連線失敗等）'],
] as $i => [$tag, $desc]) {
    $bg = $i % 2 === 0 ? $cellStyle : $altCellStyle;
    $logtable->addRow();
    $logtable->addCell(1500, $bg)->addText($tag, $codeStyle);
    $logtable->addCell(5300, $bg)->addText($desc, ['name' => '微軟正黑體', 'size' => 10]);
}
$section->addTextBreak(1);

$section->addTitle('測試工具', 2);
$section->addText('純接收監聽（確認封包是否存在，不寫入資料庫）：', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\aiezfind\\test_recv.php', $codeStyle, $codeBg);
$section->addTextBreak(1);
$section->addText('手動發送模擬封包（開發測試用）：', ['bold' => true, 'name' => '微軟正黑體']);
$section->addText('C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\aiezfind\\test_pub.php tag C1  # -63 dBm', $codeStyle, $codeBg);
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 5 章：Web 介面功能說明
// ════════════════════════════════════════════
$section->addTitle('5. Web 介面功能說明', 1);
$section->addText('系統網址：http://localhost/aiezfind/', ['bold' => true, 'name' => '微軟正黑體', 'color' => '2563eb']);
$section->addTextBreak(1);

$pages = [
    ['📊 儀表板', 'index.php', '顯示在線 TAG 數、在線 NODE 數、未讀告警數、低電量 TAG 數。下方呈現最近告警紀錄與 TAG 即時狀態，每 10 秒自動刷新。'],
    ['🗺️ 即時地圖', 'map_view.php', '以 Leaflet.js 顯示 PNG 平面圖，彩色圓點代表 TAG（離線變灰色），紫色圓點代表 NODE。每 2 秒自動刷新位置。點擊標記可查看詳細資訊（電量、RSSI、信心度）。'],
    ['📂 TAG 管理', 'tags.php', '新增、編輯（名稱、顏色）、停用或刪除 TAG。顯示最後偵測位置、電量與狀態。'],
    ['📡 NODE 管理', 'nodes.php', '查看所有 NODE 的電量與在線狀態。透過互動式地圖設定每台 NODE 的實際座標（必要步驟）。'],
    ['🏢 樓層管理', 'floors.php', '新增樓層、上傳平面圖（系統自動偵測像素尺寸）、調整樓層排序。'],
    ['⏱️ 歷史軌跡', 'history.php', '選擇 TAG 與日期後，地圖以虛線呈現移動路徑。支援播放動畫、暫停、逐筆、快轉（0.5x/1x/2x/4x）。'],
    ['🛡️ 電子圍欄', 'geofence.php', '在地圖上點選 3 點以上繪製多邊形區域，命名後儲存。TAG 進出圍欄會自動產生告警紀錄。'],
    ['🚨 告警紀錄', 'alerts.php', '依 TAG 或類型篩選告警（進入圍欄、離開圍欄、離線、低電量）。支援分頁與標記已讀。'],
    ['⚙️ 系統設定', 'settings.php', '調整 MQTT 主機、帳密，以及定位算法參數（TxPower、路徑損耗指數、時間視窗等）。'],
];

$phpWord->addTableStyle('PageTable', $tableStyle);
$ptable = $section->addTable('PageTable');
$ptable->addRow();
$ptable->addCell(1600, $headerCellStyle)->addText('頁面', $headerFontStyle);
$ptable->addCell(2000, $headerCellStyle)->addText('網址', $headerFontStyle);
$ptable->addCell(4200, $headerCellStyle)->addText('功能說明', $headerFontStyle);
foreach ($pages as $i => [$name, $url, $desc]) {
    $bg = $i % 2 === 0 ? $cellStyle : $altCellStyle;
    $ptable->addRow();
    $ptable->addCell(1600, $bg)->addText($name, ['bold' => true, 'name' => '微軟正黑體', 'size' => 10]);
    $ptable->addCell(2000, $bg)->addText($url, $codeStyle);
    $ptable->addCell(4200, $bg)->addText($desc, ['name' => '微軟正黑體', 'size' => 9.5]);
}
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 6 章：定位參數調校
// ════════════════════════════════════════════
$section->addTitle('6. 定位參數調校', 1);
$section->addText('路徑：系統設定頁面 (settings.php)，調整後儲存，下次 Daemon 接收時即生效。', $noteStyle);
$section->addTextBreak(1);

$phpWord->addTableStyle('ParamTable', $tableStyle);
$ptable2 = $section->addTable('ParamTable');
$ptable2->addRow();
$ptable2->addCell(2200, $headerCellStyle)->addText('參數', $headerFontStyle);
$ptable2->addCell(1200, $headerCellStyle)->addText('預設值', $headerFontStyle);
$ptable2->addCell(4400, $headerCellStyle)->addText('說明與調整建議', $headerFontStyle);
foreach ([
    ['MQTT Host', '127.0.0.1', 'MQTT Broker 的 IP 位址'],
    ['MQTT Port', '1883', 'MQTT 連接埠（Mosquitto 預設 1883）'],
    ['BLE TxPower', '-59 dBm', 'TAG 在 1 公尺時的基準 RSSI。硬體發射較弱 → 調至 -65；較強 → 調至 -55'],
    ['路徑損耗指數 n', '2.0', '空曠空間 = 2.0；多隔間/金屬障礙的室內建議 2.5 ~ 3.5'],
    ['RSSI 時間視窗', '5 秒', '參與定位計算的最近幾秒資料。靈敏但易跳動 → 3 秒；穩定但延遲 → 10 秒'],
    ['歷史記錄間隔', '30 秒', '每隔幾秒寫入一筆歷史座標'],
    ['離線判斷秒數', '60 秒', '超過幾秒無訊號則標記為離線'],
] as $i => [$p, $d, $desc]) {
    $bg = $i % 2 === 0 ? $cellStyle : $altCellStyle;
    $ptable2->addRow();
    $ptable2->addCell(2200, $bg)->addText($p, ['bold' => true, 'name' => '微軟正黑體', 'size' => 10]);
    $ptable2->addCell(1200, $bg)->addText($d, $codeStyle);
    $ptable2->addCell(4400, $bg)->addText($desc, ['name' => '微軟正黑體', 'size' => 9.5]);
}
$section->addPageBreak();

// ════════════════════════════════════════════
// 第 7 章：常見問題排除
// ════════════════════════════════════════════
$section->addTitle('7. 常見問題排除', 1);

$qaList = [
    [
        'Q：啟動 Daemon 後看不到任何訊息',
        [
            '確認 MQTT Broker (10.2.6.202:1883) 是否可連線。',
            '使用純接收監聽腳本確認封包是否傳入：php test_recv.php',
            '確認訂閱的 Topic（eznode/# / eztag/#）與硬體設定相符。',
        ]
    ],
    [
        'Q：連線時出現 unauthorized 錯誤',
        [
            'MQTT Broker 有啟用帳號密碼驗證，請確認 mqtt_daemon1.php 中的帳號 (btciot) 與密碼 (tiger?iot) 正確。',
            'Client ID 不可包含底線等特殊符號，目前程式已使用純英數格式（如 daemonExt1234）。',
        ]
    ],
    [
        'Q：地圖上看不到 TAG 位置',
        [
            '確認 Daemon 正在執行且日誌有 [POS] 輸出。',
            '確認 TAG 已建立且狀態為「啟用」。',
            '確認關聯 NODE 已設定座標（至少需 1 台有座標的 NODE 才能顯示）。',
            '確認 NODE 與 TAG 在同一樓層設定下。',
        ]
    ],
    [
        'Q：PHP 出現「找不到 vendor/autoload.php」',
        [
            '代表 Composer 套件尚未安裝，執行：',
            '  cd C:\\xampp\\htdocs\\aiezfind',
            '  & "C:\\xampp\\php\\php.exe" composer.phar install',
        ]
    ],
    [
        'Q：定位座標跳動不穩定',
        [
            '增加「RSSI 時間視窗」至 8~10 秒，對多筆訊號做平均。',
            '確認場域中有 2 台以上設定好座標的 NODE。',
            '調整「路徑損耗指數 n」以符合實際環境。',
        ]
    ],
];

foreach ($qaList as [$q, $answers]) {
    $section->addText($q, ['bold' => true, 'name' => '微軟正黑體', 'color' => '1e3a5f', 'size' => 11.5]);
    foreach ($answers as $ans) {
        $section->addListItem($ans, 0, ['name' => '微軟正黑體', 'size' => 10]);
    }
    $section->addTextBreak(1);
}

// ── 儲存 Word 檔 ──────────────────────────────────────────────
$outputPath = __DIR__ . '/AiezFind_操作手冊.docx';
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($outputPath);

echo "✅ Word 檔已生成！\n";
echo "   路徑：{$outputPath}\n";
