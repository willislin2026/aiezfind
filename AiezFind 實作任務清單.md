# AiezFind 實作任務清單

## 第一階段：基礎設施
- [x] install/schema.sql — 資料庫初始化
- [x] includes/config.php — 全域設定
- [x] includes/db.php — PDO 單例
- [x] includes/positioning.php — WCL 算法
- [x] includes/nav.php — 側邊欄 HTML
- [x] assets/css/style.css — 深色主題 CSS
- [x] assets/uploads/floors/.gitkeep — 上傳目錄

## 第二階段：MQTT 守護程序
- [x] daemon/mqtt_daemon.php — MQTT 訂閱 + 定位計算

## 第三階段：API 端點
- [x] api/positions.php — 即時位置
- [x] api/nodes_status.php — NODE 狀態
- [x] api/history.php — 歷史軌跡
- [x] api/alerts.php — 告警資料
- [x] api/floors.php — 樓層資料
- [x] api/geofences.php — 圍欄資料
- [x] api/tags.php — TAG 管理 CRUD
- [x] api/nodes.php — NODE 管理 CRUD
- [x] api/upload_floor.php — 上傳樓層圖片

## 第四階段：核心頁面
- [x] index.php — 儀表板
- [x] map_view.php — 即時地圖

## 第五階段：管理頁面
- [x] floors.php — 樓層管理
- [x] nodes.php — NODE 管理
- [x] tags.php — TAG 管理

## 第六階段：進階功能
- [x] history.php — 歷史軌跡回放
- [x] geofence.php — 電子圍欄
- [x] alerts.php — 告警紀錄
- [x] settings.php — 系統設定
