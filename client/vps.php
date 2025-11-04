<?php
/**
 * Сторінка управління VPS
 * Файл: /client/vps.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

// Перевірка авторізації
requireLogin('/client/vps.php');

$user_id = getUserId();
$user_email = getUserEmail();
$user_name = getUserName();

$page_title = 'Управління VPS - STHost';
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
        
        .vps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        .vps-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .vps-card::before {
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
        
        .vps-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        
        .vps-card:hover::before {
            opacity: 1;
        }
        
        .vps-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .vps-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .vps-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-running {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-stopped {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .vps-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .spec-item i {
            color: var(--primary-color);
        }
        
        .spec-value {
            font-weight: 600;
        }
        
        .vps-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
        
        .btn-action {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-action:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
        }
        
        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-action.btn-start:hover {
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-action.btn-stop:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .btn-action.btn-restart:hover {
            border-color: var(--warning);
            color: var(--warning);
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
        
        /* VNC Console Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
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
            max-width: 90%;
            width: 1200px;
            max-height: 90vh;
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
        
        .vnc-container {
            width: 100%;
            height: 600px;
            background: #000;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
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
            
            .vps-grid {
                grid-template-columns: 1fr;
            }
            
            .vps-specs {
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
                <a href="/client/vps.php" class="nav-link active">
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
                    <li class="breadcrumb-item active">VPS сервери</li>
                </ol>
            </nav>
            
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h1>Мої VPS сервери</h1>
                <button class="btn-primary" onclick="window.location.href='https://sthost.pro'">
                    <i class="bi bi-plus-lg"></i> Замовити VPS
                </button>
            </div>
        </div>

        <!-- VPS Grid -->
        <div class="vps-grid" id="vpsGrid">
            <div class="loading" style="grid-column: 1/-1;">
                <div class="spinner"></div>
            </div>
        </div>
    </main>

    <!-- VNC Console Modal -->
    <div class="modal-overlay" id="vncModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">VNC Консоль - <span id="vncServerName"></span></h3>
                <button class="btn-close" onclick="closeVncModal()">&times;</button>
            </div>
            <div class="vnc-container" id="vncContainer">
                <div style="text-align: center;">
                    <i class="bi bi-display" style="font-size: 48px; margin-bottom: 1rem;"></i>
                    <p>VNC консоль буде доступна після повної інтеграції з Libvirt</p>
                    <p style="font-size: 14px; margin-top: 1rem;">
                        Порти VNC: 5900-5999<br>
                        Підключення: qemu+tcp://192.168.0.4/system
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let vpsServers = [];

        // Toggle sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('mobile-visible');
        }

        // Load VPS list
        async function loadVPS() {
            try {
                const response = await fetch('/api/vps/get_list.php');
                const data = await response.json();
                
                const grid = document.getElementById('vpsGrid');
                
                if (data.success && data.servers && data.servers.length > 0) {
                    vpsServers = data.servers;
                    displayVPS(vpsServers);
                } else {
                    grid.innerHTML = `
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <i class="bi bi-server"></i>
                            <h3>У вас немає VPS серверів</h3>
                            <p>Замовте потужний VPS сервер прямо зараз!</p>
                            <button class="btn-primary" onclick="window.location.href='https://sthost.pro'">
                                Замовити VPS
                            </button>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading VPS:', error);
                document.getElementById('vpsGrid').innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="bi bi-exclamation-circle"></i>
                        <h3>Помилка завантаження</h3>
                        <p>Спробуйте оновити сторінку</p>
                    </div>
                `;
            }
        }

        // Display VPS
        function displayVPS(servers) {
            const grid = document.getElementById('vpsGrid');
            
            grid.innerHTML = servers.map(server => {
                const isPaid = server.is_paid;
                const statusClass = server.status === 'running' ? 'status-running' : 
                                   server.status === 'stopped' ? 'status-stopped' : 'status-pending';
                const statusText = server.status === 'running' ? 'Запущено' :
                                  server.status === 'stopped' ? 'Зупинено' : 'Очікування';
                
                return `
                    <div class="vps-card">
                        <div class="vps-header">
                            <div>
                                <div class="vps-name">${escapeHtml(server.name)}</div>
                                <small style="color: var(--text-secondary);">${escapeHtml(server.ip || 'IP не призначено')}</small>
                            </div>
                            <span class="vps-status ${statusClass}">
                                <span class="status-indicator"></span>
                                ${statusText}
                            </span>
                        </div>
                        
                        <div class="vps-specs">
                            <div class="spec-item">
                                <i class="bi bi-cpu"></i>
                                <span><span class="spec-value">${server.cpu}</span> vCPU</span>
                            </div>
                            <div class="spec-item">
                                <i class="bi bi-memory"></i>
                                <span><span class="spec-value">${server.ram}</span> GB RAM</span>
                            </div>
                            <div class="spec-item">
                                <i class="bi bi-hdd"></i>
                                <span><span class="spec-value">${server.disk}</span> GB SSD</span>
                            </div>
                            <div class="spec-item">
                                <i class="bi bi-hdd-network"></i>
                                <span><span class="spec-value">${escapeHtml(server.os || 'Ubuntu')}</span></span>
                            </div>
                        </div>
                        
                        <div class="vps-actions">
                            <button class="btn-action btn-start" 
                                    onclick="controlVPS(${server.id}, 'start')" 
                                    ${!isPaid || server.status === 'running' ? 'disabled' : ''}>
                                <i class="bi bi-play-fill"></i> Старт
                            </button>
                            <button class="btn-action btn-stop" 
                                    onclick="controlVPS(${server.id}, 'stop')" 
                                    ${!isPaid || server.status === 'stopped' ? 'disabled' : ''}>
                                <i class="bi bi-stop-fill"></i> Стоп
                            </button>
                            <button class="btn-action btn-restart" 
                                    onclick="controlVPS(${server.id}, 'restart')" 
                                    ${!isPaid ? 'disabled' : ''}>
                                <i class="bi bi-arrow-clockwise"></i> Перезапуск
                            </button>
                            <button class="btn-action" 
                                    onclick="openVNC(${server.id}, '${escapeHtml(server.name)}')" 
                                    ${!isPaid ? 'disabled' : ''}>
                                <i class="bi bi-display"></i> Консоль
                            </button>
                            <button class="btn-action" 
                                    onclick="window.open('https://bill.sthost.pro', '_blank')">
                                <i class="bi bi-gear"></i> Керування
                            </button>
                            <button class="btn-action" 
                                    onclick="window.open('https://bill.sthost.pro', '_blank')"
                                    ${isPaid ? 'disabled' : ''}>
                                <i class="bi bi-credit-card"></i> ${isPaid ? 'Оплачено' : 'Оплатити'}
                            </button>
                        </div>
                        ${!isPaid ? '<p style="color: var(--warning); font-size: 12px; margin-top: 1rem; text-align: center;"><i class="bi bi-exclamation-triangle"></i> Оплатіть для активації</p>' : ''}
                    </div>
                `;
            }).join('');
        }

        // Control VPS (start/stop/restart)
        async function controlVPS(serverId, action) {
            if (!confirm(`Ви впевнені що хочете виконати дію: ${action}?`)) {
                return;
            }
            
            try {
                const response = await fetch('/api/vps/control.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        server_id: serverId,
                        action: action
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`Команда ${action} виконана успішно!`);
                    // Перезавантажуємо список через 2 секунди
                    setTimeout(loadVPS, 2000);
                } else {
                    alert(`Помилка: ${data.message || 'Невідома помилка'}`);
                }
            } catch (error) {
                console.error('Error controlling VPS:', error);
                alert('Помилка виконання команди');
            }
        }

        // Open VNC console
        function openVNC(serverId, serverName) {
            document.getElementById('vncServerName').textContent = serverName;
            document.getElementById('vncModal').classList.add('show');
            
            // TODO: Підключення до VNC через WebSocket або noVNC
            // Тут буде код для підключення до VNC консолі
        }

        // Close VNC modal
        function closeVncModal() {
            document.getElementById('vncModal').classList.remove('show');
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('vncModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'vncModal') {
                closeVncModal();
            }
        });

        // Auto refresh every 30 seconds
        setInterval(loadVPS, 30000);

        // Initialize
        document.addEventListener('DOMContentLoaded', loadVPS);
    </script>
</body>
</html>
</parameter>
</invoke>