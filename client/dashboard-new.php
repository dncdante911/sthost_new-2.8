<?php
/**
 * Головна панель управління користувача
 * Файл: /client/dashboard-new.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

// Перевірка авторізації
requireLogin('/client/dashboard-new.php');

$user_id = getUserId();
$user_email = getUserEmail();
$user_name = getUserName();
$fossbilling_client_id = getFossBillingClientId();

// Отримуємо інформацію про користувача
$user_info = DatabaseConnection::fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
);

$page_title = 'Панель управління - STHost';
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --primary-color: #667eea;
            --primary-hover: #5a67d8;
            --secondary-color: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
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
        
        .sidebar.mobile-hidden {
            transform: translateX(-100%);
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
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .welcome-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .welcome-text h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .notification-btn, .mobile-menu-btn {
            position: relative;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .notification-btn:hover, .mobile-menu-btn:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.balance {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stat-icon.domains {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .stat-icon.vps {
            background: rgba(139, 92, 246, 0.1);
            color: var(--secondary-color);
        }
        
        .stat-icon.hosting {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: var(--primary-color);
        }
        
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .view-all:hover {
            color: var(--secondary-color);
        }
        
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .service-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }
        
        .service-info h4 {
            font-size: 16px;
            margin-bottom: 0.25rem;
        }
        
        .service-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-expired {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Loading State */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .spinner {
            border: 3px solid var(--border-color);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
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
                display: flex;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-text h1 {
                font-size: 24px;
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
                <a href="/client/dashboard-new.php" class="nav-link active">
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
                <a href="/client/hosting.php" class="nav-link">
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="welcome-section">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="welcome-text">
                    <h1>Вітаємо, <?php echo htmlspecialchars(explode(' ', $user_name)[0] ?? 'Користувач'); ?>! 👋</h1>
                    <p>Керуйте своїми сервісами легко та зручно</p>
                </div>
            </div>
            <div class="top-actions">
                <button class="notification-btn" onclick="showNotifications()">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge"></span>
                </button>
                <div class="user-avatar" onclick="window.location.href='/client/settings.php'">
                    <?php echo strtoupper(substr($user_email, 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon balance">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="stat-value" id="balance">
                    <div class="spinner"></div>
                </div>
                <div class="stat-label">Баланс рахунку</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon domains">
                        <i class="bi bi-globe2"></i>
                    </div>
                </div>
                <div class="stat-value" id="domains-count">
                    <div class="spinner"></div>
                </div>
                <div class="stat-label">Активних доменів</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon vps">
                        <i class="bi bi-server"></i>
                    </div>
                </div>
                <div class="stat-value" id="vps-count">
                    <div class="spinner"></div>
                </div>
                <div class="stat-label">VPS серверів</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon hosting">
                        <i class="bi bi-hdd-network"></i>
                    </div>
                </div>
                <div class="stat-value" id="hosting-count">
                    <div class="spinner"></div>
                </div>
                <div class="stat-label">Хостинг-пакетів</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Services -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-clock-history"></i>
                        Останні послуги
                    </h3>
                    <a href="https://bill.sthost.pro" target="_blank" class="view-all">Переглянути всі</a>
                </div>
                <div id="recent-services">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-receipt"></i>
                        Історія платежів
                    </h3>
                    <a href="https://bill.sthost.pro" target="_blank" class="view-all">Переглянути всі</a>
                </div>
                <div id="payment-history">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-graph-up"></i>
                        Витрати за місяць
                    </h3>
                </div>
                <div class="chart-container">
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-pie-chart"></i>
                        Розподіл послуг
                    </h3>
                </div>
                <div class="chart-container">
                    <canvas id="servicesChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-visible');
        }

        // Load billing data from FossBilling
        async function loadBillingData() {
            try {
                const response = await fetch('/api/billing/get_balance.php');
                const data = await response.json();
                
                const balanceEl = document.getElementById('balance');
                if (data.success) {
                    balanceEl.innerHTML = data.balance.toFixed(2) + ' ₴';
                } else {
                    balanceEl.innerHTML = '0.00 ₴';
                }
            } catch (error) {
                console.error('Error loading billing data:', error);
                document.getElementById('balance').innerHTML = '0.00 ₴';
            }
        }

        // Load services count
        async function loadServicesCount() {
            try {
                const response = await fetch('/api/billing/get_services_count.php');
                const data = await response.json();
                
                document.getElementById('domains-count').textContent = data.domains || 0;
                document.getElementById('vps-count').textContent = data.vps || 0;
                document.getElementById('hosting-count').textContent = data.hosting || 0;
            } catch (error) {
                console.error('Error loading services count:', error);
                document.getElementById('domains-count').textContent = '0';
                document.getElementById('vps-count').textContent = '0';
                document.getElementById('hosting-count').textContent = '0';
            }
        }

        // Load recent services
        async function loadRecentServices() {
            try {
                const response = await fetch('/api/billing/get_recent_services.php');
                const data = await response.json();
                
                const container = document.getElementById('recent-services');
                
                if (data.success && data.services && data.services.length > 0) {
                    container.innerHTML = data.services.map(service => `
                        <div class="service-item">
                            <div class="service-info">
                                <h4>${escapeHtml(service.name || 'Послуга')}</h4>
                                <p>Оплачено до: ${escapeHtml(service.expires_at || 'Невідомо')}</p>
                            </div>
                            <span class="status-badge ${service.status === 'active' ? 'status-active' : 'status-expired'}">
                                ${service.status === 'active' ? 'Активно' : 'Закінчилось'}
                            </span>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>Немає активних послуг</p>
                            <a href="https://sthost.pro" class="btn-primary">Замовити послуги</a>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent services:', error);
                document.getElementById('recent-services').innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-circle"></i>
                        <p>Помилка завантаження даних</p>
                    </div>
                `;
            }
        }

        // Load payment history
        async function loadPaymentHistory() {
            try {
                const response = await fetch('/api/billing/get_payment_history.php');
                const data = await response.json();
                
                const container = document.getElementById('payment-history');
                
                if (data.success && data.payments && data.payments.length > 0) {
                    container.innerHTML = data.payments.slice(0, 5).map(payment => `
                        <div class="service-item">
                            <div class="service-info">
                                <h4>${parseFloat(payment.amount || 0).toFixed(2)} ₴</h4>
                                <p>${escapeHtml(payment.date || 'Невідомо')}</p>
                            </div>
                            <span class="status-badge status-active">
                                Оплачено
                            </span>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>Немає платежів</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading payment history:', error);
                document.getElementById('payment-history').innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-circle"></i>
                        <p>Помилка завантаження даних</p>
                    </div>
                `;
            }
        }

        // Initialize charts
        function initCharts() {
            // Expenses Chart
            const expensesCtx = document.getElementById('expensesChart');
            if (expensesCtx) {
                new Chart(expensesCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Січ', 'Лют', 'Бер', 'Кві', 'Тра', 'Чер'],
                        datasets: [{
                            label: 'Витрати (₴)',
                            data: [0, 0, 0, 0, 0, 0],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(148, 163, 184, 0.1)' },
                                ticks: { color: '#94a3b8' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#94a3b8' }
                            }
                        }
                    }
                });
            }

            // Services Chart
            const servicesCtx = document.getElementById('servicesChart');
            if (servicesCtx) {
                new Chart(servicesCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Домени', 'VPS', 'Хостинг'],
                        datasets: [{
                            data: [0, 0, 0],
                            backgroundColor: ['#667eea', '#764ba2', '#f59e0b'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#94a3b8',
                                    padding: 20
                                }
                            }
                        }
                    }
                });
            }
        }

        // Show notifications
        function showNotifications() {
            alert('Функція сповіщень буде доступна незабаром!');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-update every 10 seconds
        setInterval(() => {
            loadBillingData();
            loadServicesCount();
            loadRecentServices();
            loadPaymentHistory();
        }, 10000);

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadBillingData();
            loadServicesCount();
            loadRecentServices();
            loadPaymentHistory();
            initCharts();
        });

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
</parameter>
</invoke>