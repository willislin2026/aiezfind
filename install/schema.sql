-- ============================================================
-- AiezFind — BLE TAG & NODE 室內定位系統
-- 資料庫初始化 SQL
-- 執行前請先建立資料庫：CREATE DATABASE aiezfind CHARACTER SET utf8mb4;
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. floors（樓層平面圖）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `floors` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(50)  NOT NULL COMMENT '樓層名稱 如1F B1',
  `image_path` VARCHAR(255) DEFAULT NULL COMMENT '上傳PNG路徑',
  `img_width`  INT          NOT NULL DEFAULT 1000 COMMENT '平面圖像素寬',
  `img_height` INT          NOT NULL DEFAULT 800  COMMENT '平面圖像素高',
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='樓層平面圖';

-- ------------------------------------------------------------
-- 2. nodes（固定錨點）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nodes` (
  `id`        INT          NOT NULL AUTO_INCREMENT,
  `node_id`   VARCHAR(20)  NOT NULL COMMENT 'NODE MAC/ID 如1201A2',
  `name`      VARCHAR(100) DEFAULT NULL COMMENT '顯示名稱',
  `floor_id`  INT          DEFAULT NULL COMMENT '所在樓層',
  `x`         FLOAT        DEFAULT NULL COMMENT '平面圖X座標(左→右)',
  `y`         FLOAT        DEFAULT NULL COMMENT '平面圖Y座標(上→下)',
  `bat`       INT          NOT NULL DEFAULT 0 COMMENT '電量%',
  `status`    TINYINT      NOT NULL DEFAULT 0 COMMENT '0=離線 1=在線',
  `last_seen` DATETIME     DEFAULT NULL COMMENT '最後心跳時間',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_node_id` (`node_id`),
  KEY `fk_node_floor` (`floor_id`),
  CONSTRAINT `fk_node_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NODE固定錨點';

-- ------------------------------------------------------------
-- 3. tags（被追蹤裝置）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `tag_id`      VARCHAR(20)  NOT NULL COMMENT 'TAG CardNo 如1200A3',
  `name`        VARCHAR(100) DEFAULT NULL COMMENT '顯示名稱',
  `description` TEXT         DEFAULT NULL,
  `icon_color`  VARCHAR(10)  NOT NULL DEFAULT '#38bdf8' COMMENT '地圖顯示顏色',
  `status`      TINYINT      NOT NULL DEFAULT 1 COMMENT '1=啟用 0=停用',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TAG被追蹤裝置';

-- ------------------------------------------------------------
-- 4. rssi_log（原始 RSSI 記錄）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rssi_log` (
  `id`          BIGINT    NOT NULL AUTO_INCREMENT,
  `node_id`     VARCHAR(20) NOT NULL,
  `tag_id`      VARCHAR(20) NOT NULL,
  `rssi`        INT         NOT NULL COMMENT 'dBm 負整數',
  `received_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tag_time`  (`tag_id`, `received_at`),
  KEY `idx_node_time` (`node_id`, `received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RSSI原始記錄';

-- ------------------------------------------------------------
-- 5. tag_positions（最新位置，每TAG一筆）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tag_positions` (
  `tag_id`      VARCHAR(20) NOT NULL,
  `floor_id`    INT         DEFAULT NULL,
  `x`           FLOAT       DEFAULT NULL,
  `y`           FLOAT       DEFAULT NULL,
  `confidence`  FLOAT       NOT NULL DEFAULT 0,
  `node_count`  INT         NOT NULL DEFAULT 0,
  `bat`         INT         NOT NULL DEFAULT 0 COMMENT 'TAG電量%',
  `last_rssi`   INT         DEFAULT NULL COMMENT '最後RSSI dBm',
  `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TAG最新位置';

-- ------------------------------------------------------------
-- 6. position_history（歷史軌跡）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `position_history` (
  `id`          BIGINT   NOT NULL AUTO_INCREMENT,
  `tag_id`      VARCHAR(20) NOT NULL,
  `floor_id`    INT         DEFAULT NULL,
  `x`           FLOAT       DEFAULT NULL,
  `y`           FLOAT       DEFAULT NULL,
  `confidence`  FLOAT       NOT NULL DEFAULT 0,
  `recorded_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tag_time` (`tag_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='歷史軌跡';

-- ------------------------------------------------------------
-- 7. geofences（電子圍欄）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `geofences` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `floor_id`   INT          DEFAULT NULL,
  `points`     JSON         NOT NULL COMMENT '多邊形頂點 [[x1,y1],...]',
  `color`      VARCHAR(10)  NOT NULL DEFAULT '#f59e0b',
  `active`     TINYINT      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_floor` (`floor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='電子圍欄';

-- ------------------------------------------------------------
-- 8. alerts（告警紀錄）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`           BIGINT       NOT NULL AUTO_INCREMENT,
  `tag_id`       VARCHAR(20)  NOT NULL,
  `geofence_id`  INT          DEFAULT NULL,
  `alert_type`   ENUM('enter','exit','offline','low_bat') NOT NULL,
  `message`      VARCHAR(255) DEFAULT NULL,
  `is_read`      TINYINT      NOT NULL DEFAULT 0,
  `triggered_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tag`      (`tag_id`),
  KEY `idx_unread`   (`is_read`),
  KEY `idx_time`     (`triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='告警紀錄';

-- ------------------------------------------------------------
-- 9. settings（系統設定）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name`    VARCHAR(50)  NOT NULL,
  `key_value`   VARCHAR(255) NOT NULL DEFAULT '',
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系統設定';

INSERT INTO `settings` (`key_name`, `key_value`, `description`) VALUES
  ('mqtt_host',   '127.0.0.1', 'MQTT Broker IP'),
  ('mqtt_port',   '1883',      'MQTT Port'),
  ('mqtt_user',   '',          'MQTT 帳號'),
  ('mqtt_pass',   '',          'MQTT 密碼'),
  ('tx_power',    '-59',       'BLE TxPower dBm（1公尺基準）'),
  ('path_loss_n', '2.0',       '路徑損耗指數（室內建議2.0~3.5）'),
  ('rssi_window', '5',         '定位時間視窗（秒）'),
  ('hist_interval','30',       '歷史記錄間隔（秒）'),
  ('offline_sec', '60',        '超過幾秒無訊號視為離線')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;

SET FOREIGN_KEY_CHECKS = 1;
