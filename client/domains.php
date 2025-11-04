<?php
/**
 * Сторінка управління доменами
 * Файл: /client/domains.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

// Перевірка авторізації
requireLogin('/client/domains.php');

$user_id = getUserId();
$user_email = getUserEmail();
$user_name = getUserName();

$page_title = 'Управління доменами - STHost';
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
        
        /* Sidebar - той самий що і на dashboard */
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
        
        .breadcrumb-item.active {
            color: var(--text-primary);
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .search-input {
            flex: 1;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .domains-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .domain-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .domain-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        
        .domain-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .domain-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .domain-status {
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
        
        .domain-info {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 1rem;
        }
        
        .domain-info p {
            margin: 0.5rem 0;
        }
        
        .domain-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            flex: 1;
            min-width: 120px;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-action:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }
        
        .btn-close {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }
        
        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
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
            
            .domains-grid {
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
                <a href="/client/domains.php" class="nav-link active">
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
        <!-- Page Header -->
        <div class="page-header">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/client/dashboard-new.php">Головна</a></li>
                    <li class="breadcrumb-item active">Домени</li>
                </ol>
            </nav>
            
            <h1>Мої домени</h1>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="Пошук домену...">
            <button class="btn-primary" onclick="window.location.href='https://sthost.pro'">
                <i class="bi bi-plus-lg"></i> Замовити домен
            </button>
        </div>

        <!-- Domains Grid -->
        <div class="domains-grid" id="domainsGrid">
            <div class="loading">
                <div class="spinner"></div>
            </div>
        </div>
    </main>

    <!-- DNS Management Modal -->
    <div class="modal-overlay" id="dnsModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Управління DNS</h3>
                <button class="btn-close" onclick="closeDnsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Домен</label>
                    <input type="text" class="form-control" id="modalDomain" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">DNS записи будуть доступні через ISPmanager</label>
                    <a href="https://cp.sthost.pro" target="_blank" class="btn-primary" style="display: block; text-align: center;">
                        Відкрити ISPmanager <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 1rem;">
                    <i class="bi bi-info-circle"></i> 
                    Для управління DNS записами використовуйте ISPmanager панель керування
                </p>
            </div>
        </div>
    </div>

    <script>
        let allDomains = [];

        // Toggle sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('mobile-visible');
        }

        // Load domains
        async function loadDomains() {
            try {
                const response = await fetch('/api/domains/get_list.php');
                const data = await response.json();
                
                const grid = document.getElementById('domainsGrid');
                
                if (data.success && data.domains && data.domains.length > 0) {
                    allDomains = data.domains;
                    displayDomains(allDomains);
                } else {
                    grid.innerHTML = `
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <i class="bi bi-globe2"></i>
                            <h3>У вас немає доменів</h3>
                            <p>Замовте свій перший домен прямо зараз!</p>
                            <button class="btn-primary" onclick="window.location.href='https://sthost.pro'">
                                Замовити домен
                            </button>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading domains:', error);
                document.getElementById('domainsGrid').innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="bi bi-exclamation-circle"></i>
                        <h3>Помилка завантаження</h3>
                        <p>Спробуйте оновити сторінку</p>
                    </div>
                `;
            }
        }

        // Display domains
        function displayDomains(domains) {
            const grid = document.getElementById('domainsGrid');
            
            grid.innerHTML = domains.map(domain => `
                <div class="domain-card">
                    <div class="domain-header">
                        <div>
                            <div class="domain-name">${escapeHtml(domain.name)}</div>
                        </div>
                        <span class="domain-status ${domain.status === 'active' ? 'status-active' : 'status-expired'}">
                            ${domain.status === 'active' ? 'Активний' : 'Закінчився'}
                        </span>
                    </div>
                    
                    <div class="domain-info">
                        <p><i class="bi bi-calendar-event"></i> Зареєстровано: ${escapeHtml(domain.registered_at)}</p>
                        <p><i class="bi bi-calendar-x"></i> Закінчується: ${escapeHtml(domain.expires_at)}</p>
                        ${domain.auto_renewal ? '<p><i class="bi bi-arrow-repeat"></i> Автопродовження увімкнено</p>' : ''}
                    </div>
                    
                    <div class="domain-actions">
                        <button class="btn-action" onclick="manageDns('${escapeHtml(domain.name)}')">
                            <i class="bi bi-diagram-3"></i> DNS
                        </button>
                        <button class="btn-action" onclick="window.open('https://cp.sthost.pro', '_blank')">
                            <i class="bi bi-gear"></i> Керування
                        </button>
                        <button class="btn-action" onclick="window.open('https://bill.sthost.pro', '_blank')">
                            <i class="bi bi-arrow-clockwise"></i> Продовжити
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Search domains
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const filtered = allDomains.filter(domain => 
                domain.name.toLowerCase().includes(searchTerm)
            );
            displayDomains(filtered);
        });

        // Manage DNS
        function manageDns(domainName) {
            document.getElementById('modalDomain').value = domainName;
            document.getElementById('dnsModal').classList.add('show');
        }

        // Close DNS modal
        function closeDnsModal() {
            document.getElementById('dnsModal').classList.remove('show');
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('dnsModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'dnsModal') {
                closeDnsModal();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', loadDomains);
    </script>
</body>
</html>
</parameter>
</invoke>