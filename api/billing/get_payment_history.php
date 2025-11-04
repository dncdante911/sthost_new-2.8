<?php
/**
 * API для отримання історії платежів з FossBilling
 * Файл: /api/billing/get_payment_history.php
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
    $payments = [];
    
    if ($fossbilling_client_id) {
        $api_url = 'https://bill.sthost.pro/api/admin/invoice/get_list';
        $params = [
            'client_id' => $fossbilling_client_id,
            'status' => 'paid'
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
                foreach ($data['result']['list'] as $invoice) {
                    $paid_at = 'Невідомо';
                    if (isset($invoice['paid_at']) && $invoice['paid_at']) {
                        $paid_at = date('d.m.Y H:i', strtotime($invoice['paid_at']));
                    }
                    
                    $payments[] = [
                        'id' => $invoice['id'] ?? 0,
                        'amount' => floatval($invoice['total'] ?? 0),
                        'date' => $paid_at,
                        'currency' => $invoice['currency'] ?? 'UAH'
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    error_log('Payment history API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка отримання даних',
        'payments' => []
    ]);
}
?>