<?php
/**
 * Перевірка авторизації користувача
 * Файл: /includes/auth/check_auth.php
 */

// Захист від прямого доступу
if (!defined('SECURE_ACCESS')) {
    die('Прямий доступ заборонено');
}

// Початок сесії якщо ще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Перевірка чи користувач авторизований
 */
function isLoggedIn() {
    return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true && isset($_SESSION['user_id']);
}

/**
 * Отримання ID користувача
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Отримання email користувача
 */
function getUserEmail() {
    return $_SESSION['user_email'] ?? '';
}

/**
 * Отримання імені користувача
 */
function getUserName() {
    return $_SESSION['user_name'] ?? '';
}

/**
 * Получение WHMCS client ID
 */
function getWHMCSClientId() {
    return $_SESSION['whmcs_client_id'] ?? null;
}

/**
 * Устаревшая функция для обратной совместимости
 * @deprecated Используйте getWHMCSClientId()
 */
function getFossBillingClientId() {
    return getWHMCSClientId();
}

/**
 * Редірект на сторінку логіну якщо не авторизований
 */
function requireLogin($redirect_after = null) {
    if (!isLoggedIn()) {
        $redirect_url = '/';
        if ($redirect_after) {
            $redirect_url .= '?redirect=' . urlencode($redirect_after);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Перевірка remember token для автоматичного входу
 */
function checkRememberToken() {
    if (isLoggedIn()) {
        return true;
    }
    
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
        
        $token = $_COOKIE['remember_token'];
        $hashed_token = hash('sha256', $token);
        
        // Перевіряємо токен у БД
        $result = DatabaseConnection::fetchOne(
            "SELECT rt.user_id, u.email, u.full_name, u.language, u.whmcs_client_id, u.is_active
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.token = ? AND rt.expires_at > NOW()",
            [$hashed_token]
        );

        if ($result && $result['is_active']) {
            // Відновлюємо сесію
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['user_email'] = $result['email'];
            $_SESSION['user_name'] = $result['full_name'];
            $_SESSION['user_language'] = $result['language'];
            $_SESSION['is_logged_in'] = true;
            $_SESSION['whmcs_client_id'] = $result['whmcs_client_id'];
            
            // Оновлюємо час останнього входу
            DatabaseConnection::execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$result['user_id']]
            );
            
            return true;
        }
        
        // Токен недійсний - видаляємо cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        return false;
        
    } catch (Exception $e) {
        error_log('Remember token check error: ' . $e->getMessage());
        return false;
    }
}

// Автоматична перевірка remember token при завантаженні
checkRememberToken();
?>