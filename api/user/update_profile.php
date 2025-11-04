<?php
/**
 * API для оновлення профілю користувача
 * Файл: /api/user/update_profile.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Перевірка авторізації
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Не авторизовано']);
    exit;
}

// Тільки POST запити
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Тільки POST запити']);
    exit;
}

$user_id = getUserId();

try {
    // Отримуємо JSON дані
    $input = json_decode(file_get_contents('php://input'), true);
    
    $full_name = trim($input['full_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $language = $input['language'] ?? 'ua';
    
    // Валідація
    if (empty($full_name) || strlen($full_name) < 2) {
        echo json_encode(['success' => false, 'message' => 'Введіть повне ім\'я (мінімум 2 символи)']);
        exit;
    }
    
    if (!empty($phone) && !preg_match('/^\+?[0-9\s\-\(\)]{10,15}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Невірний формат телефону']);
        exit;
    }
    
    if (!in_array($language, ['ua', 'en', 'ru'])) {
        $language = 'ua';
    }
    
    // Оновлюємо профіль
    DatabaseConnection::execute(
        "UPDATE users SET full_name = ?, phone = ?, language = ?, updated_at = NOW() WHERE id = ?",
        [$full_name, $phone, $language, $user_id]
    );
    
    // Оновлюємо сесію
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_language'] = $language;
    
    // Логуємо
    DatabaseConnection::insert(
        "INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, ?, ?, ?)",
        [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            'profile_update',
            'Оновлення профілю користувача',
            'low'
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Профіль успішно оновлено'
    ]);
    
} catch (Exception $e) {
    error_log('Update profile error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка оновлення профілю'
    ]);
}
?>