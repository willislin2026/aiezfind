# AiezFind — BLE 室內定位系統操作手冊

> **版本：** v1.0.0 ／ **適用環境：** Windows + XAMPP (PHP 8.1 + MariaDB)

---

## 目錄

1. [系統架構總覽](#1-系統架構總覽)
2. [環境需求與安裝](#2-環境需求與安裝)
3. [初次啟動流程](#3-初次啟動流程)
4. [MQTT Daemon 操作說明](#4-mqtt-daemon-操作說明)
5. [Web 介面功能說明](#5-web-介面功能說明)
6. [定位參數調校](#6-定位參數調校)
7. [常見問題排除](#7-常見問題排除)

---

## 1. 系統架構總覽

```
BLE TAG 設備
    │  (BLE 廣播)
    ▼
NODE 固定錨點  ──MQTT Topic: eztag/{CardNo}──▶  MQTT Broker (10.2.6.202)
NODE 心跳      ──MQTT Topic: eznode/{NodeID}──▶  MQTT Broker
                                                       │
                                          PHP MQTT Daemon 訂閱
                                                       │
                                               寫入 MySQL 資料庫
                                          (rssi_log / tag_positions)
                                                       │
                                          WCL 加權質心定位算法
                                                       │
                                          Web API (JSON) ◀── 前端 AJAX 2秒輪詢
                                                       │
                                          Leaflet.js 平面圖即時顯示
```

### 訊息格式說明

**NODE 心跳 (Topic: `eznode/{NodeID}`)：**
```json
{"ID":"1201A2","BAT":"64","ST":"00","GP":"0000","CNT":"019FF4","CS":"AD"}
```

**TAG 偵測 (Topic: `eztag/{CardNo}`)：**
```json
{"ID":"1201A2","CardNo":"1200A3","BAT":"4A","ST":"00","GP":"0000","RSSI":"C1","CNT":"002D04","CS":"9C"}
```

| 欄位 | 說明 |
|------|------|
| `ID` | 偵測到此 TAG 的 NODE ID |
| `CardNo` | TAG 本身卡號 |
| `BAT` | 電量 (HEX, 例如 `64` = 100%) |
| `RSSI` | 訊號強度 (HEX 有符號 8-bit, 例如 `C1` = -63 dBm) |

---

## 2. 環境需求與安裝

### 系統需求

| 項目 | 規格 |
|------|------|
| 作業系統 | Windows 10/11 |
| Web Server | XAMPP (Apache + PHP 8.1 + MariaDB) |
| PHP | 8.1（含 PDO、PDO_MySQL） |
| MQTT Broker | Mosquitto 或任何相容的 Broker |

### 安裝步驟

**1. 確認 XAMPP 服務啟動**
- 開啟 XAMPP Control Panel
- 確認 **Apache** 與 **MySQL** 兩項服務都顯示綠燈

**2. 建立資料庫**

開啟 PowerShell，執行以下指令建立 `aiezfind` 資料庫並匯入結構：
```powershell
# 建立資料庫
& "C:\xampp\mysql\bin\mysql.exe" -u root -pmysql_root -e "CREATE DATABASE IF NOT EXISTS aiezfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 匯入 Schema
& "C:\xampp\mysql\bin\mysql.exe" -u root -pmysql_root --default-character-set=utf8mb4 aiezfind -e "source C:/xampp/htdocs/aiezfind/install/schema.sql"
```

**3. 安裝 PHP MQTT 套件**

此系統使用純 PHP 的 `php-mqtt/client` 套件（無需安裝 DLL 擴充）：
```powershell
cd C:\xampp\htdocs\aiezfind

# 下載 Composer 並安裝依賴套件（只需執行一次）
Invoke-WebRequest -Uri https://getcomposer.org/download/latest-stable/composer.phar -OutFile composer.phar
& "C:\xampp\php\php.exe" composer.phar require php-mqtt/client
```

**4. 確認設定檔**

查看 `includes/config.php`，確認以下設定正確：
```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'aiezfind');
define('DB_USER',    'root');
define('DB_PASS',    'mysql_root');

define('MQTT_HOST',  '10.2.6.202'); // 您的 MQTT Broker IP
define('MQTT_PORT',  1883);
```

---

## 3. 初次啟動流程

首次使用，請按以下順序完成設定：

```
啟動 Daemon → 新增樓層 → 上傳平面圖 → 設定 NODE 座標 → 建立 TAG → 開啟地圖
```

### 第一步：新增樓層並上傳平面圖

1. 開啟瀏覽器前往 `http://localhost/aiezfind/floors.php`
2. 點擊右上角 **「新增樓層」**
3. 輸入名稱（例如：`1F 辦公區`），設定排序後儲存
4. 在表格中找到剛新增的樓層，點擊 **「上傳圖片」**
5. 選擇平面圖（PNG / JPG，建議解析度 1000px 以上）
6. 系統自動套用圖片的像素寬高

> [!IMPORTANT]
> 地圖上的所有座標（NODE / TAG 位置）皆以「像素」為單位，與圖片實際尺寸對應，請確保上傳的圖片解析度符合您的需求。

### 第二步：設定 NODE 座標

1. 開啟 `http://localhost/aiezfind/nodes.php`
2. 啟動 Daemon 後，系統會自動將硬體傳來的 NODE 加入清單
3. 找到對應的 Node ID，點擊 **「設定座標」**
4. 選擇該 NODE 所在的樓層
5. **直接在地圖預覽圖上點擊** 設定其物理位置（支援拖曳微調）
6. 點擊「儲存」

> [!NOTE]
> NODE 的座標精準度直接決定定位精度。建議在實際場所丈量 NODE 的距牆位置，對應至平面圖像素後再標記。

### 第三步：建立 TAG 資料

1. 開啟 `http://localhost/aiezfind/tags.php`
2. 點擊右上角 **「新增 TAG」**
3. 輸入 **TAG CardNo**（即設備卡號，例如：`1200A3`）
4. 輸入顯示名稱（人員或物品名稱）
5. 選擇專屬顏色方便地圖辨識
6. 點擊儲存

> [!TIP]
> 若 TAG 已在場域中運作，Daemon 接收到訊號後會自動建立基本資料，您只需再進來編輯名稱與顏色即可。

---

## 4. MQTT Daemon 操作說明

Daemon 是本系統的核心，負責從 MQTT Broker 接收資料，並計算 TAG 位置後儲存至資料庫。

### 啟動指令

**正式版（串接 `10.2.6.202` 實機，含寫入資料庫）：**
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\daemon\mqtt_daemon1.php
```

**測試版（串接 localhost，用於本地開發）：**
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\daemon\mqtt_daemon.php
```

### 正常運作時的輸出樣式

```
[2026-04-07 12:00:00] [INFO] === AiezFind MQTT Daemon 啟動 ===
[2026-04-07 12:00:00] [INFO] MQTT Broker: 10.2.6.202:1883
[2026-04-07 12:00:00] [INFO] MQTT 連線成功
[2026-04-07 12:00:00] [INFO] 已訂閱 eznode/# 與 eztag/#
[2026-04-07 12:00:02] [TAG]  120094 ← NODE 120171  RSSI=-65dBm  BAT=78%
[2026-04-07 12:00:02] [POS]  120094 → floor=1 x=345.0 y=210.3 conf=89% (n=2)
```

| 日誌標籤 | 說明 |
|----------|------|
| `[INFO]` | 系統資訊（連線、訂閱） |
| `[NODE]` | 收到 NODE 心跳，更新電量與在線狀態 |
| `[TAG]` | 偵測到 TAG，記錄 RSSI |
| `[POS]` | 定位計算完成，輸出座標 |
| `[ALERT]` | 觸發告警（低電量、圍欄事件） |
| `[WARN]` | 警告（封包格式異常等） |
| `[ERROR]` | 錯誤（資料庫連線失敗等） |

### 停止 Daemon

在執行 Daemon 的視窗中，按下 `Ctrl + C` 即可中斷。

### 測試封包收發工具

**純接收監聽（確認 Broker 是否有資料，不寫入資料庫）：**
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_recv.php
```

**手動發送模擬封包（開發測試用）：**
```powershell
# 同時發送 NODE 心跳 + TAG 訊號
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_pub.php

# 只發送 TAG，帶入不同 RSSI 測試距離
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_pub.php tag C1   # -63 dBm (約 1m)
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_pub.php tag D8   # -40 dBm (很近)
C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_pub.php tag B0   # -80 dBm (很遠)
```

---

## 5. Web 介面功能說明

系統網址：`http://localhost/aiezfind/`

### 📊 儀表板 (`index.php`)

| 統計卡片 | 說明 |
|----------|------|
| 在線 TAG | 近 60 秒內有收到訊號的 TAG 數量 |
| 在線 NODE | 目前狀態為在線的 NODE 數量 |
| 未讀告警 | 尚未處理的告警筆數 |
| 低電量 TAG | 電量低於 20% 的設備數量 |

下方雙欄顯示「最近告警紀錄」與「TAG 即時狀態列表」，每 10 秒自動更新。

---

### 🗺️ 即時地圖 (`map_view.php`)

- **左側樓層切換**：點擊樓層按鈕切換不同樓層的平面圖
- **TAG 標記**：彩色圓點，顏色為您在 TAG 管理中設定的顏色；離線則轉為灰色
- **NODE 標記**：紫色圓點，代表固定接收器的位置
- **點擊標記**：可展開 Popup 查看設備的電量、RSSI、信心度等詳細資訊
- **左側 TAG 清單**：點擊名稱可讓地圖自動移動至該 TAG 的位置
- 地圖每 **2 秒** 自動刷新

---

### 📂 TAG 管理 (`tags.php`)

| 欄位 | 說明 |
|------|------|
| TAG ID | 卡號（硬體 CardNo，一旦建立不可修改） |
| 名稱 | 顯示用名稱（人員 / 物品） |
| 位置 | 最後偵測到的樓層與座標 |
| 電量 | 以進度條顯示，低於 20% 亮紅色 |
| RSSI | 最後收到的訊號強度（dBm） |
| 狀態 | 在線 / 離線 / 停用 |

**操作：**
- **新增 TAG**：點擊右上角「新增 TAG」按鈕
- **編輯（✏️）**：修改名稱、顏色、備註、狀態（啟用/停用）
- **刪除（🗑️）**：確認後永久刪除（歷史記錄也會一並消失）

---

### 📡 NODE 管理 (`nodes.php`)

Daemon 啟動後，有傳送心跳封包的 NODE 會自動出現在列表中。

**設定座標：**
1. 點擊「設定座標」按鈕
2. 選擇該 NODE 所在的樓層
3. 在下方地圖上點擊對應位置（或直接在 X / Y 欄位輸入像素值）
4. 標記支援拖曳微調
5. 點擊「儲存」

> [!IMPORTANT]
> **每一台 NODE 都必須設定座標**，系統才能進行定位三角計算。未設定座標的 NODE 會被跳過。

---

### 🏢 樓層管理 (`floors.php`)

| 功能 | 說明 |
|------|------|
| 新增樓層 | 輸入名稱與排序 |
| 上傳圖片 | 支援 PNG / JPG / GIF / WEBP，自動套用圖片解析度 |
| 刪除樓層 | 注意：刪除後，該樓層的 NODE 座標設定也會清除 |

---

### ⏱️ 歷史軌跡 (`history.php`)

1. 選擇目標 TAG（下拉選單）
2. 選擇查詢日期
3. 點擊「查詢」
4. 地圖上以虛線連出當天的移動路徑
5. 使用右上角的播放控制軸回放移動過程

**播放控制說明：**

| 控制項 | 說明 |
|--------|------|
| ▶️ 播放 | 開始自動播放軌跡動畫 |
| ⏸ 暫停 | 暫停（再次點擊繼續） |
| ⏮ / ⏭ | 逐筆後退 / 前進 |
| 時間軸拖曳 | 直接跳至指定時間點 |
| 速度選單 | 0.5x / 1x / 2x / 4x |

---

### 🛡️ 電子圍欄 (`geofence.php`)

**建立圍欄：**
1. 先在左側選單選擇樓層
2. 點擊「開始繪製」
3. 在地圖上依序點擊多個頂點（**至少 3 點**）
4. 點擊「完成」，輸入圍欄名稱與顏色後儲存

**告警觸發條件：**
- TAG **進入**圍欄區域 → 產生 `進入圍欄` 告警
- TAG **離開**圍欄區域 → 產生 `離開圍欄` 告警
- 同一事件 60 秒內不重複記錄

---

### 🚨 告警紀錄 (`alerts.php`)

**篩選功能：**
- 依 TAG 篩選（下拉選單）
- 依告警類型篩選：`進入圍欄` / `離開圍欄` / `離線` / `低電量`

**處理操作：**
- 點擊個別「標記已讀」
- 或點擊右上角「全部標為已讀」統一清除

---

### ⚙️ 系統設定 (`settings.php`)

設定匯存於資料庫，**無需重新啟動**即可讓 Daemon 下次讀取生效（但需重啟 Daemon 才會讀取新的 MQTT 主機設定）。

---

## 6. 定位參數調校

> 路在 `設定 → 系統設定` 頁面調整，儲存後下次 Daemon 接收時即生效。

| 參數 | 預設值 | 說明 |
|------|--------|------|
| MQTT Host | `127.0.0.1` | MQTT Broker IP |
| MQTT Port | `1883` | MQTT 連接埠 |
| BLE TxPower | `-59` dBm | TAG 在 1 公尺時的基準 RSSI，依硬體調整 |
| 路徑損耗指數 n | `2.0` | 空曠空間=2.0；多障礙物室內建議 2.5~3.5 |
| RSSI 時間視窗 | `5` 秒 | 參與定位計算的最近幾秒資料 |
| 歷史記錄間隔 | `30` 秒 | 每隔幾秒寫入一筆歷史座標 |
| 離線判斷秒數 | `60` 秒 | 超過幾秒無訊號則標記為離線 |

**調校建議：**
- 若 TAG 位置跳動過大 → 調高「時間視窗」（如 10 秒）
- 若 TAG 位置反應太慢 → 調低「時間視窗」（如 3 秒）
- 若距離算得比實際遠 → 調高「TxPower」（如 -55）
- 若距離算得比實際近 → 調低「TxPower」（如 -65）
- 廠區隔間多、金屬遮蔽多 → 調高 n 值（如 3.0）

---

## 7. 常見問題排除

### ❌ 啟動 Daemon 後沒有收到任何訊息

1. 確認 MQTT Broker (`10.2.6.202`) 是否可連線：
   ```powershell
   Test-NetConnection -ComputerName 10.2.6.202 -Port 1883
   ```
2. 用純測試腳本監聽確認封包是否存在：
   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\aiezfind\test_recv.php
   ```
3. 確認訂閱的 Topic (`eznode/#` / `eztag/#`) 與硬體設定一致

---

### ❌ 地圖上看不到 TAG

1. 確認 Daemon 正在執行，且日誌有 `[POS]` 輸出
2. 確認 TAG 已在「TAG 管理」中建立且狀態為「啟用」
3. 確認相關的 **NODE 已設定座標**（至少需要 1 個 NODE 有座標才會顯示單點位置）
4. 確認 NODE 與 TAG 在同一樓層

---

### ❌ Daemon 出現 `unauthorized` 錯誤

此問題通常是 MQTT Broker 設有帳號密碼驗證。
請在 `daemon/mqtt_daemon1.php` 中確認以下設定正確：
```php
$mqttUser = 'btciot';
$mqttPass = 'tiger?iot';
```
另外請確認 Client ID 不含底線 (`_`) 等特殊符號，目前程式已使用純英數格式（如 `daemonExt1234`）。

---

### ❌ PHP 報錯「找不到 vendor/autoload.php」

代表 Composer 套件尚未安裝，執行以下指令：
```powershell
cd C:\xampp\htdocs\aiezfind
& "C:\xampp\php\php.exe" composer.phar install
```

---

*文件最後更新：2026-04-08*
