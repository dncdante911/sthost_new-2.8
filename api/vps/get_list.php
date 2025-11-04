<?php
/**
 * API для отримання списку VPS з FossBilling
 * Файл: /api/vps/get_list.php
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

$user_id = getUserId();
$fossbilling_client_id = getFossBillingClientId();

try {
    $api_key = 'YPo9tN0V8Ih0pdHDAKJfBuyNA08CnaHN';
    $servers = [];
    
    if ($fossbilling_client_id) {
        // Отримуємо замовлення VPS з FossBilling
        $api_url = 'https://bill.sthost.pro/api/admin/order/get_list';
        $params = [
            'client_id' => $fossbilling_client_id
        ];
        
        $url = $api_url . '?key=' . $api_key;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['result']['list']) && is_array($data['result']['list'])) {
                foreach ($data['result']['list'] as $order) {
                    $product_type = strtolower($order['product_type'] ?? '');
                    
                    // Фільтруємо тільки VPS замовлення
                    if (strpos($product_type, 'vps') === false && 
                        strpos($product_type, 'server') === false &&
                        strpos(strtolower($order['title'] ?? ''), 'vps') === false) {
                        continue;
                    }
                    
                    // Отримуємо конфігурацію з config
                    $config = [];
                    if (isset($order['config']) && !empty($order['config'])) {
                        $config = json_decode($order['config'], true) ?? [];
                    }
                    
                    // Статус оплати
                    $is_paid = ($order['status'] ?? '') === 'active';
                    
                    // Дані VPS
                    $server_name = $order['title'] ?? 'VPS Server';
                    $cpu = $config['cpu'] ?? $config['vcpu'] ?? 2;
                    $ram = $config['ram'] ?? $config['memory'] ?? 4;
                    $disk = $config['disk'] ?? $config['storage'] ?? 50;
                    $os = $config['os'] ?? $config['operating_system'] ?? 'Ubuntu 24.04';
                    $ip = $config['ip'] ?? $config['ip_address'] ?? null;
                    
                    // Визначаємо статус сервера
                    $server_status = 'pending';
                    if ($is_paid) {
                        // Тут можна додати перевірку реального статусу через Libvirt API
                        $server_status = 'running'; // За замовчуванням running якщо оплачено
                    }
                    
                    $servers[] = [
                        'id' => $order['id'] ?? 0,
                        'name' => $server_name,
                        'cpu' => intval($cpu),
                        'ram' => intval($ram),
                        'disk' => intval($disk),
                        'os' => $os,
                        'ip' => $ip,
                        'status' => $server_status,
                        'is_paid' => $is_paid,
                        'created_at' => $order['created_at'] ?? null,
                        'price' => floatval($order['price'] ?? 0)
                    ];
                }
            }
        }
    }
    
    // Якщо немає даних з FossBilling, можна перевірити локальну БД
    if (empty($servers)) {
        try {
            $pdo = DatabaseConnection::getSiteConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM vps_instances 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            $local_vps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($local_vps as $vps) {
                $servers[] = [
                    'id' => $vps['id'],
                    'name' => $vps['hostname'] ?? 'VPS Server',
                    'cpu' => intval($vps['vcpu'] ?? 2),
                    'ram' => intval($vps['ram_mb'] / 1024 ?? 4),
                    'disk' => intval($vps['disk_gb'] ?? 50),
                    'os' => $vps['os_template'] ?? 'Ubuntu',
                    'ip' => $vps['ip_address'],
                    'status' => $vps['status'] ?? 'pending',
                    'is_paid' => ($vps['status'] ?? '') !== 'pending_payment',
                    'created_at' => $vps['created_at'],
                    'price' => 0
                ];
            }
        } catch (Exception $e) {
            error_log('Local VPS query error: ' . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'servers' => $servers
    ]);
    
} catch (Exception $e) {
    error_log('VPS list API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання даних',
        'servers' => []
    ]);
}
?>