<?php
/**
 * API для зміни пароля користувача
 * Файл: /api/user/change_password.php
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
    
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    
    // Валідація
    if (empty($current_password)) {
        echo json_encode(['success' => false, 'message' => 'Введіть поточний пароль']);
        exit;
    }
    
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Новий пароль повинен містити мінімум 8 символів']);
        exit;
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Пароль повинен містити великі і малі літери та цифри']);
        exit;
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Паролі не співпадають']);
        exit;
    }
    
    // Перевіряємо поточний пароль
    $user = DatabaseConnection::fetchOne(
        "SELECT password FROM users WHERE id = ?",
        [$user_id]
    );
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Невірний поточний пароль']);
        exit;
    }
    
    // Хешуємо новий пароль
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Оновлюємо пароль
    DatabaseConnection::execute(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
        [$hashed_password, $user_id]
    );
    
    // Видаляємо всі remember tokens для безпеки
    DatabaseConnection::execute(
        "DELETE FROM remember_tokens WHERE user_id = ?",
        [$user_id]
    );
    
    // Логуємо
    DatabaseConnection::insert(
        "INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, ?, ?, ?)",
        [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            'password_change',
            'Зміна пароля користувача',
            'medium'
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Пароль успішно змінено'
    ]);
    
} catch (Exception $e) {
    error_log('Change password error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка зміни пароля'
    ]);
}
?>