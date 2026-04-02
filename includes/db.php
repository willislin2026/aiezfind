<?php
/**
 * AiezFind — PDO 資料庫單例
 */
require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}

/** 快捷函式 */
function db(): PDO {
    return DB::getInstance();
}

/** 從 settings 表讀取設定值 */
function getSetting(string $key, $default = null) {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $stmt = db()->prepare("SELECT key_value FROM settings WHERE key_name = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            $cache[$key] = ($val !== false) ? $val : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

/** 寫入 settings 表 */
function setSetting(string $key, string $value): void {
    db()->prepare("INSERT INTO settings (key_name, key_value) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE key_value=VALUES(key_value)")
       ->execute([$key, $value]);
}
