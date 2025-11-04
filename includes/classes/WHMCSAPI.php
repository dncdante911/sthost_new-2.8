<?php
/**
 * WHMCS API Integration
 * Класс для интеграции с WHMCS
 * Файл: /includes/classes/WHMCSAPI.php
 */

// Защита от прямого доступа
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

class WHMCSAPI {
    private $api_url;
    private $api_identifier;
    private $api_secret;
    private $timeout = 30;

    public function __construct($api_url = null, $api_identifier = null, $api_secret = null) {
        // URL должен указывать на api.php в WHMCS
        // Например: https://billing.sthost.pro/includes/api.php
        $this->api_url = $api_url ?: WHMCS_API_URL;
        $this->api_identifier = $api_identifier ?: WHMCS_API_IDENTIFIER;
        $this->api_secret = $api_secret ?: WHMCS_API_SECRET;
    }

    /**
     * Выполнение API запроса к WHMCS
     */
    private function makeRequest($action, $params = []) {
        try {
            // WHMCS API всегда использует POST с параметрами формы
            $postData = array_merge([
                'action' => $action,
                'identifier' => $this->api_identifier,
                'secret' => $this->api_secret,
                'responsetype' => 'json'
            ], $params);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ]);

            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                throw new Exception("cURL Error: " . $error);
            }

            $decoded = json_decode($response, true);

            // WHMCS возвращает result: "success" или "error"
            $success = isset($decoded['result']) && $decoded['result'] === 'success';

            return [
                'success' => $success,
                'http_code' => $http_code,
                'data' => $decoded,
                'raw_response' => $response,
                'error' => !$success ? ($decoded['message'] ?? 'Unknown error') : null
            ];

        } catch (Exception $e) {
            error_log("WHMCS API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Создание клиента в WHMCS
     */
    public function createClient($client_data) {
        $params = [
            'firstname' => $client_data['first_name'],
            'lastname' => $client_data['last_name'],
            'email' => $client_data['email'],
            'address1' => $client_data['address'] ?? '',
            'city' => $client_data['city'] ?? '',
            'state' => $client_data['state'] ?? '',
            'postcode' => $client_data['postcode'] ?? '',
            'country' => $client_data['country'] ?? 'UA',
            'phonenumber' => $client_data['phone'] ?? '',
            'password2' => $client_data['password'],
            'clientip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'language' => 'russian',
            'currency' => 1, // ID валюты (обычно 1 для основной валюты)
            'skipvalidation' => true // Пропускаем дополнительные проверки
        ];

        if (!empty($client_data['company'])) {
            $params['companyname'] = $client_data['company'];
        }

        $result = $this->makeRequest('AddClient', $params);

        // Добавляем client_id для совместимости
        if ($result['success'] && isset($result['data']['clientid'])) {
            $result['client_id'] = $result['data']['clientid'];
        }

        return $result;
    }

    /**
     * Получение информации о клиенте
     */
    public function getClient($client_id) {
        $params = [
            'clientid' => $client_id,
            'stats' => true
        ];

        return $this->makeRequest('GetClientsDetails', $params);
    }

    /**
     * Поиск клиента по email
     */
    public function findClientByEmail($email) {
        $params = [
            'search' => $email,
            'limitnum' => 1
        ];

        $result = $this->makeRequest('GetClients', $params);

        // Форматируем результат для совместимости с FOSSBilling
        if ($result['success'] && isset($result['data']['clients'])) {
            $result['data']['list'] = $result['data']['clients']['client'] ?? [];
        }

        return $result;
    }

    /**
     * Получение баланса клиента
     */
    public function getClientBalance($client_id) {
        $result = $this->getClient($client_id);

        if ($result['success'] && isset($result['data']['credit'])) {
            return [
                'success' => true,
                'balance' => floatval($result['data']['credit'])
            ];
        }

        return [
            'success' => false,
            'error' => 'Unable to get balance',
            'balance' => 0
        ];
    }

    /**
     * Создание продукта VPS
     * Примечание: в WHMCS продукты обычно создаются через админку
     * Этот метод добавляет custom fields для существующего продукта
     */
    public function createVPSProduct($product_data) {
        // В WHMCS продукты обычно настраиваются вручную через админку
        // API не предоставляет прямого создания продуктов
        // Но можно использовать AddProduct (требуется активация в WHMCS)

        return [
            'success' => false,
            'error' => 'Product creation should be done via WHMCS admin panel',
            'message' => 'Create products manually in WHMCS and use product ID for orders'
        ];
    }

    /**
     * Создание заказа VPS
     */
    public function createVPSOrder($order_data) {
        $params = [
            'clientid' => $order_data['client_id'],
            'pid' => $order_data['product_id'], // ID продукта в WHMCS
            'billingcycle' => $this->convertPeriod($order_data['period'] ?? 'monthly'),
            'paymentmethod' => $order_data['paymentmethod'] ?? 'banktransfer',
        ];

        // Custom поля для VPS конфигурации
        if (isset($order_data['config'])) {
            $config = $order_data['config'];
            if (isset($config['hostname'])) {
                $params['hostname'] = $config['hostname'];
            }
            if (isset($config['os_template'])) {
                $params['configoptions[1]'] = $config['os_template']; // Номер зависит от настроек
            }
            if (isset($config['root_password'])) {
                $params['rootpw'] = $config['root_password'];
            }
        }

        $result = $this->makeRequest('AddOrder', $params);

        // Автоматическая активация заказа если требуется
        if ($result['success'] && isset($result['data']['orderid']) &&
            ($order_data['activate'] ?? false)) {
            $order_id = $result['data']['orderid'];
            $this->activateOrder($order_id);
        }

        return $result;
    }

    /**
     * Конвертация периода из FOSSBilling формата в WHMCS
     */
    private function convertPeriod($period) {
        $map = [
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually' => 'semiannually',
            'annually' => 'annually',
            'biennially' => 'biennially',
            'triennially' => 'triennially',
            'onetime' => 'onetime',
            'free' => 'free'
        ];

        return $map[$period] ?? 'monthly';
    }

    /**
     * Получение информации о заказе
     */
    public function getOrder($order_id) {
        $params = [
            'id' => $order_id
        ];

        return $this->makeRequest('GetOrders', $params);
    }

    /**
     * Получение списка заказов клиента
     */
    public function getClientOrders($client_id, $filters = []) {
        $params = array_merge([
            'userid' => $client_id,
            'limitnum' => 100
        ], $filters);

        return $this->makeRequest('GetOrders', $params);
    }

    /**
     * Активация заказа
     */
    public function activateOrder($order_id) {
        $params = [
            'orderid' => $order_id,
            'autosetup' => true,
            'sendemail' => true
        ];

        return $this->makeRequest('AcceptOrder', $params);
    }

    /**
     * Получение списка продуктов/услуг клиента
     */
    public function getClientProducts($client_id, $filters = []) {
        $params = array_merge([
            'clientid' => $client_id,
            'limitnum' => 100
        ], $filters);

        $result = $this->makeRequest('GetClientsProducts', $params);

        // Форматируем для совместимости
        if ($result['success'] && isset($result['data']['products'])) {
            $result['data']['list'] = $result['data']['products']['product'] ?? [];
        }

        return $result;
    }

    /**
     * Приостановка услуги
     */
    public function suspendOrder($service_id, $reason = '') {
        $params = [
            'serviceid' => $service_id,
            'suspendreason' => $reason
        ];

        return $this->makeRequest('ModuleSuspend', $params);
    }

    /**
     * Возобновление услуги
     */
    public function unsuspendOrder($service_id) {
        $params = [
            'serviceid' => $service_id
        ];

        return $this->makeRequest('ModuleUnsuspend', $params);
    }

    /**
     * Удаление/отмена услуги
     */
    public function cancelOrder($service_id) {
        $params = [
            'serviceid' => $service_id
        ];

        return $this->makeRequest('ModuleTerminate', $params);
    }

    /**
     * Создание счета
     */
    public function createInvoice($invoice_data) {
        $params = [
            'userid' => $invoice_data['client_id'],
            'status' => 'Unpaid',
            'sendinvoice' => $invoice_data['sendinvoice'] ?? true,
            'date' => date('Y-m-d'),
            'duedate' => $invoice_data['due_at'] ?? date('Y-m-d', strtotime('+7 days'))
        ];

        // Добавляем позиции счета
        if (isset($invoice_data['lines']) && is_array($invoice_data['lines'])) {
            foreach ($invoice_data['lines'] as $index => $line) {
                $params["itemdescription{$index}"] = $line['title'];
                $params["itemamount{$index}"] = $line['price'];
                $params["itemtaxed{$index}"] = $invoice_data['taxrate'] ?? 0 ? 1 : 0;
            }
        } else {
            $params['itemdescription1'] = $invoice_data['title'];
            $params['itemamount1'] = $invoice_data['amount'];
            $params['itemtaxed1'] = $invoice_data['taxrate'] ?? 0 ? 1 : 0;
        }

        return $this->makeRequest('CreateInvoice', $params);
    }

    /**
     * Получение счета
     */
    public function getInvoice($invoice_id) {
        $params = [
            'invoiceid' => $invoice_id
        ];

        return $this->makeRequest('GetInvoice', $params);
    }

    /**
     * Получение списка счетов клиента
     */
    public function getClientInvoices($client_id, $status = null) {
        $params = [
            'userid' => $client_id,
            'limitnum' => 100
        ];

        if ($status) {
            $params['status'] = ucfirst($status); // Paid, Unpaid, Cancelled, etc.
        }

        $result = $this->makeRequest('GetInvoices', $params);

        // Форматируем для совместимости
        if ($result['success'] && isset($result['data']['invoices'])) {
            $result['data']['list'] = $result['data']['invoices']['invoice'] ?? [];
        }

        return $result;
    }

    /**
     * Добавление платежа к счету
     */
    public function createPayment($payment_data) {
        $params = [
            'invoiceid' => $payment_data['invoice_id'],
            'transid' => $payment_data['transaction_id'] ?? '',
            'gateway' => $payment_data['gateway'] ?? 'banktransfer',
            'date' => date('Y-m-d H:i:s'),
            'amount' => $payment_data['amount'],
            'fees' => $payment_data['fees'] ?? 0,
            'noemail' => $payment_data['noemail'] ?? false
        ];

        return $this->makeRequest('AddInvoicePayment', $params);
    }

    /**
     * Получение списка продуктов
     */
    public function getProducts($filters = []) {
        $params = array_merge([
            'limitnum' => 100
        ], $filters);

        $result = $this->makeRequest('GetProducts', $params);

        // Форматируем для совместимости
        if ($result['success'] && isset($result['data']['products'])) {
            $result['data']['list'] = $result['data']['products']['product'] ?? [];
        }

        return $result;
    }

    /**
     * Получение статистики (простая версия)
     */
    public function getStats() {
        // WHMCS не имеет прямого endpoint для статистики через API
        // Собираем базовую информацию
        return [
            'success' => true,
            'data' => [
                'message' => 'Use WHMCS admin panel for detailed statistics'
            ]
        ];
    }

    /**
     * Обработка webhook от WHMCS
     */
    public function processWebhook($payload) {
        try {
            // WHMCS использует Module Hooks
            // События передаются через POST параметры

            $event_type = $_POST['event'] ?? $payload['event'] ?? '';

            switch ($event_type) {
                case 'AfterModuleCreate':
                    return $this->handleOrderActivated($_POST);

                case 'AfterModuleSuspend':
                    return $this->handleOrderSuspended($_POST);

                case 'AfterModuleTerminate':
                    return $this->handleOrderCancelled($_POST);

                case 'InvoicePaid':
                    return $this->handleInvoicePaid($_POST);

                default:
                    return ['success' => true, 'message' => 'Event ignored'];
            }

        } catch (Exception $e) {
            error_log("Webhook processing error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Обработка активации заказа
     */
    private function handleOrderActivated($data) {
        $service_id = $data['serviceid'] ?? null;
        if (!$service_id) {
            return ['success' => false, 'error' => 'Missing service ID'];
        }

        // Логика создания VPS при активации заказа
        // Интеграция с VPSManager

        return ['success' => true, 'message' => 'Order activation processed'];
    }

    /**
     * Обработка приостановки заказа
     */
    private function handleOrderSuspended($data) {
        $service_id = $data['serviceid'] ?? null;
        if (!$service_id) {
            return ['success' => false, 'error' => 'Missing service ID'];
        }

        // Логика приостановки VPS

        return ['success' => true, 'message' => 'Order suspension processed'];
    }

    /**
     * Обработка отмены заказа
     */
    private function handleOrderCancelled($data) {
        $service_id = $data['serviceid'] ?? null;
        if (!$service_id) {
            return ['success' => false, 'error' => 'Missing service ID'];
        }

        // Логика удаления VPS

        return ['success' => true, 'message' => 'Order cancellation processed'];
    }

    /**
     * Обработка оплаты счета
     */
    private function handleInvoicePaid($data) {
        $invoice_id = $data['invoiceid'] ?? null;
        if (!$invoice_id) {
            return ['success' => false, 'error' => 'Missing invoice ID'];
        }

        // Логика обработки оплаты
        // Можно активировать связанные услуги

        return ['success' => true, 'message' => 'Payment processed'];
    }

    /**
     * Генерация пароля
     */
    private function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle($chars), 0, $length);
    }

    /**
     * Синхронизация клиента с WHMCS
     */
    public function syncClient($user_data) {
        try {
            // Ищем клиента по email
            $client_search = $this->findClientByEmail($user_data['email']);

            if ($client_search['success'] && !empty($client_search['data']['list'])) {
                // Клиент уже существует
                $client = $client_search['data']['list'][0];
                return [
                    'success' => true,
                    'client_id' => $client['id'],
                    'exists' => true
                ];
            }

            // Создаем нового клиента
            $client_result = $this->createClient([
                'first_name' => $user_data['name'] ?? 'User',
                'last_name' => $user_data['surname'] ?? '',
                'email' => $user_data['email'],
                'phone' => $user_data['phone'] ?? '',
                'password' => $user_data['password'] ?? $this->generatePassword()
            ]);

            if ($client_result['success']) {
                return [
                    'success' => true,
                    'client_id' => $client_result['client_id'],
                    'exists' => false
                ];
            }

            return $client_result;

        } catch (Exception $e) {
            error_log("Sync client error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Обновление информации о клиенте
     */
    public function updateClient($client_id, $client_data) {
        $params = array_merge(['clientid' => $client_id], $client_data);
        return $this->makeRequest('UpdateClient', $params);
    }

    /**
     * Добавление заметки к клиенту
     */
    public function addClientNote($client_id, $note) {
        $params = [
            'userid' => $client_id,
            'notes' => $note
        ];

        return $this->makeRequest('AddClientNote', $params);
    }
}
?>
