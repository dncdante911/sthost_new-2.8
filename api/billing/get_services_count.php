<?php
/**
 * API для получения количества услуг из WHMCS
 * Файл: /api/billing/get_services_count.php
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

    $domains = 0;
    $vps = 0;
    $hosting = 0;

    // Получаем список активных услуг клиента
    if ($whmcs_client_id) {
        $result = $whmcs->getClientProducts($whmcs_client_id, ['status' => 'Active']);

        if ($result['success'] && isset($result['data']['list'])) {
            foreach ($result['data']['list'] as $product) {
                $product_name = strtolower($product['name'] ?? $product['product'] ?? '');
                $group_name = strtolower($product['groupname'] ?? '');

                // Подсчет по типам
                if (strpos($product_name, 'domain') !== false || strpos($group_name, 'domain') !== false) {
                    $domains++;
                } elseif (strpos($product_name, 'vps') !== false || strpos($product_name, 'server') !== false ||
                          strpos($group_name, 'vps') !== false || strpos($group_name, 'server') !== false) {
                    $vps++;
                } elseif (strpos($product_name, 'hosting') !== false || strpos($product_name, 'host') !== false ||
                          strpos($group_name, 'hosting') !== false || strpos($group_name, 'host') !== false) {
                    $hosting++;
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
        'message' => 'Ошибка получения данных',
        'domains' => 0,
        'vps' => 0,
        'hosting' => 0
    ]);
}
?>
