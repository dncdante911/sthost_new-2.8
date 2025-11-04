<?php
/**
 * ============================================
 * DEBUG DASHBOARD - StormHosting UA
 * Упрощенная версия для отладки
 * ============================================
 */

define('SECURE_ACCESS', true);

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Начинаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Debug Dashboard - Проверка системы</h2>";

// Проверка авторизации
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    echo "<p style='color: red;'>❌ Пользователь не авторизован</p>";
    echo "<p><a href='/auth/login.php'>Перейти к авторизации</a></p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Пользователь авторизован</p>";
    echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'не задан') . "</p>";
    echo "<p>User Name: " . ($_SESSION['user_name'] ?? 'не задан') . "</p>";
    echo "<p>User Email: " . ($_SESSION['user_email'] ?? 'не задан') . "</p>";
}

// Проверка подключения к конфигурации
echo "<h3>Проверка конфигурации:</h3>";
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
    echo "<p style='color: green;'>✅ Config.php подключен</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка в config.php: " . $e->getMessage() . "</p>";
}

// Проверка подключения к БД
echo "<h3>Проверка базы данных:</h3>";
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
    $pdo = DatabaseConnection::getSiteConnection();
    echo "<p style='color: green;'>✅ Подключение к БД успешно</p>";
    
    // Тестовый запрос
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Пользователей в БД: " . $result['user_count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка подключения к БД: " . $e->getMessage() . "</p>";
}

// Проверка таблиц VPS
echo "<h3>Проверка таблиц VPS:</h3>";
try {
    $tables_to_check = [
        'vps_instances',
        'vps_plans', 
        'vps_os_templates'
    ];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p style='color: green;'>✅ Таблица $table: " . $result['count'] . " записей</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Таблица $table не существует или пуста</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка проверки таблиц VPS: " . $e->getMessage() . "</p>";
}

// Проверка классов
echo "<h3>Проверка классов:</h3>";
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php';
    echo "<p style='color: green;'>✅ VPSManager класс загружен</p>";
    
    $vpsManager = new VPSManager($pdo);
    echo "<p style='color: green;'>✅ VPSManager инициализирован</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка VPSManager: " . $e->getMessage() . "</p>";
}

try {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/classes/FossBillingAPI.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/FossBillingAPI.php';
        echo "<p style='color: green;'>✅ FossBillingAPI класс загружен</p>";
        
        $fossBillingAPI = new FossBillingAPI();
        echo "<p style='color: green;'>✅ FossBillingAPI инициализирован</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ FossBillingAPI.php не найден</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка FossBillingAPI: " . $e->getMessage() . "</p>";
}

// Проверка статистики пользователя
echo "<h3>Статистика пользователя:</h3>";
$user_id = $_SESSION['user_id'];

try {
    // VPS статистика
    $stmt = $pdo->prepare("SELECT COUNT(*) as vps_count FROM vps_instances WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $vps_result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>VPS пользователя: " . ($vps_result['vps_count'] ?? 0) . "</p>";
    
    // Активные VPS
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_vps FROM vps_instances WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $active_vps = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Активные VPS: " . ($active_vps['active_vps'] ?? 0) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка получения статистики VPS: " . $e->getMessage() . "</p>";
}

// Проверка файлов CSS/JS
echo "<h3>Проверка ресурсов:</h3>";
$resources = [
    '/assets/css/dashboard-new.css',
    '/assets/js/dashboard-new.js'
];

foreach ($resources as $resource) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $resource;
    if (file_exists($file_path)) {
        echo "<p style='color: green;'>✅ $resource найден (" . filesize($file_path) . " байт)</p>";
    } else {
        echo "<p style='color: red;'>❌ $resource не найден</p>";
    }
}

// Простой тест дашбоарда
echo "<h3>Тест простого дашбоарда:</h3>";
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard - StormHosting UA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .debug-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        body {
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="debug-card">
        <h2>🎯 Простой дашбоард (работает!)</h2>
        <p>Добро пожаловать, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Пользователь'); ?>!</strong></p>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="vps-count"><?php echo $vps_result['vps_count'] ?? 0; ?></div>
                <div>VPS Серверов</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="active-count"><?php echo $active_vps['active_vps'] ?? 0; ?></div>
                <div>Активных</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number">0</div>
                <div>Домены</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number">0.00</div>
                <div>Баланс, грн</div>
            </div>
        </div>
    </div>
    
    <div class="debug-card">
        <h4>🔧 Быстрые действия</h4>
        <div class="row">
            <div class="col-md-6">
                <a href="/pages/vps.php" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-plus-circle"></i> Заказать VPS
                </a>
            </div>
            <div class="col-md-6">
                <a href="/client/vps/" class="btn btn-success w-100 mb-2">
                    <i class="bi bi-gear"></i> Управление VPS
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <a href="https://bill.sthost.pro/client" class="btn btn-info w-100 mb-2" target="_blank">
                    <i class="bi bi-credit-card"></i> Биллинг панель
                </a>
            </div>
            <div class="col-md-6">
                <a href="/client/profile.php" class="btn btn-secondary w-100 mb-2">
                    <i class="bi bi-person-gear"></i> Профиль
                </a>
            </div>
        </div>
    </div>
    
    <div class="debug-card">
        <h4>📋 Информация о системе</h4>
        <p><strong>PHP версия:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Сессия ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Время сервера:</strong> <?php echo date('d.m.Y H:i:s'); ?></p>
        <p><strong>FOSSBilling Client ID:</strong> <?php echo $_SESSION['fossbilling_client_id'] ?? 'не задан'; ?></p>
    </div>
    
    <div class="debug-card">
        <h4>🚀 Переход к полному дашбоарду</h4>
        <p>Если все проверки выше прошли успешно, можно попробовать полный дашбоард:</p>
        <a href="/client/dashboard_new.php" class="btn btn-warning">
            <i class="bi bi-arrow-right"></i> Открыть полный дашбоард
        </a>
        
        <hr>
        
        <h5>Или создайте недостающие файлы:</h5>
        <div class="alert alert-info">
            <strong>1. Создайте файл:</strong> <code>/assets/css/dashboard-new.css</code><br>
            <strong>2. Создайте файл:</strong> <code>/assets/js/dashboard-new.js</code><br>
            <strong>3. Создайте файл:</strong> <code>/includes/classes/FossBillingAPI.php</code><br>
            <strong>4. Создайте API файлы в:</strong> <code>/api/dashboard/</code>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
console.log('🎉 Debug Dashboard loaded successfully!');

// Простой тест JavaScript без eval()
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM loaded');
    
    // Простая анимация счетчиков
    const counters = document.querySelectorAll('.stats-number');
    counters.forEach(counter => {
        const finalValue = parseInt(counter.textContent);
        let currentValue = 0;
        const increment = Math.ceil(finalValue / 20);
        
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            counter.textContent = currentValue;
        }, 50);
    });
});

// Тест AJAX запроса (без eval)
function testAPI() {
    fetch('/api/dashboard/stats.php')
        .then(response => response.json())
        .then(data => {
            console.log('API test:', data);
        })
        .catch(error => {
            console.log('API test failed:', error);
        });
}

// Запускаем тест через 2 секунды
setTimeout(testAPI, 2000);
</script>

</body>
</html>