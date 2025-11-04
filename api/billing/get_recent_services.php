<?php
/**
 * API для отримання останніх послуг з FossBilling
 * Файл: /api/billing/get_recent_services.php
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

$fossbilling_client_id = getFossBillingClientId();

try {
    $api_key = 'YPo9tN0V8Ih0pdHDAKJfBuyNA08CnaHN';
    $services = [];
    
    if ($fossbilling_client_id) {
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
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['result']['list']) && is_array($data['result']['list'])) {
                // Беремо останні 5 послуг
                $orders = array_slice($data['result']['list'], 0, 5);
                
                foreach ($orders as $order) {
                    $expires_at = 'Безстроково';
                    if (isset($order['expires_at']) && $order['expires_at']) {
                        $expires_at = date('d.m.Y', strtotime($order['expires_at']));
                    }
                    
                    $status = $order['status'] ?? 'pending';
                    
                    $services[] = [
                        'id' => $order['id'] ?? 0,
                        'name' => $order['title'] ?? 'Послуга',
                        'status' => $status,
                        'expires_at' => $expires_at,
                        'price' => floatval($order['price'] ?? 0)
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'services' => $services
    ]);
    
} catch (Exception $e) {
    error_log('Recent services API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання даних',
        'services' => []
    ]);
}
?>