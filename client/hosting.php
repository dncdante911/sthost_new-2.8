<?php
/**
 * Сторінка управління хостингом через ISPmanager
 * Файл: /client/hosting.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

// Перевірка авторізації
requireLogin('/client/hosting.php');

$user_id = getUserId();
$user_email = getUserEmail();
$user_name = getUserName();

$page_title = 'Управління хостингом - STHost';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --primary-hover: #5a67d8;
            --secondary-color: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --hover-bg: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: rgba(148, 163, 184, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            padding: 2rem 1rem;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .logo i {
            font-size: 32px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .nav-link i {
            font-size: 20px;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-item {
            color: var(--text-secondary);
        }
        
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
            border-color: var(--primary-color);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .feature-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .feature-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .info-banner {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-banner h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }
        
        .info-banner h3 i {
            color: var(--primary-color);
        }
        
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-link {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .quick-link:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }
        
        .quick-link i {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .mobile-menu-btn {
            display: none;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            color: var(--text-primary);
            cursor: pointer;
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-visible {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="bi bi-server"></i>
            <h3>STHost</h3>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/client/dashboard-new.php" class="nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Головна</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/client/domains.php" class="nav-link">
                    <i class="bi bi-globe2"></i>
                    <span>Домени</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/client/vps.php" class="nav-link">
                    <i class="bi bi-server"></i>
                    <span>VPS сервери</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/client/hosting.php" class="nav-link active">
                    <i class="bi bi-hdd-network"></i>
                    <span>Хостинг</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="https://mail.sthost.pro" target="_blank" class="nav-link">
                    <i class="bi bi-envelope"></i>
                    <span>Пошта</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/client/settings.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Налаштування</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/auth/logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Вихід</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/client/dashboard-new.php">Головна</a></li>
                    <li class="breadcrumb-item active">Хостинг</li>
                </ol>
            </nav>
            
            <h1>Управління хостингом</h1>
            <p style="color: var(--text-secondary);">Керуйте своїми хостинг-пакетами через ISPmanager</p>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <h3>
                <i class="bi bi-info-circle"></i>
                Про ISPmanager
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                ISPmanager - потужна панель керування хостингом, яка надає повний контроль над вашими веб-сайтами, 
                базами даних, поштою та іншими сервісами. Отримайте доступ до всіх функцій хостингу в одному місці.
            </p>
            <a href="https://cp.sthost.pro" target="_blank" class="btn-primary" style="max-width: 300px;">
                <i class="bi bi-box-arrow-up-right"></i> Відкрити ISPmanager
            </a>
        </div>

        <!-- Features Grid -->
        <div class="features-grid">
            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-diagram-3"></i>
                </div>
                <h3 class="feature-title">DNS управління</h3>
                <p class="feature-description">
                    Повне керування DNS записами: A, AAAA, CNAME, MX, TXT та іншими
                </p>
                <button class="btn-primary">
                    Керувати DNS
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="feature-title">SSL сертифікати</h3>
                <p class="feature-description">
                    Встановлення безкоштовних Let's Encrypt сертифікатів або власних SSL
                </p>
                <button class="btn-primary">
                    Керувати SSL
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-hdd-stack"></i>
                </div>
                <h3 class="feature-title">Бази даних</h3>
                <p class="feature-description">
                    Створення та керування MySQL/PostgreSQL базами даних через phpMyAdmin
                </p>
                <button class="btn-primary">
                    Керувати БД
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-folder2-open"></i>
                </div>
                <h3 class="feature-title">Файловий менеджер</h3>
                <p class="feature-description">
                    Завантаження, редагування та управління файлами сайту через веб-інтерфейс
                </p>
                <button class="btn-primary">
                    Відкрити файли
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-cloud-arrow-up"></i>
                </div>
                <h3 class="feature-title">FTP доступ</h3>
                <p class="feature-description">
                    Створення FTP акаунтів для завантаження файлів на сервер
                </p>
                <button class="btn-primary">
                    Керувати FTP
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://mail.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-envelope-at"></i>
                </div>
                <h3 class="feature-title">Електронна пошта</h3>
                <p class="feature-description">
                    Створення поштових скриньок, налаштування переадресації та автовідповідачів
                </p>
                <button class="btn-primary">
                    Керувати поштою
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3 class="feature-title">Cron завдання</h3>
                <p class="feature-description">
                    Автоматизація виконання скриптів за розкладом
                </p>
                <button class="btn-primary">
                    Керувати Cron
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-archive"></i>
                </div>
                <h3 class="feature-title">Резервні копії</h3>
                <p class="feature-description">
                    Створення та відновлення backup копій сайтів та баз даних
                </p>
                <button class="btn-primary">
                    Керувати backup
                </button>
            </div>

            <div class="feature-card" onclick="window.open('https://cp.sthost.pro', '_blank')">
                <div class="feature-icon">
                    <i class="bi bi-bar-chart"></i>
                </div>
                <h3 class="feature-title">Статистика</h3>
                <p class="feature-description">
                    Перегляд статистики відвідувань, використання ресурсів та логів
                </p>
                <button class="btn-primary">
                    Переглянути статистику
                </button>
            </div>
        </div>

        <!-- Quick Access Links -->
        <div style="margin-top: 3rem;">
            <h2 style="margin-bottom: 1.5rem;">Швидкий доступ</h2>
            <div class="quick-links">
                <a href="https://cp.sthost.pro" target="_blank" class="quick-link">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <div>ISPmanager панель</div>
                </a>
                <a href="https://cp.sthost.pro:1501/Z6mCFHxyrhB5yKQ5/phpmyadmin/" target="_blank" class="quick-link">
                    <i class="bi bi-database"></i>
                    <div>phpMyAdmin</div>
                </a>
                <a href="https://mail.sthost.pro" target="_blank" class="quick-link">
                    <i class="bi bi-envelope"></i>
                    <div>Webmail</div>
                </a>
                <a href="https://cp.sthost.pro/docs" target="_blank" class="quick-link">
                    <i class="bi bi-book"></i>
                    <div>Документація</div>
                </a>
            </div>
        </div>

        <!-- Login Info -->
        <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(102, 126, 234, 0.05); border-radius: 12px; border-left: 4px solid var(--primary-color);">
            <h4 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
                <i class="bi bi-info-circle"></i>
                Дані для входу
            </h4>
            <p style="color: var(--text-secondary); font-size: 14px;">
                <strong>URL:</strong> <a href="https://cp.sthost.pro" target="_blank" style="color: var(--primary-color);">https://cp.sthost.pro</a><br>
                <strong>Логін:</strong> Ваш email (<?php echo htmlspecialchars($user_email); ?>)<br>
                <strong>Пароль:</strong> Той самий що і для входу в цю панель<br><br>
                <i class="bi bi-exclamation-triangle" style="color: var(--warning);"></i> 
                Якщо у вас немає доступу до ISPmanager, зверніться до підтримки для створення облікового запису.
            </p>
        </div>
    </main>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('mobile-visible');
        }

        // Close sidebar on click outside (mobile)
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('mobile-visible') && 
                !sidebar.contains(e.target) && 
                !menuBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-visible');
            }
        });
    </script>
</body>
</html>