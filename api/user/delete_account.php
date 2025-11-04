<?php
/**
 * API для видалення акаунту користувача
 * Файл: /api/user/delete_account.php
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
$user_email = getUserEmail();

try {
    // Отримуємо дані користувача
    $user = DatabaseConnection::fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$user_id]
    );
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Користувача не знайдено']);
        exit;
    }
    
    // Видаляємо аватар якщо є
    if (!empty($user['avatar'])) {
        $avatar_path = $_SERVER['DOCUMENT_ROOT'] . $user['avatar'];
        if (file_exists($avatar_path)) {
            unlink($avatar_path);
        }
    }
    
    // Логуємо перед видаленням
    DatabaseConnection::insert(
        "INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, ?, ?, ?)",
        [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            'account_delete',
            "Видалення акаунту: $user_email",
            'critical'
        ]
    );
    
    // Видаляємо пов'язані дані
    // Remember tokens
    DatabaseConnection::execute(
        "DELETE FROM remember_tokens WHERE user_id = ?",
        [$user_id]
    );
    
    // Password resets
    DatabaseConnection::execute(
        "DELETE FROM password_resets WHERE email = ?",
        [$user_email]
    );
    
    // Login attempts
    DatabaseConnection::execute(
        "DELETE FROM login_attempts WHERE email = ?",
        [$user_email]
    );
    
    // VPS instances (якщо є таблиця)
    try {
        DatabaseConnection::execute(
            "DELETE FROM vps_instances WHERE user_id = ?",
            [$user_id]
        );
    } catch (Exception $e) {
        // Таблиця може не існувати
    }
    
    // VPS operations log (якщо є таблиця)
    try {
        DatabaseConnection::execute(
            "DELETE FROM vps_operations_log WHERE user_id = ?",
            [$user_id]
        );
    } catch (Exception $e) {
        // Таблиця може не існувати
    }
    
    // Видаляємо користувача
    DatabaseConnection::execute(
        "DELETE FROM users WHERE id = ?",
        [$user_id]
    );
    
    // Очищаємо сесію
    session_unset();
    session_destroy();
    
    // Видаляємо cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Акаунт успішно видалено'
    ]);
    
} catch (Exception $e) {
    error_log('Delete account error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка видалення акаунту'
    ]);
}

/**
 * ПРИМІТКА:
 * Для повного видалення акаунту, також потрібно:
 * 1. Видалити послуги в FossBilling через їх API
 * 2. Видалити домени в ISPmanager
 * 3. Видалити VPS в Libvirt
 * 
 * Це можна зробити додатково через окремі API запити
 * або додати в цей файл відповідні виклики
 */
?>