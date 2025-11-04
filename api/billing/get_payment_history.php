<?php
/**
 * API для получения истории платежей из WHMCS
 * Файл: /api/billing/get_payment_history.php
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
    $payments = [];

    if ($whmcs_client_id) {
        // Получаем оплаченные счета клиента
        $result = $whmcs->getClientInvoices($whmcs_client_id, 'paid');

        if ($result['success'] && isset($result['data']['list'])) {
            foreach ($result['data']['list'] as $invoice) {
                $paid_at = 'Неизвестно';
                if (isset($invoice['datepaid']) && $invoice['datepaid']) {
                    $paid_at = date('d.m.Y H:i', strtotime($invoice['datepaid']));
                }

                $payments[] = [
                    'id' => $invoice['id'] ?? 0,
                    'amount' => floatval($invoice['total'] ?? 0),
                    'date' => $paid_at,
                    'currency' => $invoice['currencycode'] ?? 'UAH'
                ];
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
        'message' => 'Ошибка получения данных',
        'payments' => []
    ]);
}
?>
