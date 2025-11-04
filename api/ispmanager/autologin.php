<?php
/**
 * API для автоматичного логіну в ISPmanager
 * Файл: /api/ispmanager/autologin.php
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

$user_email = getUserEmail();
$section = $_GET['section'] ?? '';

try {
    /**
     * ISPmanager AUTOLOGIN
     * 
     * Для автологіну потрібно:
     * 1. Створити сесійний токен через ISPmanager API
     * 2. Або використати auth_key для одноразового входу
     */
    
    $ispmanager_url = 'https://cp.sthost.pro/ispmgr';
    $api_user = 'createuserapi';
    $api_pass = '3344Frz@q0607Dm$157';
    
    // Генеруємо auth токен через ISPmanager API
    $auth_params = [
        'out' => 'json',
        'func' => 'auth',
        'username' => $user_email,
        'password' => '' // Тут потрібен пароль користувача ISPmanager
    ];
    
    /**
     * ВАРІАНТ 1: Redirect з автологіном (якщо налаштовано Single Sign-On)
     * 
     * Деякі ISPmanager підтримують SSO через auth_key
     */
    
    // Створюємо тимчасовий auth key (якщо підтримується)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ispmanager_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERPWD, "$api_user:$api_pass");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'func' => 'session.generate',
        'username' => $user_email,
        'out' => 'json'
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        
        if (isset($data['auth'])) {
            $auth_key = $data['auth'];
            
            // Мапа розділів
            $sections = [
                'dns' => 'func=domain.sublist',
                'ssl' => 'func=cert',
                'database' => 'func=db',
                'filemanager' => 'func=filemanager',
                'ftp' => 'func=ftpuser',
                'cron' => 'func=cron',
                'backup' => 'func=backup',
                'stat' => 'func=stat.site'
            ];
            
            $section_param = isset($sections[$section]) ? '&' . $sections[$section] : '';
            
            // URL з auth токеном
            $redirect_url = $ispmanager_url . '?auth=' . $auth_key . $section_param;
            
            echo json_encode([
                'success' => true,
                'redirect_url' => $redirect_url
            ]);
            exit;
        }
    }
    
    /**
     * ВАРІАНТ 2: Якщо SSO не налаштовано, просто відкриваємо розділ
     * Користувач повинен буде ввести пароль один раз
     */
    
    $sections = [
        'dns' => 'func=domain.sublist',
        'ssl' => 'func=cert',
        'database' => 'func=db',
        'filemanager' => 'func=filemanager',
        'ftp' => 'func=ftpuser',
        'cron' => 'func=cron',
        'backup' => 'func=backup',
        'stat' => 'func=stat.site'
    ];
    
    $section_param = isset($sections[$section]) ? '?' . $sections[$section] : '';
    $redirect_url = $ispmanager_url . $section_param;
    
    echo json_encode([
        'success' => true,
        'redirect_url' => $redirect_url,
        'note' => 'SSO not configured, manual login required'
    ]);
    
} catch (Exception $e) {
    error_log('ISPmanager autologin error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка автологіну'
    ]);
}

/**
 * ІНСТРУКЦІЯ ПО НАЛАШТУВАННЮ SSO В ISPMANAGER:
 * 
 * 1. В ISPmanager перейдіть в:
 *    Налаштування -> Безпека -> API доступ
 * 
 * 2. Увімкніть "Дозволити створення сесій через API"
 * 
 * 3. Додайте IP адресу вашого веб-сервера в білий список
 * 
 * 4. Створіть API користувача з правами:
 *    - session.generate
 *    - session.validate
 * 
 * 5. Альтернативно можна використовувати:
 *    - LDAP інтеграцію
 *    - OAuth2
 *    - SAML SSO
 * 
 * Документація: https://www.ispmanager.ru/docs/ispmanager/ispmanager-api
 */
?>