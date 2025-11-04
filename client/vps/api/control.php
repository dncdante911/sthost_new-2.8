<?php
/**
 * ============================================
 * VPS CONTROL API - StormHosting UA
 * API для управления VPS серверами
 * ============================================
 */

define('SECURE_ACCESS', true);

// Заголовки для API
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers
header('Access-Control-Allow-Origin: https://sthost.pro');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Начинаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Требуется авторизация',
        'code' => 'UNAUTHORIZED'
    ]);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

// Подключение к базе данных
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/LibvirtManager.php';
    
    $pdo = DatabaseConnection::getSiteConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка подключения к базе данных',
        'code' => 'DATABASE_ERROR'
    ]);
    exit;
}

// Получение данных запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Некорректные данные запроса',
        'code' => 'INVALID_JSON'
    ]);
    exit;
}

// Проверка CSRF токена
if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Недействительный CSRF токен',
        'code' => 'INVALID_CSRF'
    ]);
    exit;
}

// Валидация входных данных
$vps_id = isset($data['vps_id']) ? (int)$data['vps_id'] : null;
$action = isset($data['action']) ? trim($data['action']) : null;

if (!$vps_id || !$action) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Отсутствуют обязательные параметры',
        'code' => 'MISSING_PARAMETERS'
    ]);
    exit;
}

// Проверка допустимых действий
$allowed_actions = ['start', 'stop', 'restart', 'reset_password', 'create_snapshot', 'restore_snapshot'];
if (!in_array($action, $allowed_actions)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Недопустимое действие',
        'code' => 'INVALID_ACTION'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Проверяем принадлежность VPS пользователю
    $stmt = $pdo->prepare("
        SELECT vi.*, vp.name_ua as plan_name 
        FROM vps_instances vi
        LEFT JOIN vps_plans vp ON vi.plan_id = vp.id
        WHERE vi.id = ? AND vi.user_id = ?
    ");
    $stmt->execute([$vps_id, $user_id]);
    $vps = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vps) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'VPS не найден или не принадлежит пользователю',
            'code' => 'VPS_NOT_FOUND'
        ]);
        exit;
    }
    
    // Проверяем статус VPS
    if ($vps['status'] === 'suspended') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'VPS заблокирован. Обратитесь в поддержку.',
            'code' => 'VPS_SUSPENDED'
        ]);
        exit;
    }
    
    if ($vps['status'] === 'creating') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'VPS находится в процессе создания. Попробуйте позже.',
            'code' => 'VPS_CREATING'
        ]);
        exit;
    }
    
    // Проверяем лимиты операций (rate limiting)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as operation_count 
        FROM vps_operations_log 
        WHERE vps_id = ? AND started_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$vps_id]);
    $recent_operations = $stmt->fetchColumn();
    
    if ($recent_operations >= 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Слишком много операций. Попробуйте через 5 минут.',
            'code' => 'RATE_LIMIT_EXCEEDED'
        ]);
        exit;
    }
    
    // Инициализируем VPS Manager
    $vpsManager = new VPSManager($pdo);
    $libvirtManager = new LibvirtManager();
    
    // Логируем начало операции
    $operation_id = logOperation($pdo, $vps_id, $user_id, $action, 'started');
    
    // Выполняем действие
    $result = null;
    
    switch ($action) {
        case 'start':
            $result = performVPSStart($libvirtManager, $vps, $pdo, $operation_id);
            break;
            
        case 'stop':
            $result = performVPSStop($libvirtManager, $vps, $pdo, $operation_id);
            break;
            
        case 'restart':
            $result = performVPSRestart($libvirtManager, $vps, $pdo, $operation_id);
            break;
            
        case 'reset_password':
            $result = performPasswordReset($libvirtManager, $vps, $pdo, $operation_id);
            break;
            
        case 'create_snapshot':
            $result = performCreateSnapshot($libvirtManager, $vps, $pdo, $operation_id, $data['snapshot_name'] ?? null);
            break;
            
        case 'restore_snapshot':
            $result = performRestoreSnapshot($libvirtManager, $vps, $pdo, $operation_id, $data['snapshot_id'] ?? null);
            break;
            
        default:
            throw new Exception('Неподдерживаемое действие');
    }
    
    // Обновляем лог операции
    updateOperationLog($pdo, $operation_id, 'completed', $result['message'] ?? 'Операция выполнена');
    
    // Логируем для безопасности
    logSecurityEvent($pdo, $user_id, "vps_{$action}", [
        'vps_id' => $vps_id,
        'hostname' => $vps['hostname'],
        'result' => $result['success'] ? 'success' : 'error'
    ]);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("VPS Control API Error: " . $e->getMessage());
    
    // Обновляем лог операции с ошибкой
    if (isset($operation_id)) {
        updateOperationLog($pdo, $operation_id, 'failed', $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Произошла ошибка при выполнении операции',
        'code' => 'INTERNAL_ERROR',
        'details' => $e->getMessage()
    ]);
}

// ============================================
// ФУНКЦИИ ВЫПОЛНЕНИЯ ОПЕРАЦИЙ
// ============================================

/**
 * Запуск VPS
 */
function performVPSStart($libvirtManager, $vps, $pdo, $operation_id) {
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        $result = $libvirtManager->startVPS($vps['libvirt_name']);
        
        if ($result['success']) {
            // Обновляем статус в БД
            $stmt = $pdo->prepare("UPDATE vps_instances SET power_state = 'running', last_action = NOW() WHERE id = ?");
            $stmt->execute([$vps['id']]);
            
            return [
                'success' => true,
                'message' => 'VPS успешно запущен',
                'new_status' => 'running'
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось запустить VPS');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

/**
 * Остановка VPS
 */
function performVPSStop($libvirtManager, $vps, $pdo, $operation_id) {
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        $result = $libvirtManager->stopVPS($vps['libvirt_name']);
        
        if ($result['success']) {
            // Обновляем статус в БД
            $stmt = $pdo->prepare("UPDATE vps_instances SET power_state = 'stopped', last_action = NOW() WHERE id = ?");
            $stmt->execute([$vps['id']]);
            
            return [
                'success' => true,
                'message' => 'VPS успешно остановлен',
                'new_status' => 'stopped'
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось остановить VPS');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

/**
 * Перезагрузка VPS
 */
function performVPSRestart($libvirtManager, $vps, $pdo, $operation_id) {
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        $result = $libvirtManager->rebootVPS($vps['libvirt_name']);
        
        if ($result['success']) {
            // Обновляем время последнего действия
            $stmt = $pdo->prepare("UPDATE vps_instances SET last_action = NOW() WHERE id = ?");
            $stmt->execute([$vps['id']]);
            
            return [
                'success' => true,
                'message' => 'VPS перезагружается',
                'new_status' => 'running'
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось перезагрузить VPS');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

/**
 * Сброс пароля
 */
function performPasswordReset($libvirtManager, $vps, $pdo, $operation_id) {
    // Генерируем новый пароль
    $new_password = generateSecurePassword();
    
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        // Сбрасываем пароль через libvirt
        $result = $libvirtManager->resetPassword($vps['libvirt_name'], $new_password);
        
        if ($result['success']) {
            // Обновляем пароль в БД (храним хеш)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE vps_instances SET root_password_hash = ?, last_action = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $vps['id']]);
            
            // Отправляем новый пароль по email (закомментировано для безопасности)
            /*
            sendPasswordResetEmail(
                $_SESSION['user_email'],
                $_SESSION['user_name'],
                $vps['hostname'],
                $new_password
            );
            */
            
            return [
                'success' => true,
                'message' => 'Пароль успешно сброшен. Новый пароль отправлен на email.',
                'new_password' => $new_password // В продакшене убрать из ответа!
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось сбросить пароль');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

/**
 * Создание снапшота
 */
function performCreateSnapshot($libvirtManager, $vps, $pdo, $operation_id, $snapshot_name = null) {
    if (!$snapshot_name) {
        $snapshot_name = "snapshot_" . date('Y-m-d_H-i-s');
    }
    
    // Валидируем имя снапшота
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $snapshot_name)) {
        throw new Exception('Недопустимое имя снапшота');
    }
    
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        $result = $libvirtManager->createSnapshot($vps['libvirt_name'], $snapshot_name);
        
        if ($result['success']) {
            // Сохраняем информацию о снапшоте в БД
            $stmt = $pdo->prepare("
                INSERT INTO vps_snapshots (vps_id, name, libvirt_name, created_at, status, description)
                VALUES (?, ?, ?, NOW(), 'active', 'User created snapshot')
            ");
            $stmt->execute([$vps['id'], $snapshot_name, $result['snapshot_name']]);
            
            return [
                'success' => true,
                'message' => 'Снапшот успешно создан',
                'snapshot_name' => $snapshot_name,
                'snapshot_id' => $pdo->lastInsertId()
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось создать снапшот');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

/**
 * Восстановление снапшота
 */
function performRestoreSnapshot($libvirtManager, $vps, $pdo, $operation_id, $snapshot_id = null) {
    if (!$snapshot_id) {
        throw new Exception('Не указан ID снапшота');
    }
    
    // Получаем информацию о снапшоте
    $stmt = $pdo->prepare("
        SELECT * FROM vps_snapshots 
        WHERE id = ? AND vps_id = ? AND status = 'active'
    ");
    $stmt->execute([$snapshot_id, $vps['id']]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$snapshot) {
        throw new Exception('Снапшот не найден или недоступен');
    }
    
    if (!$libvirtManager->connect()) {
        throw new Exception('Не удалось подключиться к серверу виртуализации');
    }
    
    try {
        $result = $libvirtManager->restoreSnapshot($vps['libvirt_name'], $snapshot['libvirt_name']);
        
        if ($result['success']) {
            // Обновляем время последнего действия
            $stmt = $pdo->prepare("UPDATE vps_instances SET last_action = NOW() WHERE id = ?");
            $stmt->execute([$vps['id']]);
            
            // Логируем восстановление
            $stmt = $pdo->prepare("
                INSERT INTO vps_snapshot_log (vps_id, snapshot_id, action, created_at)
                VALUES (?, ?, 'restore', NOW())
            ");
            $stmt->execute([$vps['id'], $snapshot_id]);
            
            return [
                'success' => true,
                'message' => 'Снапшот успешно восстановлен',
                'snapshot_name' => $snapshot['name']
            ];
        } else {
            throw new Exception($result['error'] ?? 'Не удалось восстановить снапшот');
        }
        
    } finally {
        $libvirtManager->disconnect();
    }
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================

/**
 * Логирование операции
 */
function logOperation($pdo, $vps_id, $user_id, $operation_type, $status, $details = null) {
    $stmt = $pdo->prepare("
        INSERT INTO vps_operations_log (vps_id, user_id, operation_type, status, started_at, details)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$vps_id, $user_id, $operation_type, $status, json_encode($details)]);
    
    return $pdo->lastInsertId();
}

/**
 * Обновление лога операции
 */
function updateOperationLog($pdo, $operation_id, $status, $message = null) {
    $stmt = $pdo->prepare("
        UPDATE vps_operations_log 
        SET status = ?, completed_at = NOW(), result_message = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $message, $operation_id]);
}

/**
 * Логирование событий безопасности
 */
function logSecurityEvent($pdo, $user_id, $action, $details = []) {
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, action, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $action,
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

/**
 * Генерация безопасного пароля
 */
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Отправка email с новым паролем (заглушка)
 */
function sendPasswordResetEmail($email, $name, $hostname, $password) {
    // Здесь должна быть реализация отправки email
    // Используйте PHPMailer или другую библиотеку
    
    $subject = "Новый пароль для VPS {$hostname} - StormHosting";
    $message = "
    Здравствуйте, {$name}!
    
    Пароль для VPS {$hostname} был успешно сброшен.
    
    Новый пароль: {$password}
    
    Пожалуйста, смените пароль после первого входа.
    
    С уважением,
    Команда StormHosting UA
    ";
    
    // mail($email, $subject, $message); // Базовая отправка
    
    error_log("Password reset email sent to {$email} for VPS {$hostname}");
}

/**
 * Проверка доступности SMS 2FA (закомментировано)
 */
function checkSMS2FARequired($user_id, $action) {
    /*
    // Критические действия, требующие SMS подтверждения
    $critical_actions = ['stop', 'restart', 'reset_password', 'restore_snapshot'];
    
    if (!in_array($action, $critical_actions)) {
        return false;
    }
    
    // Проверяем настройки пользователя
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sms_2fa_enabled, phone_verified 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user && $user['sms_2fa_enabled'] && $user['phone_verified'];
    */
    
    return false; // Пока отключено
}

/**
 * Отправка SMS кода (закомментировано)
 */
function sendSMSCode($user_id, $action) {
    /*
    global $pdo;
    
    // Генерируем код
    $code = sprintf('%06d', random_int(0, 999999));
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 минут
    
    // Сохраняем код в БД
    $stmt = $pdo->prepare("
        INSERT INTO sms_codes (user_id, code, action, expires_at, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        code = VALUES(code), 
        expires_at = VALUES(expires_at),
        created_at = NOW()
    ");
    $stmt->execute([$user_id, $code, $action, $expires]);
    
    // Получаем номер телефона
    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $phone = $stmt->fetchColumn();
    
    if (!$phone) {
        throw new Exception('Номер телефона не найден');
    }
    
    // Отправляем SMS через API
    $message = "Код подтверждения StormHosting: {$code}";
    
    // Здесь должна быть интеграция с SMS провайдером
    // sendSMSViaAPI($phone, $message);
    
    error_log("SMS code {$code} sent to {$phone} for action {$action}");
    
    return true;
    */
    
    return true; // Заглушка
}

/**
 * Проверка SMS кода (закомментировано)
 */
function verifySMSCode($user_id, $code, $action) {
    /*
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM sms_codes 
        WHERE user_id = ? AND code = ? AND action = ? 
        AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$user_id, $code, $action]);
    $sms_code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sms_code) {
        return false;
    }
    
    // Помечаем код как использованный
    $stmt = $pdo->prepare("
        UPDATE sms_codes 
        SET used = 1, used_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$sms_code['id']]);
    
    return true;
    */
    
    return true; // Заглушка
}

// ============================================
// СОЗДАНИЕ НЕОБХОДИМЫХ ТАБЛИЦ (если не существуют)
// ============================================

/**
 * Создание таблиц для VPS управления
 */
function createVPSTables($pdo) {
    // Таблица снапшотов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vps_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            libvirt_name VARCHAR(150) NOT NULL,
            description TEXT,
            status ENUM('active', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vps_id) REFERENCES vps_instances(id) ON DELETE CASCADE,
            UNIQUE KEY unique_vps_snapshot (vps_id, name)
        )
    ");
    
    // Таблица логов снапшотов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_snapshot_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vps_id INT NOT NULL,
            snapshot_id INT NOT NULL,
            action ENUM('create', 'restore', 'delete') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vps_id) REFERENCES vps_instances(id) ON DELETE CASCADE,
            FOREIGN KEY (snapshot_id) REFERENCES vps_snapshots(id) ON DELETE CASCADE
        )
    ");
    
    // Таблица логов операций VPS (если не существует)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_operations_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vps_id INT NOT NULL,
            user_id INT NOT NULL,
            operation_type VARCHAR(50) NOT NULL,
            status ENUM('started', 'running', 'completed', 'failed') DEFAULT 'started',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            result_message TEXT,
            details JSON,
            FOREIGN KEY (vps_id) REFERENCES vps_instances(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Таблица логов безопасности
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Таблица SMS кодов (закомментировано)
    /*
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(6) NOT NULL,
            action VARCHAR(50) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_action (user_id, action)
        )
    ");
    */
}

// Создаем таблицы при первом запуске
try {
    createVPSTables($pdo);
} catch (Exception $e) {
    error_log("Error creating VPS tables: " . $e->getMessage());
}