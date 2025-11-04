<?php
/**
 * API для отримання балансу з FossBilling
 * Файл: /api/billing/get_balance.php
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
    // API ключ FossBilling
    $api_key = 'YPo9tN0V8Ih0pdHDAKJfBuyNA08CnaHN';
    $api_url = 'https://bill.sthost.pro/api/admin/client/balance_get';
    
    // Якщо є client_id, робимо запит до FossBilling
    if ($fossbilling_client_id) {
        $params = [
            'id' => $fossbilling_client_id
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
            
            if (isset($data['result'])) {
                echo json_encode([
                    'success' => true,
                    'balance' => floatval($data['result'])
                ]);
                exit;
            }
        }
    }
    
    // Якщо не вдалося отримати з API, повертаємо 0
    echo json_encode([
        'success' => true,
        'balance' => 0.00
    ]);
    
} catch (Exception $e) {
    error_log('Balance API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання балансу',
        'balance' => 0.00
    ]);
}
?>