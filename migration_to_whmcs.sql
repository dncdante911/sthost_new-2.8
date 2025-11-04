-- ============================================
-- МИГРАЦИЯ С FOSSBILLING НА WHMCS
-- База данных: sthostsitedb
-- Совместимость: MariaDB 10.11+
-- ============================================

USE sthostsitedb;

-- ============================================
-- 1. ТАБЛИЦА USERS
-- ============================================

-- Добавляем whmcs_client_id (безопасно, игнорирует если уже есть)
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN whmcs_client_id INT(11) DEFAULT NULL AFTER email',
        'SELECT "Column whmcs_client_id already exists in users" AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'whmcs_client_id'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индекс для whmcs_client_id
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE users ADD INDEX idx_whmcs_client_id (whmcs_client_id)',
        'SELECT "Index idx_whmcs_client_id already exists" AS message'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND INDEX_NAME = 'idx_whmcs_client_id'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Копируем данные из fossbilling_client_id в whmcs_client_id
UPDATE users
SET whmcs_client_id = fossbilling_client_id
WHERE fossbilling_client_id IS NOT NULL
    AND whmcs_client_id IS NULL
    AND EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'users'
            AND COLUMN_NAME = 'fossbilling_client_id'
    );

-- ============================================
-- 2. ТАБЛИЦА VPS_INSTANCES
-- ============================================

-- Проверяем существует ли таблица
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_instances'
);

-- Если таблица не существует - создаем с нуля
SET @query = IF(
    @table_exists = 0,
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
        KEY idx_user_id (user_id),
        KEY idx_plan_id (plan_id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
    'SELECT "Table vps_instances already exists" AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Если таблица существует, обрабатываем поля
-- Сначала проверяем есть ли whmcs_service_id
SET @has_new_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_instances'
        AND COLUMN_NAME = 'whmcs_service_id'
);

-- Проверяем есть ли старое поле fossbilling_order_id
SET @has_old_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_instances'
        AND COLUMN_NAME = 'fossbilling_order_id'
);

-- Если есть старое поле - переименовываем его
SET @query = IF(
    @table_exists > 0 AND @has_new_column = 0 AND @has_old_column > 0,
    'ALTER TABLE vps_instances CHANGE COLUMN fossbilling_order_id whmcs_service_id INT(11) DEFAULT NULL',
    IF(
        @table_exists > 0 AND @has_new_column = 0 AND @has_old_column = 0,
        'ALTER TABLE vps_instances ADD COLUMN whmcs_service_id INT(11) DEFAULT NULL AFTER plan_id',
        'SELECT "Column whmcs_service_id is OK in vps_instances" AS message'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. ТАБЛИЦА VPS_PLANS
-- ============================================

-- Проверяем есть ли whmcs_product_id
SET @has_new_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_plans'
        AND COLUMN_NAME = 'whmcs_product_id'
);

-- Проверяем есть ли старое поле fossbilling_product_id
SET @has_old_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_plans'
        AND COLUMN_NAME = 'fossbilling_product_id'
);

-- Если есть старое поле - переименовываем его
SET @query = IF(
    @has_new_column = 0 AND @has_old_column > 0,
    'ALTER TABLE vps_plans CHANGE COLUMN fossbilling_product_id whmcs_product_id INT(11) DEFAULT NULL',
    IF(
        @has_new_column = 0 AND @has_old_column = 0,
        'ALTER TABLE vps_plans ADD COLUMN whmcs_product_id INT(11) DEFAULT NULL AFTER id',
        'SELECT "Column whmcs_product_id already exists in vps_plans" AS message'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индекс для whmcs_product_id
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE vps_plans ADD INDEX idx_whmcs_product_id (whmcs_product_id)',
        'SELECT "Index idx_whmcs_product_id already exists" AS message'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'vps_plans'
        AND INDEX_NAME = 'idx_whmcs_product_id'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- ПРОВЕРКА РЕЗУЛЬТАТОВ
-- ============================================

SELECT '✅ МИГРАЦИЯ ЗАВЕРШЕНА!' AS Status;
SELECT '';

SELECT '📋 ТАБЛИЦА USERS - проверка полей:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME IN ('fossbilling_client_id', 'whmcs_client_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '📋 ТАБЛИЦА VPS_INSTANCES - проверка полей:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'vps_instances'
    AND COLUMN_NAME IN ('fossbilling_order_id', 'whmcs_service_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '📋 ТАБЛИЦА VPS_PLANS - проверка полей:' AS Info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'vps_plans'
    AND COLUMN_NAME IN ('fossbilling_product_id', 'whmcs_product_id')
ORDER BY ORDINAL_POSITION;

SELECT '';
SELECT '✅ ГОТОВО! Все таблицы обновлены для работы с WHMCS' AS Result;
SELECT 'Теперь не забудь настроить API credentials в config.php' AS NextStep;
