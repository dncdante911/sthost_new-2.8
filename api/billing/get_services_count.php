<?php
/**
 * API для отримання кількості послуг з FossBilling
 * Файл: /api/billing/get_services_count.php
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
    
    $domains = 0;
    $vps = 0;
    $hosting = 0;
    
    // Отримуємо список послуг клієнта
    if ($fossbilling_client_id) {
        $api_url = 'https://bill.sthost.pro/api/admin/order/get_list';
        $params = [
            'client_id' => $fossbilling_client_id,
            'status' => 'active'
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
                foreach ($data['result']['list'] as $order) {
                    $product_type = strtolower($order['product_type'] ?? '');
                    
                    // Підрахунок по типах
                    if (strpos($product_type, 'domain') !== false) {
                        $domains++;
                    } elseif (strpos($product_type, 'vps') !== false || strpos($product_type, 'server') !== false) {
                        $vps++;
                    } elseif (strpos($product_type, 'hosting') !== false || strpos($product_type, 'host') !== false) {
                        $hosting++;
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'domains' => $domains,
        'vps' => $vps,
        'hosting' => $hosting
    ]);
    
} catch (Exception $e) {
    error_log('Services count API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання даних',
        'domains' => 0,
        'vps' => 0,
        'hosting' => 0
    ]);
}
?>