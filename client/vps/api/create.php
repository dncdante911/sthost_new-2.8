<?php
define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
// Убедитесь, что путь к VPSManager правильный
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php';

header('Content-Type: application/json');

// Проверяем авторизацию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Получение данных запроса в формате JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Некорректные данные запроса (неверный JSON)']);
        exit;
    }

    // Валидируем входные данные
    $required_fields = ['plan_id', 'os_template_id', 'hostname'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Отсутствуют обязательные поля: ' . implode(', ', $missing_fields)
        ]);
        exit;
    }

    $planId = (int)$data['plan_id'];
    $osTemplateId = (int)$data['os_template_id'];
    $hostname = trim($data['hostname']);
    
    // Валидация hostname
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]$/', $hostname)) {
        echo json_encode(['success' => false, 'message' => 'Некорректное имя хоста. Разрешены только буквы, цифры и дефисы.']);
        exit;
    }

    // Проверяем лимиты пользователя (например, не больше 10 VPS)
    $stmt = $pdo->prepare("SELECT COUNT(*) as vps_count FROM vps_instances WHERE user_id = ? AND status != 'destroyed'");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() >= 10) {
        echo json_encode(['success' => false, 'message' => 'Вы достигли максимального количества VPS (10).']);
        exit;
    }

    // Проверяем существование плана
    $stmt = $pdo->prepare("SELECT * FROM vps_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$planId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Выбранный тариф не найден или неактивен.']);
        exit;
    }

    // Проверяем существование шаблона ОС
    $stmt = $pdo->prepare("SELECT * FROM vps_os_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$osTemplateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Выбранный шаблон ОС не найден или неактивен.']);
        exit;
    }

    // Создаем VPS Manager, передавая ему подключение к БД
    $vpsManager = new VPSManager($pdo);

    // Формируем конфигурацию для заказа
    $orderConfig = [
        'hostname' => $hostname,
        'os_template' => $template['name'], // Передаем системное имя шаблона
        'period' => 'monthly' // Или другой период по умолчанию
    ];

    // Используем ПРАВИЛЬНЫЙ метод для создания заказа VPS
    $result = $vpsManager->createVPSOrder($user_id, $planId, $orderConfig);

    if ($result['success']) {
        // Логируем успешное создание
        $stmt = $pdo->prepare("INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, 'vps_created', ?, 'low')");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            json_encode(['vps_id' => $result['vps_id'], 'hostname' => $hostname])
        ]);
        
        // Отправляем email пользователю (нужно настроить отправку)
        // sendVPSCreationEmail(...)
        
        // Удаляем пароль из ответа для безопасности
        unset($result['root_password']);

    } else {
        // Логируем ошибку создания
        $stmt = $pdo->prepare("INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, 'vps_create_failed', ?, 'medium')");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            json_encode(['hostname' => $hostname, 'error' => $result['error'] ?? 'Unknown error'])
        ]);
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log('VPS Creation API Error: ' . $e->getMessage());

    // Логируем критическую ошибку
    $stmt = $pdo->prepare("INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, 'vps_create_error', ?, 'high')");
    $stmt->execute([
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $user_id,
        $e->getMessage()
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Произошла внутренняя ошибка сервера. Пожалуйста, свяжитесь с поддержкой.'
    ]);
}

/**
 * Отправка email с данными созданного VPS (заглушка)
 */
function sendVPSCreationEmail($email, $userName, $hostname, $ipAddress, $rootPassword) {
    // В реальном проекте здесь будет отправка email через SMTP
    // Для демонстрации просто логируем
    $subject = "Ваш VPS {$hostname} успешно создан";
    $message = "Здравствуйте, {$userName}!\n\nВаш VPS был успешно создан.\n\nДетали:\n- Имя хоста: {$hostname}\n- IP Адрес: {$ipAddress}\n- Пароль root: {$rootPassword}\n\nС уважением,\nКоманда StormHosting UA";
    
    error_log("Email о создании VPS отправлен на {$email}: {$subject}");
    return true;
}
?>