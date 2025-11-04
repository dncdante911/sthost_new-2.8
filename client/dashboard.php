<?php
/**
 * ============================================
 * НОВЫЙ ДАШБОАРД StormHosting UA
 * Современный интерфейс с интеграцией VPS
 * ============================================
 */

define('SECURE_ACCESS', true);

// Проверка авторизации
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// Данные пользователя
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$fossbilling_client_id = $_SESSION['fossbilling_client_id'];

// Получаем информацию о пользователе
$user_info = DatabaseConnection::fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
);

// >>> ИСПРАВЛЕНИЕ: ИНИЦИАЛИЗАЦИЯ $pdo ДЛЯ ПРЯМЫХ DB-ЗАПРОСОВ
// Это решает проблему HTTP 500, так как предыдущие запросы использовали только статические методы.
$pdo = DatabaseConnection::getSiteConnection();

// Подключаем VPS Manager и FOSSBilling API
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/FossBillingAPI.php';

// Инициализируем классы
$vpsManager = new VPSManager();
$fossBillingAPI = new FossBillingAPI();

// ============================================
// ПОЛУЧЕНИЕ СТАТИСТИКИ УСЛУГ
// ============================================

// Инициализация статистики
$services_stats = [
    'domains' => 0,
    'hosting' => 0,
    'vps' => 0,
    'active_services' => 0,
    'balance' => 0.00,
    'pending_invoices' => 0,
    'total_spent' => 0.00
];

// Получаем VPS статистику
try {
    $user_vps = $vpsManager->getUserVPS($user_id);
    if ($user_vps['success']) {
        $vps_list = $user_vps['vps_list'];
        $services_stats['vps'] = count($vps_list);
        
        // Подсчитываем активные VPS
        foreach ($vps_list as $vps) {
            if ($vps['status'] === 'active') {
                $services_stats['active_services']++;
            }
        }
    }
} catch (Exception $e) {
    error_log("VPS stats error: " . $e->getMessage());
    $vps_list = [];
}

// Получаем данные из FOSSBilling
try {
    if ($fossbilling_client_id) {
        // Баланс клиента
        $balance = $fossBillingAPI->getClientBalance($fossbilling_client_id);
        if ($balance['success']) {
            $services_stats['balance'] = $balance['balance'];
        }
        
        // Счета клиента
        $invoices = $fossBillingAPI->getClientInvoices($fossbilling_client_id, ['status' => 'unpaid']);
        if ($invoices['success']) {
            $services_stats['pending_invoices'] = count($invoices['invoices']);
        }
        
        // Домены клиента
        $domains = $fossBillingAPI->getClientDomains($fossbilling_client_id);
        if ($domains['success']) {
            $services_stats['domains'] = count($domains['domains']);
        }
        
        // Общая потрачена сумма
        $total_spent = $fossBillingAPI->getClientTotalSpent($fossbilling_client_id);
        if ($total_spent['success']) {
            $services_stats['total_spent'] = $total_spent['amount'];
        }
    }
} catch (Exception $e) {
    error_log("FOSSBilling API error: " . $e->getMessage());
}

// >>> ВОССТАНОВЛЕННЫЙ БЛОК: Получаем последние операции VPS
// Здесь используется $pdo, который мы инициализировали выше.
$recent_vps_operations = [];
try {
    $stmt = $pdo->prepare("
        SELECT vol.*, vi.hostname, vi.ip_address
        FROM vps_operations_log vol
        LEFT JOIN vps_instances vi ON vol.vps_id = vi.id
        WHERE vol.user_id = ?
        ORDER BY vol.started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_vps_operations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Таблица логов может не существовать
}

// >>> ВОССТАНОВЛЕННЫЙ БЛОК: Получаем последнюю активность
// Здесь используется $pdo, который мы инициализировали выше.
$recent_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_activity
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Таблица активности может не существовать
}

// Мета-данные страницы
$page_title = 'Панель управління - StormHosting UA';
$page_description = 'Сучасна панель управління послугами хостингу, доменами та VPS серверами';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/dashboard-new.css">
</head>

<body class="dashboard-page">

<main class="dashboard-main">
    <div class="container-fluid">
        
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-section">
                        <h1 class="dashboard-title">
                            Добро пожаловать, <span class="text-primary"><?php echo htmlspecialchars($user_name); ?>!</span>
                        </h1>
                        <p class="dashboard-subtitle">
                            Управляйте своими услугами и отслеживайте статистику в современной панели
                        </p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="user-quick-info">
                        <div class="balance-card">
                            <span class="balance-label">Баланс:</span>
                            <span class="balance-amount"><?php echo number_format($services_stats['balance'], 2); ?> грн</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card stats-vps">
                    <div class="stats-icon">
                        <i class="bi bi-hdd-stack"></i>
                    </div>
                    <div class="stats-content">
                        <h3><?php echo $services_stats['vps']; ?></h3>
                        <p>VPS Сервери</p>
                        <small class="stats-note"><?php echo $services_stats['active_services']; ?> активних</small>
                    </div>
                    <div class="stats-action">
                        <?php if ($services_stats['vps'] > 0): ?>
                        <a href="/client/vps/" class="btn btn-sm btn-outline-primary">Управління</a>
                        <?php else: ?>
                        <a href="/pages/vds/virtual.php" class="btn btn-sm btn-primary">Замовити</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stats-card stats-domains">
                    <div class="stats-icon">
                        <i class="bi bi-globe"></i>
                    </div>
                    <div class="stats-content">
                        <h3><?php echo $services_stats['domains']; ?></h3>
                        <p>Домени</p>
                        <small class="stats-note">Активні домени</small>
                    </div>
                    <div class="stats-action">
                        <a href="/pages/domains.php" class="btn btn-sm btn-outline-success">Управління</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stats-card stats-hosting">
                    <div class="stats-icon">
                        <i class="bi bi-server"></i>
                    </div>
                    <div class="stats-content">
                        <h3><?php echo $services_stats['hosting']; ?></h3>
                        <p>Хостинг</p>
                        <small class="stats-note">Активні пакети</small>
                    </div>
                    <div class="stats-action">
                        <a href="/pages/hosting.php" class="btn btn-sm btn-outline-info">Управління</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stats-card stats-invoices">
                    <div class="stats-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="stats-content">
                        <h3><?php echo $services_stats['pending_invoices']; ?></h3>
                        <p>Рахунки</p>
                        <small class="stats-note">До оплати</small>
                    </div>
                    <div class="stats-action">
                        <a href="https://bill.sthost.pro/client/invoices" class="btn btn-sm btn-outline-warning" target="_blank">Переглянути</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="card-header">
                        <div class="header-content">
                            <h4 class="card-title">
                                <i class="bi bi-hdd-stack me-2"></i>
                                VPS Сервери
                            </h4>
                            <p class="card-subtitle">Управління віртуальними приватними серверами</p>
                        </div>
                        <div class="header-actions">
                            <?php if ($services_stats['vps'] > 0): ?>
                            <a href="/client/vps/" class="btn btn-primary btn-sm">
                                <i class="bi bi-gear me-1"></i>
                                Управління VPS
                            </a>
                            <?php else: ?>
                            <a href="/pages/vds/virtual.php" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>
                                Замовити VPS
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($vps_list)): ?>
                        <div class="vps-list">
                            <?php foreach (array_slice($vps_list, 0, 3) as $vps): ?>
                            <div class="vps-item">
                                <div class="vps-info">
                                    <div class="vps-name">
                                        <strong><?php echo htmlspecialchars($vps['hostname']); ?></strong>
                                        <span class="vps-os"><?php echo htmlspecialchars($vps['os_name'] ?? 'Unknown OS'); ?></span>
                                    </div>
                                    <div class="vps-specs">
                                        <span class="spec-item">CPU: <?php echo $vps['plan_cpu'] ?? 1; ?> ядра</span>
                                        <span class="spec-item">RAM: <?php echo $vps['plan_ram'] ?? 1024; ?> MB</span>
                                        <span class="spec-item">IP: <?php echo htmlspecialchars($vps['ip_address']); ?></span>
                                    </div>
                                </div>
                                <div class="vps-status">
                                    <span class="status-badge status-<?php echo $vps['status']; ?>">
                                        <?php echo ucfirst($vps['status']); ?>
                                    </span>
                                </div>
                                <div class="vps-actions">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-success vps-start" data-vps-id="<?php echo $vps['id']; ?>" title="Запустить">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                        <button class="btn btn-outline-warning vps-restart" data-vps-id="<?php echo $vps['id']; ?>" title="Перезагрузить">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button class="btn btn-outline-danger vps-stop" data-vps-id="<?php echo $vps['id']; ?>" title="Остановить">
                                            <i class="bi bi-stop-fill"></i>
                                        </button>
                                        <a href="/client/vps/console.php?id=<?php echo $vps['id']; ?>" class="btn btn-outline-primary" title="Консоль" target="_blank">
                                            <i class="bi bi-terminal"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($vps_list) > 3): ?>
                        <div class="text-center mt-3">
                            <a href="/client/vps/" class="btn btn-outline-primary">
                                Показать все VPS (<?php echo count($vps_list); ?>)
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-hdd-stack"></i>
                            </div>
                            <h5>У вас пока нет VPS серверов</h5>
                            <p>Начните с заказа мощного виртуального сервера</p>
                            <a href="/pages/vds/virtual.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>
                                Заказать первый VPS
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-credit-card me-2"></i>
                            Биллинг
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="billing-stats">
                            <div class="billing-item">
                                <span class="label">Текущий баланс:</span>
                                <span class="value text-primary"><?php echo number_format($services_stats['balance'], 2); ?> грн</span>
                            </div>
                            <div class="billing-item">
                                <span class="label">Неоплачено:</span>
                                <span class="value text-warning"><?php echo $services_stats['pending_invoices']; ?> счетов</span>
                            </div>
                            <div class="billing-item">
                                <span class="label">Потрачено всего:</span>
                                <span class="value text-muted"><?php echo number_format($services_stats['total_spent'], 2); ?> грн</span>
                            </div>
                        </div>
                        <div class="billing-actions mt-3">
                            <a href="https://bill.sthost.pro/client" class="btn btn-outline-primary btn-sm me-2" target="_blank">
                                Биллинг-панель
                            </a>
                            <a href="https://bill.sthost.pro/client/invoices" class="btn btn-primary btn-sm" target="_blank">
                                Пополнить
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($recent_vps_operations)): ?>
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-clock-history me-2"></i>
                            Последние операции VPS
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php foreach (array_slice($recent_vps_operations, 0, 5) as $operation): ?>
                            <div class="activity-item">
                                <div class="activity-icon status-<?php echo $operation['status']; ?>">
                                    <i class="bi bi-<?php echo getOperationIcon($operation['operation_type']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo ucfirst($operation['operation_type']); ?>
                                    </div>
                                    <div class="activity-desc">
                                        <?php echo htmlspecialchars($operation['hostname']); ?>
                                        (<?php echo htmlspecialchars($operation['ip_address']); ?>)
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('d.m.Y H:i', strtotime($operation['started_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status">
                                    <span class="status-dot status-<?php echo $operation['status']; ?>"></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-lightning me-2"></i>
                            Быстрые действия
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="/pages/vds/virtual.php" class="quick-action-btn">
                                <i class="bi bi-plus-circle"></i>
                                <span>Заказать VPS</span>
                            </a>
                            <a href="/pages/domains.php" class="quick-action-btn">
                                <i class="bi bi-search"></i>
                                <span>Найти домен</span>
                            </a>
                            <a href="/pages/hosting.php" class="quick-action-btn">
                                <i class="bi bi-server"></i>
                                <span>Заказать хостинг</span>
                            </a>
                            <a href="/client/profile.php" class="quick-action-btn">
                                <i class="bi bi-person-gear"></i>
                                <span>Настройки</span>
                            </a>
                            <a href="https://bill.sthost.pro/client/support" class="quick-action-btn" target="_blank">
                                <i class="bi bi-chat-dots"></i>
                                <span>Поддержка</span>
                            </a>
                            </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($recent_activity)): ?>
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-activity me-2"></i>
                            Недавняя активность
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Действие</th>
                                        <th>Детали</th>
                                        <th>IP адрес</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="activity-badge">
                                                <?php echo getActivityName($activity['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $details = json_decode($activity['details'], true);
                                            echo $details ? getActivityDetails($details) : 'N/A';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/dashboard-new.js"></script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>

</body>
</html>

<?php
// ============================================
// HELPER FUNCTIONS
// ============================================

function getOperationIcon($operation_type) {
    switch ($operation_type) {
        case 'start': return 'play-fill';
        case 'stop': return 'stop-fill';
        case 'restart': return 'arrow-clockwise';
        case 'create': return 'plus-circle';
        case 'delete': return 'trash';
        default: return 'gear';
    }
}

function getActivityName($action) {
    $actions = [
        'login' => 'Вход в систему',
        'logout' => 'Выход из системы',
        'vps_created' => 'Создание VPS',
        'vps_start' => 'Запуск VPS',
        'vps_stop' => 'Остановка VPS',
        'vps_restart' => 'Перезагрузка VPS',
        'password_changed' => 'Смена пароля',
        'profile_updated' => 'Обновление профиля',
    ];
    
    return $actions[$action] ?? $action;
}

function getActivityDetails($details) {
    if (isset($details['hostname'])) {
        return 'Сервер: ' . htmlspecialchars($details['hostname']);
    }
    if (isset($details['vps_id'])) {
        return 'VPS ID: ' . htmlspecialchars($details['vps_id']);
    }
    return 'Детали недоступны';
}
?>