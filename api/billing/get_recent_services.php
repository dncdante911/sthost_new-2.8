<?php
/**
 * API для получения последних услуг из WHMCS
 * Файл: /api/billing/get_recent_services.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/WHMCSAPI.php';

header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Не авторизовано']);
    exit;
}

$whmcs_client_id = getWHMCSClientId();

try {
    $whmcs = new WHMCSAPI();
    $services = [];

    if ($whmcs_client_id) {
        // Получаем список продуктов/услуг клиента
        $result = $whmcs->getClientProducts($whmcs_client_id);

        if ($result['success'] && isset($result['data']['list'])) {
            // Берем последние 5 услуг
            $products = array_slice($result['data']['list'], 0, 5);

            foreach ($products as $product) {
                $expires_at = 'Бессрочно';
                if (isset($product['nextduedate']) && $product['nextduedate'] && $product['nextduedate'] != '0000-00-00') {
                    $expires_at = date('d.m.Y', strtotime($product['nextduedate']));
                }

                $status = $product['status'] ?? 'Pending';

                $services[] = [
                    'id' => $product['id'] ?? 0,
                    'name' => $product['name'] ?? ($product['product'] ?? 'Услуга'),
                    'status' => $status,
                    'expires_at' => $expires_at,
                    'price' => floatval($product['amount'] ?? 0)
                ];
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
        'message' => 'Ошибка получения данных',
        'services' => []
    ]);
}
?>
