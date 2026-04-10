# Ubuntu (10.2.3.69) 自動化部署與轉移計畫

為將目前的 AiezFind 專案順利搬移至遠端的 Ubuntu 機器，我會將專案打包並替您準備好一鍵安裝的腳本。如此一來，Ubuntu 機器就能自動架設好伺服器環境並將我們的 MQTT Daemon 註冊為背景常駐的系統服務 (Systemd)。

## Proposed Changes

### 1. 打包本機專案與資料庫 Schema
- **[NEW] `C:\xampp\htdocs\aiezfind_deploy.zip`**
  將 `aiezfind` 目錄（包含我們剛剛寫好的 `mqtt_daemon1.php`）打包成一個 zip 檔，方便傳輸。

### 2. 建立 Ubuntu 自動化環境建置腳本
- **[NEW] `C:\xampp\htdocs\deploy_ubuntu.sh`**
  我會寫一支 Shell Script，內容包含：
  1. 自動 `apt-get install` 安裝 Apache2、MySQL Server、PHP 8.1 及其必要模組。
  2. 自動將解壓縮後的專案放到 `/var/www/html/aiezfind` 並設定權限 (`www-data`)。
  3. 自動建立 `aiezfind` 資料庫，並匯入 `schema.sql`，設定正確的帳號密碼。
  4. 自動將 `daemon/mqtt_daemon1.php` 註冊為 **Systemd 系統服務**（名為 `aiezfind-daemon.service`）。這樣若是主機重開機，MQTT 接收程式也會自動在背景啟動！

## User Review Required

> [!IMPORTANT]
> 因為 SSH 直接連線通常會卡在「輸入密碼」的提示中，目前的 Windows 環境我們缺少自動輸入 SSH 密碼的工具（例如 `sshpass`）。

**為了順利完成部署，請問您傾向使用哪一種方式？**

- **【方式 A：您手動執行傳輸】(最穩妥推薦👍)**
  我將上述的 `.zip` 檔跟 `.sh` 檔準備好在您的本機。然後教您使用目前的 PowerShell 打一行指令 ( `scp` ) 把檔案傳過去，並提供在 Ubuntu 上執行的步驟給您。
- **【方式 B：金鑰連線全自動】**
  若您這台 Windows 和 `10.2.3.69` 之間「已經設定好了免密碼 SSH Key 連線」，您可以告訴我（並提供 Ubuntu 的登入使用者名稱），我就可以直接從背景下指令自動幫您整套傳過去並安裝完畢！

請告訴我您的選擇，或者直接核准計畫（若未說明我將採取方式 A 為您做準備）。
