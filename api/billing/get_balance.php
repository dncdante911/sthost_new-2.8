<?php
/**
 * API для получения баланса клиента из WHMCS
 * Файл: /api/billing/get_balance.php
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

$user_id = getUserId();
$whmcs_client_id = getWHMCSClientId();

try {
    // Инициализация WHMCS API
    $whmcs = new WHMCSAPI();

    // Если есть client_id, делаем запрос к WHMCS
    if ($whmcs_client_id) {
        $result = $whmcs->getClientBalance($whmcs_client_id);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'balance' => floatval($result['balance'])
            ]);
            exit;
        }
    }

    // Если не удалось получить из API, возвращаем 0
    echo json_encode([
        'success' => true,
        'balance' => 0.00
    ]);

} catch (Exception $e) {
    error_log('Balance API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка получения баланса',
        'balance' => 0.00
    ]);
}
?>
