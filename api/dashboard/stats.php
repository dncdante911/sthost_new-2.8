<?php
/**
 * ============================================
 * DASHBOARD STATS API - StormHosting UA
 * API для получения статистики дашбоарда
 * ============================================
 */

define('SECURE_ACCESS', true);

// Заголовки для API
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Проверка авторизации
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Требуется авторизация'
    ]);
    exit;
}

// Подключение к базе данных и классам
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/FossBillingAPI.php';
    
    $pdo = DatabaseConnection::getSiteConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка подключения к базе данных'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$fossbilling_client_id = $_SESSION['fossbilling_client_id'] ?? null;

try {
    // Инициализируем статистику
    $stats = [
        'vps' => 0,
        'domains' => 0,
        'hosting' => 0,
        'active_services' => 0,
        'balance' => 0.00,
        'pending_invoices' => 0,
        'total_spent' => 0.00,
        'last_updated' => date('c')
    ];
    
    // ============================================
    // VPS СТАТИСТИКА
    // ============================================
    
    // Общее количество VPS
    $stmt = $pdo->prepare("SELECT COUNT(*) as vps_count FROM vps_instances WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $vps_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['vps'] = (int)($vps_result['vps_count'] ?? 0);
    
    // Активные VPS
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_vps 
        FROM vps_instances 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$user_id]);
    $active_vps = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_services'] += (int)($active_vps['active_vps'] ?? 0);
    
    // Детальная статистика VPS по статусам
    $stmt = $pdo->prepare("
        SELECT 
            status,
            power_state,
            COUNT(*) as count
        FROM vps_instances 
        WHERE user_id = ? 
        GROUP BY status, power_state
    ");
    $stmt->execute([$user_id]);
    $vps_detailed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['vps_details'] = [
        'running' => 0,
        'stopped' => 0,
        'suspended' => 0,
        'creating' => 0,
        'error' => 0
    ];
    
    foreach ($vps_detailed as $detail) {
        $key = $detail['status'] === 'active' ? $detail['power_state'] : $detail['status'];
        $stats['vps_details'][$key] = (int)$detail['count'];
    }
    
    // ============================================
    // FOSSBILLING СТАТИСТИКА
    // ============================================
    
    if ($fossbilling_client_id) {
        $fossBillingAPI = new FossBillingAPI();
        
        // Баланс клиента
        $balance_result = $fossBillingAPI->getClientBalance($fossbilling_client_id);
        if ($balance_result['success']) {
            $stats['balance'] = (float)$balance_result['balance'];
        }
        
        // Неоплаченные счета
        $invoices_result = $fossBillingAPI->getClientInvoices($fossbilling_client_id, [
            'status' => 'unpaid',
            'limit' => 100
        ]);
        if ($invoices_result['success']) {
            $stats['pending_invoices'] = count($invoices_result['invoices']);
            
            // Сумма к доплате
            $pending_amount = 0;
            foreach ($invoices_result['invoices'] as $invoice) {
                $pending_amount += (float)$invoice['total'];
            }
            $stats['pending_amount'] = $pending_amount;
        }
        
        // Общая потрачена сумма
        $total_spent_result = $fossBillingAPI->getClientTotalSpent($fossbilling_client_id);
        if ($total_spent_result['success']) {
            $stats['total_spent'] = (float)$total_spent_result['amount'];
        }
        
        // Домены
        $domains_result = $fossBillingAPI->getClientDomains($fossbilling_client_id);
        if ($domains_result['success']) {
            $stats['domains'] = count($domains_result['domains']);
            
            // Статистика доменов по статусам
            $domain_stats = ['active' => 0, 'expired' => 0, 'pending' => 0];
            foreach ($domains_result['domains'] as $domain) {
                $domain_status = $domain['status'] ?? 'active';
                if (isset($domain_stats[$domain_status])) {
                    $domain_stats[$domain_status]++;
                }
            }
            $stats['domain_details'] = $domain_stats;
            $stats['active_services'] += $domain_stats['active'];
        }
        
        // Хостинг пакеты
        $hosting_result = $fossBillingAPI->getClientOrders($fossbilling_client_id, [
            'status' => 'active',
            'product_type' => 'hosting'
        ]);
        if ($hosting_result['success']) {
            $stats['hosting'] = count($hosting_result['orders']);
            $stats['active_services'] += $stats['hosting'];
        }
        
        // Последние заказы
        $recent_orders_result = $fossBillingAPI->getClientOrders($fossbilling_client_id, [
            'limit' => 5,
            'sort' => 'created_at',
            'order' => 'desc'
        ]);
        if ($recent_orders_result['success']) {
            $stats['recent_orders'] = array_map(function($order) {
                return [
                    'id' => $order['id'],
                    'title' => $order['title'] ?? 'Заказ #' . $order['id'],
                    'status' => $order['status'],
                    'total' => (float)($order['total'] ?? 0),
                    'created_at' => $order['created_at']
                ];
            }, $recent_orders_result['orders']);
        }
    }
    
    // ============================================
    // ДОПОЛНИТЕЛЬНАЯ СТАТИСТИКА
    // ============================================
    
    // Последние операции VPS
    $stmt = $pdo->prepare("
        SELECT 
            vol.operation_type,
            vol.status,
            vol.started_at,
            vol.completed_at,
            vi.hostname,
            vi.ip_address
        FROM vps_operations_log vol
        LEFT JOIN vps_instances vi ON vol.vps_id = vi.id
        WHERE vol.user_id = ?
        ORDER BY vol.started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_operations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['recent_operations'] = array_map(function($op) {
        return [
            'operation' => $op['operation_type'],
            'status' => $op['status'],
            'hostname' => $op['hostname'],
            'ip_address' => $op['ip_address'],
            'started_at' => $op['started_at'],
            'completed_at' => $op['completed_at'],
            'duration' => $op['completed_at'] ? 
                (strtotime($op['completed_at']) - strtotime($op['started_at'])) : null
        ];
    }, $recent_operations);
    
    // Статистика активности за последние 30 дней
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            action,
            COUNT(*) as count
        FROM user_activity 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at), action
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id]);
    $activity_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Группируем активность по дням
    $activity_by_date = [];
    foreach ($activity_data as $activity) {
        $date = $activity['date'];
        if (!isset($activity_by_date[$date])) {
            $activity_by_date[$date] = [];
        }
        $activity_by_date[$date][$activity['action']] = (int)$activity['count'];
    }
    $stats['activity_chart'] = $activity_by_date;
    
    // Системная информация
    $stats['system_info'] = [
        'last_login' => $_SESSION['last_login'] ?? null,
        'current_session_duration' => time() - ($_SESSION['login_time'] ?? time()),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'session_id' => session_id()
    ];
    
    // Проверка на предупреждения
    $warnings = [];
    
    // Проверяем неоплаченные счета
    if ($stats['pending_invoices'] > 0) {
        $warnings[] = [
            'type' => 'billing',
            'severity' => 'warning',
            'message' => "У вас {$stats['pending_invoices']} неоплаченных счета",
            'action_url' => 'https://bill.sthost.pro/client/invoices'
        ];
    }
    
    // Проверяем VPS в состоянии ошибки
    if ($stats['vps_details']['error'] > 0) {
        $warnings[] = [
            'type' => 'vps',
            'severity' => 'error',
            'message' => "У вас {$stats['vps_details']['error']} VPS в состоянии ошибки",
            'action_url' => '/client/vps/'
        ];
    }
    
    // Проверяем низкий баланс
    if ($stats['balance'] < 100) {
        $warnings[] = [
            'type' => 'balance',
            'severity' => 'info',
            'message' => 'Рекомендуем пополнить баланс',
            'action_url' => 'https://bill.sthost.pro/client/funds/add'
        ];
    }
    
    $stats['warnings'] = $warnings;
    
    // Добавляем метрики производительности
    $stats['performance'] = [
        'query_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'memory_usage' => memory_get_peak_usage(true),
        'queries_count' => $pdo->getAttribute(PDO::ATTR_SERVER_INFO) ?? 'unknown'
    ];
    
    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'cache_duration' => 30, // Кеширование на 30 секунд
        'generated_at' => date('c')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Dashboard Stats API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при получении статистики',
        'error_code' => 'STATS_ERROR',
        'debug_info' => $e->getMessage()
    ]);
}

// ============================================
// КЕШИРОВАНИЕ СТАТИСТИКИ
// ============================================

/**
 * Кеширование статистики для улучшения производительности
 */
function getCachedStats($user_id) {
    $cache_key = "dashboard_stats_user_{$user_id}";
    $cache_file = sys_get_temp_dir() . "/{$cache_key}.json";
    
    // Проверяем актуальность кеша (30 секунд)
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 30) {
        $cached_data = file_get_contents($cache_file);
        $stats = json_decode($cached_data, true);
        
        if ($stats && is_array($stats)) {
            $stats['from_cache'] = true;
            return $stats;
        }
    }
    
    return null;
}

/**
 * Сохранение статистики в кеш
 */
function setCachedStats($user_id, $stats) {
    $cache_key = "dashboard_stats_user_{$user_id}";
    $cache_file = sys_get_temp_dir() . "/{$cache_key}.json";
    
    $stats['cached_at'] = date('c');
    file_put_contents($cache_file, json_encode($stats));
}

/**
 * Очистка кеша статистики
 */
function clearStatsCache($user_id = null) {
    if ($user_id) {
        $cache_key = "dashboard_stats_user_{$user_id}";
        $cache_file = sys_get_temp_dir() . "/{$cache_key}.json";
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    } else {
        // Очищаем весь кеш статистики
        $pattern = sys_get_temp_dir() . "/dashboard_stats_user_*.json";
        foreach (glob($pattern) as $file) {
            unlink($file);
        }
    }
}

/**
 * Получение статистики использования ресурсов VPS
 */
function getVPSResourceStats($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            vi.id,
            vi.hostname,
            vi.ip_address,
            vp.cpu_cores,
            vp.ram_mb,
            vp.disk_gb,
            vi.power_state,
            vi.last_action
        FROM vps_instances vi
        LEFT JOIN vps_plans vp ON vi.plan_id = vp.id
        WHERE vi.user_id = ? AND vi.status = 'active'
        ORDER BY vi.created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $vps_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_resources = [
        'total_cpu' => 0,
        'total_ram_mb' => 0,
        'total_disk_gb' => 0,
        'running_vps' => 0,
        'stopped_vps' => 0
    ];
    
    $vps_details = [];
    
    foreach ($vps_list as $vps) {
        $total_resources['total_cpu'] += (int)$vps['cpu_cores'];
        $total_resources['total_ram_mb'] += (int)$vps['ram_mb'];
        $total_resources['total_disk_gb'] += (int)$vps['disk_gb'];
        
        if ($vps['power_state'] === 'running') {
            $total_resources['running_vps']++;
        } else {
            $total_resources['stopped_vps']++;
        }
        
        $vps_details[] = [
            'id' => $vps['id'],
            'hostname' => $vps['hostname'],
            'ip_address' => $vps['ip_address'],
            'resources' => [
                'cpu' => (int)$vps['cpu_cores'],
                'ram_mb' => (int)$vps['ram_mb'],
                'disk_gb' => (int)$vps['disk_gb']
            ],
            'power_state' => $vps['power_state'],
            'last_action' => $vps['last_action']
        ];
    }
    
    return [
        'totals' => $total_resources,
        'vps_list' => $vps_details
    ];
}

/**
 * Получение статистики трафика и использования
 */
function getUsageStats($pdo, $user_id) {
    // Статистика операций за последние 7 дней
    $stmt = $pdo->prepare("
        SELECT 
            DATE(started_at) as date,
            operation_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM vps_operations_log
        WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(started_at), operation_type
        ORDER BY date DESC, operation_type
    ");
    
    $stmt->execute([$user_id]);
    $operations_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Группируем данные по датам
    $usage_by_date = [];
    foreach ($operations_stats as $stat) {
        $date = $stat['date'];
        if (!isset($usage_by_date[$date])) {
            $usage_by_date[$date] = [
                'total_operations' => 0,
                'successful' => 0,
                'failed' => 0,
                'operations' => []
            ];
        }
        
        $usage_by_date[$date]['total_operations'] += (int)$stat['count'];
        $usage_by_date[$date]['successful'] += (int)$stat['successful'];
        $usage_by_date[$date]['failed'] += (int)$stat['failed'];
        $usage_by_date[$date]['operations'][$stat['operation_type']] = (int)$stat['count'];
    }
    
    // Топ операций
    $stmt = $pdo->prepare("
        SELECT 
            operation_type,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
            AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
        FROM vps_operations_log
        WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY operation_type
        ORDER BY total DESC
    ");
    
    $stmt->execute([$user_id]);
    $top_operations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'daily_usage' => $usage_by_date,
        'top_operations' => array_map(function($op) {
            return [
                'operation' => $op['operation_type'],
                'total' => (int)$op['total'],
                'successful' => (int)$op['successful'],
                'success_rate' => $op['total'] > 0 ? round(($op['successful'] / $op['total']) * 100, 1) : 0,
                'avg_duration' => $op['avg_duration'] ? round((float)$op['avg_duration'], 1) : null
            ];
        }, $top_operations)
    ];
}

/**
 * Получение финансовой аналитики
 */
function getFinancialAnalytics($fossBillingAPI, $client_id) {
    $analytics = [
        'monthly_spending' => [],
        'payment_methods' => [],
        'service_costs' => [],
        'upcoming_renewals' => []
    ];
    
    // Получаем транзакции за последние 12 месяцев
    $transactions_result = $fossBillingAPI->getClientTransactions($client_id, [
        'date_from' => date('Y-m-d', strtotime('-12 months')),
        'date_to' => date('Y-m-d'),
        'limit' => 1000
    ]);
    
    if ($transactions_result['success']) {
        $monthly_totals = [];
        $payment_methods = [];
        
        foreach ($transactions_result['transactions'] as $transaction) {
            $month = date('Y-m', strtotime($transaction['created_at']));
            $amount = (float)$transaction['amount'];
            
            if (!isset($monthly_totals[$month])) {
                $monthly_totals[$month] = 0;
            }
            $monthly_totals[$month] += $amount;
            
            // Статистика по методам оплаты
            $method = $transaction['gateway'] ?? 'unknown';
            if (!isset($payment_methods[$method])) {
                $payment_methods[$method] = ['count' => 0, 'amount' => 0];
            }
            $payment_methods[$method]['count']++;
            $payment_methods[$method]['amount'] += $amount;
        }
        
        $analytics['monthly_spending'] = $monthly_totals;
        $analytics['payment_methods'] = $payment_methods;
    }
    
    // Получаем предстоящие продления
    $services_result = $fossBillingAPI->getClientOrders($client_id, [
        'status' => 'active'
    ]);
    
    if ($services_result['success']) {
        $upcoming = [];
        
        foreach ($services_result['orders'] as $service) {
            if (isset($service['expires_at'])) {
                $expires_at = strtotime($service['expires_at']);
                $days_left = round(($expires_at - time()) / (24 * 3600));
                
                if ($days_left <= 30 && $days_left > 0) {
                    $upcoming[] = [
                        'service' => $service['title'] ?? 'Service #' . $service['id'],
                        'expires_at' => $service['expires_at'],
                        'days_left' => $days_left,
                        'renewal_cost' => (float)($service['price'] ?? 0)
                    ];
                }
            }
        }
        
        // Сортируем по дням до истечения
        usort($upcoming, function($a, $b) {
            return $a['days_left'] - $b['days_left'];
        });
        
        $analytics['upcoming_renewals'] = $upcoming;
    }
    
    return $analytics;
}

/**
 * Проверка системных уведомлений
 */
function getSystemNotifications($pdo, $user_id) {
    $notifications = [];
    
    // Проверяем обслуживание серверов
    $stmt = $pdo->prepare("
        SELECT * FROM system_maintenance
        WHERE start_time <= NOW() AND end_time >= NOW()
        AND status = 'active'
        ORDER BY priority DESC, start_time ASC
    ");
    $stmt->execute();
    $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($maintenance as $maint) {
        $notifications[] = [
            'type' => 'maintenance',
            'severity' => 'warning',
            'title' => 'Техническое обслуживание',
            'message' => $maint['description'],
            'start_time' => $maint['start_time'],
            'end_time' => $maint['end_time'],
            'affected_services' => json_decode($maint['affected_services'], true) ?? []
        ];
    }
    
    // Проверяем новости и обновления
    $stmt = $pdo->prepare("
        SELECT * FROM news
        WHERE published_at <= NOW() AND status = 'published'
        AND (expires_at IS NULL OR expires_at >= NOW())
        ORDER BY priority DESC, published_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($news as $article) {
        $notifications[] = [
            'type' => 'news',
            'severity' => 'info',
            'title' => $article['title'],
            'message' => $article['excerpt'] ?? substr($article['content'], 0, 200) . '...',
            'published_at' => $article['published_at'],
            'url' => '/news/' . $article['slug']
        ];
    }
    
    return $notifications;
}

/**
 * Генерация рекомендаций для пользователя
 */
function generateRecommendations($stats, $user_id) {
    $recommendations = [];
    
    // Рекомендация по VPS
    if ($stats['vps'] == 0) {
        $recommendations[] = [
            'type' => 'vps',
            'priority' => 'high',
            'title' => 'Попробуйте VPS',
            'description' => 'Виртуальные серверы идеально подходят для размещения сайтов и приложений',
            'action_text' => 'Заказать VPS',
            'action_url' => '/pages/vps.php'
        ];
    } elseif ($stats['vps_details']['stopped'] > 0) {
        $recommendations[] = [
            'type' => 'vps_optimization',
            'priority' => 'medium',
            'title' => 'Оптимизация VPS',
            'description' => "У вас {$stats['vps_details']['stopped']} остановленных VPS. Возможно, стоит их запустить или отказаться от неиспользуемых.",
            'action_text' => 'Управление VPS',
            'action_url' => '/client/vps/'
        ];
    }
    
    // Рекомендация по балансу
    if ($stats['balance'] < 100 && $stats['active_services'] > 0) {
        $recommendations[] = [
            'type' => 'balance',
            'priority' => 'high',
            'title' => 'Пополните баланс',
            'description' => 'Рекомендуем пополнить баланс для бесперебойной работы услуг',
            'action_text' => 'Пополнить баланс',
            'action_url' => 'https://bill.sthost.pro/client/funds/add'
        ];
    }
    
    // Рекомендация по доменам
    if ($stats['domains'] == 0 && $stats['vps'] > 0) {
        $recommendations[] = [
            'type' => 'domain',
            'priority' => 'medium',
            'title' => 'Зарегистрируйте домен',
            'description' => 'У вас есть VPS, но нет зарегистрированных доменов. Домен поможет сделать ваш проект более профессиональным.',
            'action_text' => 'Найти домен',
            'action_url' => '/pages/domains.php'
        ];
    }
    
    // Рекомендация по безопасности (SMS 2FA - закомментировано)
    /*
    if (!$user_2fa_enabled) {
        $recommendations[] = [
            'type' => 'security',
            'priority' => 'medium',
            'title' => 'Усильте безопасность',
            'description' => 'Включите двухфакторную аутентификацию по SMS для дополнительной защиты аккаунта',
            'action_text' => 'Настроить 2FA',
            'action_url' => '/client/security.php'
        ];
    }
    */
    
    return $recommendations;
}