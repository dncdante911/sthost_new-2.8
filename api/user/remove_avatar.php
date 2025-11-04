<?php
/**
 * API для видалення аватара користувача
 * Файл: /api/user/remove_avatar.php
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
    // Отримуємо поточний аватар
    $user = DatabaseConnection::fetchOne(
        "SELECT avatar FROM users WHERE id = ?",
        [$user_id]
    );
    
    if (empty($user['avatar'])) {
        echo json_encode(['success' => false, 'message' => 'Аватар не встановлено']);
        exit;
    }
    
    // Видаляємо файл
    $avatar_path = $_SERVER['DOCUMENT_ROOT'] . $user['avatar'];
    if (file_exists($avatar_path)) {
        unlink($avatar_path);
    }
    
    // Оновлюємо БД
    DatabaseConnection::execute(
        "UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?",
        [$user_id]
    );
    
    // Логуємо
    DatabaseConnection::insert(
        "INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, ?, ?, ?)",
        [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            'avatar_remove',
            'Видалення аватара',
            'low'
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Аватар успішно видалено'
    ]);
    
} catch (Exception $e) {
    error_log('Remove avatar error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка видалення аватара'
    ]);
}
?>