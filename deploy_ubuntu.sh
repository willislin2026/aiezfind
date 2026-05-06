#!/bin/bash
# AiezFind Ubuntu 部署自動安裝腳本
# 執行方式: sudo bash deploy_ubuntu.sh

echo "============================================="
echo "   AiezFind 系統自動部署程式開始運作"
echo "============================================="

# 檢查是否為 root 執行
if [ "$EUID" -ne 0 ]
  then echo "❌ 錯誤：請使用 sudo 權限執行此腳本 (sudo bash deploy_ubuntu.sh)"
  exit
fi

# 確認同目錄下是否有 zip 檔
if [ ! -f "aiezfind_deploy.zip" ]; then
    echo "❌ 錯誤：找不到 aiezfind_deploy.zip 檔案！請確保它與此腳本放在同一個目錄。"
    exit 1
fi

echo "✅ 1. 正在更新系統來源與安裝必備套件 (Apache, MariaDB, PHP 8.4, Composer)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y software-properties-common -qq
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y apache2 mariadb-server php8.4 libapache2-mod-php8.4 php8.4-mysql php8.4-cli php8.4-mbstring unzip curl -qq

# 若指令安裝 Composer 失敗，備用安裝方式
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "✅ 2. 正在部署網頁程式碼至 /var/www/html/aiezfind ..."
rm -rf /var/www/html/aiezfind
mkdir -p /var/www/html/aiezfind
unzip -q -o aiezfind_deploy.zip -d /var/www/html/aiezfind/
# 確保解壓縮出來如果是 aiezfind 資料夾則原封不動，如果不是則調整
if [ ! -d "/var/www/html/aiezfind/daemon" ]; then
    echo "解壓結構調整中..."
fi

chown -R www-data:www-data /var/www/html/aiezfind
chmod -R 755 /var/www/html/aiezfind

echo "✅ 3. 正在建立 MariaDB(MySQL) 資料庫與匯入 Schema..."
mysql -e "CREATE DATABASE IF NOT EXISTS aiezfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# 重設 root 密碼為 mysql_root 以符合 config.php
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'mysql_root';"
mysql -e "GRANT ALL PRIVILEGES ON aiezfind.* TO 'root'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

if [ -f "/var/www/html/aiezfind/install/schema.sql" ]; then
    mysql -uroot -pmysql_root aiezfind < /var/www/html/aiezfind/install/schema.sql
fi

# 依賴套件確保安裝 (若未打包進 zip)
echo "✅ 4. 確保 Composer 套件完整性..."
cd /var/www/html/aiezfind
sudo -u www-data composer install --no-dev --optimize-autoloader

echo "✅ 5. 正在註冊 MQTT Daemon 為背景系統服務 (Systemd)..."
cat <<EOF > /etc/systemd/system/aiezfind-daemon.service
[Unit]
Description=AiezFind MQTT Daemon (10.2.6.202)
After=network.target mariadb.service apache2.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/aiezfind
ExecStart=/usr/bin/php8.4 /var/www/html/aiezfind/daemon/mqtt_daemon1.php
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=AiezFind-MQTT
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable aiezfind-daemon.service
systemctl restart aiezfind-daemon.service

echo "✅ 6. 正在安裝 phpMyAdmin..."
# 預先設定 phpMyAdmin 安裝選項，跳過互動式問答
echo "phpmyadmin phpmyadmin/dbconfig-install boolean true" | debconf-set-selections
echo "phpmyadmin phpmyadmin/app-password-confirm password mysql_root" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/admin-pass password mysql_root" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/app-pass password mysql_root" | debconf-set-selections
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections
apt-get install -y phpmyadmin -qq

# 確保 Apache 載入 phpMyAdmin 設定
if [ ! -f /etc/apache2/conf-enabled/phpmyadmin.conf ]; then
    ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-enabled/phpmyadmin.conf 2>/dev/null || true
fi

# 允許 root 帳號使用密碼登入 phpMyAdmin
mysql -uroot -pmysql_root -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'mysql_root'; FLUSH PRIVILEGES;" 2>/dev/null || \
mysql -uroot -pmysql_root -e "UPDATE mysql.user SET plugin='mysql_native_password', authentication_string=PASSWORD('mysql_root') WHERE User='root' AND Host='localhost'; FLUSH PRIVILEGES;"

echo "✅ 7. 重啟 Apache 伺服器套用設定..."
systemctl restart apache2

IP_ADDR=$(hostname -I | awk '{print $1}')
echo "============================================="
echo "   🎉 部署已全部完成！"
echo "============================================="
echo " - AiezFind 介面請訪問： http://${IP_ADDR}/aiezfind"
echo " - phpMyAdmin  請訪問： http://${IP_ADDR}/phpmyadmin"
echo "   （登入帳號：root  ／  密碼：mysql_root）"
echo " - Daemon 守護程序已經自動在背景啟動！"
echo " - 若要查看 Daemon 狀態，請使用指令："
echo "   sudo systemctl status aiezfind-daemon.service"
echo " - 若要查看 Daemon 除錯日誌："
echo "   sudo journalctl -u aiezfind-daemon.service -f"
echo "============================================="
