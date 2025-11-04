-- ============================================
-- МИГРАЦИЯ С FOSSBILLING НА WHMCS
-- База данных: sthostsitedb
-- Дата: 2025
-- ============================================

USE sthostsitedb;

-- ============================================
-- 1. ТАБЛИЦА USERS
-- ============================================

-- Добавляем поле whmcs_client_id если его нет
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'whmcs_client_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "Column whmcs_client_id already exists in users" AS Info;',
  'ALTER TABLE users ADD COLUMN whmcs_client_id INT(11) DEFAULT NULL AFTER email;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Добавляем индекс для whmcs_client_id если его нет
SET @indexname = 'idx_whmcs_client_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  'SELECT "Index idx_whmcs_client_id already exists" AS Info;',
  'ALTER TABLE users ADD INDEX idx_whmcs_client_id (whmcs_client_id);'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Копируем данные из fossbilling_client_id в whmcs_client_id (если fossbilling_client_id существует)
SET @columnname_old = 'fossbilling_client_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname_old)
  ) > 0,
  'UPDATE users SET whmcs_client_id = fossbilling_client_id WHERE fossbilling_client_id IS NOT NULL AND whmcs_client_id IS NULL;',
  'SELECT "Column fossbilling_client_id does not exist, skipping data copy" AS Info;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- ============================================
-- 2. ТАБЛИЦА VPS_INSTANCES
-- ============================================

SET @tablename = 'vps_instances';

-- Проверяем существует ли таблица vps_instances
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename);

-- Если таблица не существует - создаем с правильной структурой
SET @preparedStatement = (SELECT IF(
  @table_exists > 0,
  'SELECT "Table vps_instances exists" AS Info;',
  'CREATE TABLE vps_instances (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    plan_id INT(11) NOT NULL,
    whmcs_service_id INT(11) DEFAULT NULL,
    hostname VARCHAR(255) NOT NULL,
    domain_name VARCHAR(255) DEFAULT NULL,
    libvirt_name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    ip_gateway VARCHAR(45) DEFAULT "192.168.0.10",
    ip_netmask VARCHAR(45) DEFAULT "255.255.255.0",
    dns_servers LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(dns_servers)),
    os_template VARCHAR(100) DEFAULT NULL,
    root_password VARCHAR(255) DEFAULT NULL,
    vnc_password VARCHAR(255) DEFAULT NULL,
    vnc_port INT(11) DEFAULT NULL,
    status ENUM("pending","creating","active","stopped","suspended","terminated","error") DEFAULT "pending",
    cpu_cores INT(11) NOT NULL,
    ram_mb INT(11) NOT NULL,
    disk_gb INT(11) NOT NULL,
    bandwidth_gb INT(11) NOT NULL,
    bandwidth_used BIGINT(20) DEFAULT 0,
    last_bandwidth_reset TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
    suspend_reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    expires_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_libvirt_name (libvirt_name),
    UNIQUE KEY unique_hostname (hostname),
    UNIQUE KEY unique_ip (ip_address),
    KEY idx_user_id (user_id),
    KEY idx_plan_id (plan_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Только если таблица существует, проверяем поля
SET @preparedStatement = (SELECT IF(
  @table_exists > 0,
  CONCAT(
    'SET @columnname = "whmcs_service_id";',
    'SET @columnname_old = "fossbilling_order_id";',
    'SET @has_old = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = "', @dbname, '" AND TABLE_NAME = "', @tablename, '" AND COLUMN_NAME = @columnname_old);',
    'SET @has_new = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = "', @dbname, '" AND TABLE_NAME = "', @tablename, '" AND COLUMN_NAME = @columnname);',
    'SET @sql = (SELECT CASE ',
    '  WHEN @has_new > 0 THEN "SELECT ''Column whmcs_service_id already exists'' AS Info"',
    '  WHEN @has_old > 0 THEN "ALTER TABLE vps_instances CHANGE COLUMN fossbilling_order_id whmcs_service_id INT(11) DEFAULT NULL"',
    '  ELSE "ALTER TABLE vps_instances ADD COLUMN whmcs_service_id INT(11) DEFAULT NULL AFTER plan_id"',
    '  END);',
    'PREPARE stmt FROM @sql;',
    'EXECUTE stmt;',
    'DEALLOCATE PREPARE stmt;'
  ),
  'SELECT "Table vps_instances does not exist, already created with correct structure" AS Info;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- ============================================
-- 3. ТАБЛИЦА VPS_PLANS
-- ============================================

SET @tablename = 'vps_plans';

-- Добавляем поле whmcs_product_id если его нет
SET @columnname = 'whmcs_product_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT "Column whmcs_product_id already exists in vps_plans" AS Info;',
  'ALTER TABLE vps_plans ADD COLUMN whmcs_product_id INT(11) DEFAULT NULL AFTER id;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Если было поле fossbilling_product_id - переименовываем
SET @columnname_old = 'fossbilling_product_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname_old)
  ) > 0,
  'ALTER TABLE vps_plans CHANGE COLUMN fossbilling_product_id whmcs_product_id INT(11) DEFAULT NULL;',
  'SELECT "Column fossbilling_product_id does not exist in vps_plans" AS Info;'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Добавляем индекс для whmcs_product_id если его нет
SET @indexname = 'idx_whmcs_product_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  'SELECT "Index idx_whmcs_product_id already exists" AS Info;',
  'ALTER TABLE vps_plans ADD INDEX idx_whmcs_product_id (whmcs_product_id);'
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- ============================================
-- ПРОВЕРКА РЕЗУЛЬТАТОВ
-- ============================================

SELECT '✅ МИГРАЦИЯ ЗАВЕРШЕНА!' AS Status;
SELECT '';
SELECT '📋 ПРОВЕРКА ТАБЛИЦЫ USERS:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME IN ('fossbilling_client_id', 'whmcs_client_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '📋 ПРОВЕРКА ТАБЛИЦЫ VPS_INSTANCES:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'vps_instances'
  AND COLUMN_NAME IN ('fossbilling_order_id', 'whmcs_service_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '📋 ПРОВЕРКА ТАБЛИЦЫ VPS_PLANS:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'vps_plans'
  AND COLUMN_NAME IN ('fossbilling_product_id', 'whmcs_product_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '✅ ГОТОВО! Все таблицы обновлены для работы с WHMCS' AS Result;
