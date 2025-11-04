<?php
/**
 * WHMCS Client - Упрощенный клиент для работы с WHMCS API
 * Файл: /includes/whmcs_client.php
 */

class WHMCSClient {
    private $api_url;
    private $api_identifier;
    private $api_secret;

    public function __construct() {
        $this->api_url = WHMCS_API_URL;
        $this->api_identifier = WHMCS_API_IDENTIFIER;
        $this->api_secret = WHMCS_API_SECRET;
    }

    /**
     * Создание клиента в WHMCS
     */
    public function createClient($clientData) {
        $params = [
            'firstname' => $clientData['first_name'] ?? $clientData['firstname'] ?? '',
            'lastname' => $clientData['last_name'] ?? $clientData['lastname'] ?? '',
            'email' => $clientData['email'],
            'address1' => $clientData['address'] ?? '',
            'city' => $clientData['city'] ?? '',
            'state' => $clientData['state'] ?? '',
            'postcode' => $clientData['postcode'] ?? '',
            'country' => $clientData['country'] ?? 'UA',
            'phonenumber' => $clientData['phone'] ?? '',
            'password2' => $clientData['password'],
            'skipvalidation' => true
        ];

        return $this->makeRequest('AddClient', $params);
    }

    /**
     * Создание заказа в WHMCS
     */
    public function createOrder($orderData) {
        $params = [
            'clientid' => $orderData['client_id'],
            'pid' => $orderData['product_id'],
            'billingcycle' => $orderData['period'] ?? 'monthly',
            'paymentmethod' => $orderData['paymentmethod'] ?? 'banktransfer'
        ];

        // Добавляем дополнительные параметры если есть
        if (isset($orderData['hostname'])) {
            $params['hostname'] = $orderData['hostname'];
        }
        if (isset($orderData['domain'])) {
            $params['domain'] = $orderData['domain'];
        }

        return $this->makeRequest('AddOrder', $params);
    }

    /**
     * Получение URL для оплаты счета
     * В WHMCS клиент может оплатить счет через viewinvoice.php
     */
    public function getPaymentUrl($invoiceId) {
        return WHMCS_URL . '/viewinvoice.php?id=' . $invoiceId;
    }

    /**
     * Получение клиента по ID
     */
    public function getClient($clientId) {
        return $this->makeRequest('GetClientsDetails', [
            'clientid' => $clientId,
            'stats' => true
        ]);
    }

    /**
     * Получение баланса клиента
     */
    public function getClientBalance($clientId) {
        $result = $this->getClient($clientId);

        if ($result && isset($result['credit'])) {
            return [
                'success' => true,
                'balance' => floatval($result['credit'])
            ];
        }

        return [
            'success' => false,
            'balance' => 0
        ];
    }

    /**
     * Получение списка продуктов клиента
     */
    public function getClientProducts($clientId) {
        return $this->makeRequest('GetClientsProducts', [
            'clientid' => $clientId,
            'limitnum' => 100
        ]);
    }

    /**
     * Получение списка счетов клиента
     */
    public function getClientInvoices($clientId, $status = null) {
        $params = [
            'userid' => $clientId,
            'limitnum' => 100
        ];

        if ($status) {
            $params['status'] = ucfirst($status);
        }

        return $this->makeRequest('GetInvoices', $params);
    }

    /**
     * Выполнение запроса к WHMCS API
     */
    private function makeRequest($action, $params = []) {
        $postData = array_merge([
            'action' => $action,
            'identifier' => $this->api_identifier,
            'secret' => $this->api_secret,
            'responsetype' => 'json'
        ], $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("WHMCS API Error: " . $error);
            return [
                'result' => 'error',
                'message' => $error
            ];
        }

        return json_decode($response, true);
    }
}
?>
