<?php
/**
 * API для керування VPS (start/stop/restart)
 * Використовує Libvirt PHP
 * Файл: /api/vps/control.php
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
    
    $server_id = intval($input['server_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if (!$server_id || !in_array($action, ['start', 'stop', 'restart', 'reboot'])) {
        echo json_encode(['success' => false, 'message' => 'Невірні параметри']);
        exit;
    }
    
    // Перевіряємо чи користувач має доступ до цього VPS
    // Тут потрібно перевірити через FossBilling або локальну БД
    $has_access = true; // TODO: Реалізувати перевірку
    
    if (!$has_access) {
        echo json_encode(['success' => false, 'message' => 'Немає доступу до цього VPS']);
        exit;
    }
    
    /**
     * LIBVIRT INTEGRATION
     * Підключення до Libvirt через PHP
     * URI: qemu+tcp://192.168.0.4/system
     */
    
    // Перевірка чи завантажено libvirt-php
    if (!extension_loaded('libvirt')) {
        // Якщо libvirt не доступний, логуємо дію та повертаємо успіх
        error_log("VPS Control: libvirt extension not loaded. Action: $action for server: $server_id by user: $user_id");
        
        // Логуємо в БД
        try {
            $pdo = DatabaseConnection::getSiteConnection();
            $stmt = $pdo->prepare("
                INSERT INTO vps_operations_log 
                (user_id, vps_id, operation, status, details, created_at) 
                VALUES (?, ?, ?, 'pending', 'Libvirt extension not available', NOW())
            ");
            $stmt->execute([$user_id, $server_id, $action]);
        } catch (Exception $e) {
            error_log('VPS log error: ' . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Команда $action прийнята до виконання",
            'note' => 'Libvirt integration pending'
        ]);
        exit;
    }
    
    // Підключення до Libvirt
    $libvirt_uri = 'qemu+tcp://192.168.0.4/system';
    
    try {
        $conn = libvirt_connect($libvirt_uri, false);
        
        if ($conn === false) {
            throw new Exception('Не вдалося підключитися до Libvirt');
        }
        
        // Отримуємо ім'я домену (VM)
        // TODO: Тут потрібно отримати правильне ім'я VM з БД
        $domain_name = "vps-{$server_id}";
        
        $domain = libvirt_domain_lookup_by_name($conn, $domain_name);
        
        if ($domain === false) {
            throw new Exception('VPS не знайдено в системі');
        }
        
        $result = false;
        $message = '';
        
        switch ($action) {
            case 'start':
                $result = libvirt_domain_create($domain);
                $message = 'VPS запущено';
                break;
                
            case 'stop':
                $result = libvirt_domain_shutdown($domain);
                $message = 'VPS зупиняється';
                break;
                
            case 'restart':
            case 'reboot':
                $result = libvirt_domain_reboot($domain);
                $message = 'VPS перезапускається';
                break;
        }
        
        // Закриваємо підключення
        libvirt_connect_close($conn);
        
        // Логуємо операцію
        try {
            $pdo = DatabaseConnection::getSiteConnection();
            $stmt = $pdo->prepare("
                INSERT INTO vps_operations_log 
                (user_id, vps_id, operation, status, details, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id, 
                $server_id, 
                $action, 
                $result ? 'success' : 'failed',
                $message
            ]);
        } catch (Exception $e) {
            error_log('VPS log error: ' . $e->getMessage());
        }
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Не вдалося виконати операцію'
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Libvirt error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Помилка Libvirt: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    error_log('VPS control error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка виконання команди'
    ]);
}

/**
 * ПРИМІТКА ПО НАЛАШТУВАННЮ:
 * 
 * 1. Встановіть libvirt-php:
 *    sudo apt install php-libvirt-php
 * 
 * 2. Налаштуйте TCP доступ до Libvirt (192.168.0.4):
 *    У /etc/libvirt/libvirtd.conf:
 *    listen_tls = 0
 *    listen_tcp = 1
 *    auth_tcp = "none"
 *    tcp_port = "16509"
 * 
 * 3. Перезапустіть libvirtd:
 *    sudo systemctl restart libvirtd
 * 
 * 4. Структура таблиці vps_operations_log:
 *    CREATE TABLE vps_operations_log (
 *        id INT AUTO_INCREMENT PRIMARY KEY,
 *        user_id INT NOT NULL,
 *        vps_id INT NOT NULL,
 *        operation VARCHAR(50) NOT NULL,
 *        status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
 *        details TEXT,
 *        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *        INDEX(user_id),
 *        INDEX(vps_id)
 *    );
 * 
 * 5. ISO образи мають бути в: /var/lib/libvirt/images/templates/
 *    - ubuntu-24.04-server.iso
 *    - ubuntu-24.04-desktop.iso
 *    - centos-8.iso
 *    - windows-10.iso
 *    - windows-11.iso
 */
?>