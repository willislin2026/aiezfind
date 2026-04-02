<?php
/**
 * AiezFind — 全域設定
 * 修改此檔案以符合您的環境設定
 */

// ── 資料庫 ────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'aiezfind');
define('DB_USER',    'root');
define('DB_PASS',    'mysql_root');
define('DB_CHARSET', 'utf8mb4');

// ── MQTT（初始預設值，實際由資料庫 settings 表覆蓋）────────
define('MQTT_HOST',      '127.0.0.1');
define('MQTT_PORT',      1883);
define('MQTT_USER',      '');
define('MQTT_PASS',      '');
define('MQTT_CLIENT_ID', 'aiezfind_' . substr(md5(uniqid()), 0, 8));

// ── 路徑 ──────────────────────────────────────────────────
define('BASE_URL',    '/aiezfind');
define('UPLOAD_DIR',  dirname(__DIR__) . '/assets/uploads/floors/');
define('UPLOAD_URL',  BASE_URL . '/assets/uploads/floors/');

// ── 應用程式 ───────────────────────────────────────────────
define('APP_NAME',    'AiezFind');
define('APP_VERSION', '1.0.0');
