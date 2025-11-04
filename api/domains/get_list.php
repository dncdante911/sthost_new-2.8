<?php
/**
 * API для отримання списку доменів з FossBilling та ISPmanager
 * Файл: /api/domains/get_list.php
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
    $domains = [];
    
    if ($fossbilling_client_id) {
        // Отримуємо замовлення з типом domain
        $api_url = 'https://bill.sthost.pro/api/admin/order/get_list';
        $params = [
            'client_id' => $fossbilling_client_id,
            'product_type' => 'domain'
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
                    // Отримуємо назву домену з title або config
                    $domain_name = $order['title'] ?? '';
                    
                    // Якщо в title немає домену, шукаємо в config
                    if (empty($domain_name) && isset($order['config'])) {
                        $config = json_decode($order['config'], true);
                        $domain_name = $config['domain'] ?? $config['domain_name'] ?? '';
                    }
                    
                    if (empty($domain_name)) {
                        continue;
                    }
                    
                    $registered_at = 'Невідомо';
                    if (isset($order['created_at']) && $order['created_at']) {
                        $registered_at = date('d.m.Y', strtotime($order['created_at']));
                    }
                    
                    $expires_at = 'Невідомо';
                    if (isset($order['expires_at']) && $order['expires_at']) {
                        $expires_at = date('d.m.Y', strtotime($order['expires_at']));
                    }
                    
                    $status = $order['status'] ?? 'pending';
                    $auto_renewal = ($order['period'] ?? '') === 'auto' || ($order['invoice_option'] ?? '') === 'auto';
                    
                    $domains[] = [
                        'id' => $order['id'] ?? 0,
                        'name' => $domain_name,
                        'status' => $status,
                        'registered_at' => $registered_at,
                        'expires_at' => $expires_at,
                        'auto_renewal' => $auto_renewal,
                        'price' => floatval($order['price'] ?? 0)
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'domains' => $domains
    ]);
    
} catch (Exception $e) {
    error_log('Domains list API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання даних',
        'domains' => []
    ]);
}
?>