<?php
/**
 * Розширена сторінка налаштувань акаунту з аватаркою
 * Файл: /client/settings.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

// Перевірка авторізації
requireLogin('/client/settings.php');

$user_id = getUserId();
$user_email = getUserEmail();
$user_name = getUserName();

// Отримуємо дані користувача
$user_info = DatabaseConnection::fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
);

$page_title = 'Налаштування акаунту - STHost';
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
        
        .settings-container {
            max-width: 900px;
        }
        
        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .settings-card h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            font-size: 20px;
        }
        
        .settings-card h3 i {
            color: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .form-control, .form-select {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-select {
            cursor: pointer;
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
            font-size: 16px;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            border-radius: 10px;
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--card-bg);
            border-color: var(--primary-color);
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert.show {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        /* Avatar Upload Section */
        .avatar-upload-section {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .avatar-container {
            position: relative;
        }
        
        .avatar-display {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            overflow: hidden;
            border: 3px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .avatar-display:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }
        
        .avatar-display img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: -10px;
            right: -10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .avatar-upload-btn:hover {
            background: var(--primary-hover);
            transform: scale(1.1);
        }
        
        .avatar-upload-btn i {
            color: white;
        }
        
        #avatarInput {
            display: none;
        }
        
        .avatar-info {
            flex: 1;
            min-width: 250px;
        }
        
        .avatar-info h4 {
            margin-bottom: 0.5rem;
        }
        
        .avatar-info p {
            color: var(--text-secondary);
            margin: 0.25rem 0;
            font-size: 14px;
        }
        
        .avatar-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-card i {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        /* Notifications Settings */
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--border-color);
        }
        
        .notification-info h5 {
            margin: 0 0 0.25rem 0;
            font-size: 16px;
        }
        
        .notification-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--hover-bg);
            transition: 0.3s;
            border-radius: 30px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .danger-zone {
            border: 2px solid var(--danger);
            border-radius: 16px;
            padding: 2rem;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .danger-zone h4 {
            color: var(--danger);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        /* Session Management */
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--border-color);
        }
        
        .session-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .session-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--hover-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .session-current {
            padding: 4px 12px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
            
            .avatar-upload-section {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-row {
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
                <a href="/client/settings.php" class="nav-link active">
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
                    <li class="breadcrumb-item active">Налаштування</li>
                </ol>
            </nav>
            
            <h1>Налаштування акаунту</h1>
        </div>

        <div class="settings-container">
            <!-- Alerts -->
            <div class="alert alert-success" id="successAlert">
                <i class="bi bi-check-circle"></i> <span id="successMessage"></span>
            </div>
            <div class="alert alert-danger" id="errorAlert">
                <i class="bi bi-exclamation-circle"></i> <span id="errorMessage"></span>
            </div>

            <!-- Account Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <i class="bi bi-calendar-check"></i>
                    <div class="stat-value"><?php echo date('d.m.Y', strtotime($user_info['created_at'] ?? 'now')); ?></div>
                    <div class="stat-label">Дата реєстрації</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-clock-history"></i>
                    <div class="stat-value"><?php echo date('d.m.Y H:i', strtotime($user_info['last_login'] ?? 'now')); ?></div>
                    <div class="stat-label">Останній вхід</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-shield-check"></i>
                    <div class="stat-value">Активний</div>
                    <div class="stat-label">Статус акаунту</div>
                </div>
            </div>

            <!-- Profile with Avatar -->
            <div class="settings-card">
                <h3>
                    <i class="bi bi-person-circle"></i>
                    Профіль користувача
                </h3>
                
                <div class="avatar-upload-section">
                    <div class="avatar-container">
                        <div class="avatar-display" id="avatarDisplay">
                            <?php 
                            if (!empty($user_info['avatar'])) {
                                echo '<img src="' . htmlspecialchars($user_info['avatar']) . '" alt="Avatar">';
                            } else {
                                echo strtoupper(substr($user_email, 0, 1));
                            }
                            ?>
                        </div>
                        <label for="avatarInput" class="avatar-upload-btn">
                            <i class="bi bi-camera"></i>
                        </label>
                        <input type="file" id="avatarInput" accept="image/*" onchange="previewAvatar(event)">
                    </div>
                    
                    <div class="avatar-info">
                        <h4><?php echo htmlspecialchars($user_info['full_name'] ?? $user_name); ?></h4>
                        <p><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user_email); ?></p>
                        <p><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user_info['phone'] ?? 'Не вказано'); ?></p>
                        
                        <div class="avatar-actions">
                            <button class="btn-primary" onclick="uploadAvatar()" id="uploadAvatarBtn" style="display: none;">
                                <i class="bi bi-upload"></i> Завантажити
                            </button>
                            <button class="btn-secondary" onclick="removeAvatar()" <?php echo empty($user_info['avatar']) ? 'style="display:none;"' : ''; ?> id="removeAvatarBtn">
                                <i class="bi bi-trash"></i> Видалити
                            </button>
                        </div>
                    </div>
                </div>

                <form id="profileForm">
                    <div class="form-group">
                        <label class="form-label">Повне ім'я</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email (не можна змінити)</label>
                        <input type="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="tel" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" 
                               placeholder="+380XXXXXXXXX">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Мова інтерфейсу</label>
                        <select class="form-select" name="language">
                            <option value="ua" <?php echo ($user_info['language'] ?? 'ua') === 'ua' ? 'selected' : ''; ?>>Українська</option>
                            <option value="en" <?php echo ($user_info['language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ru" <?php echo ($user_info['language'] ?? '') === 'ru' ? 'selected' : ''; ?>>Русский</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="bi bi-save"></i> Зберегти зміни
                    </button>
                </form>
            </div>

            <!-- Notification Settings -->
            <div class="settings-card">
                <h3>
                    <i class="bi bi-bell"></i>
                    Налаштування сповіщень
                </h3>

                <div class="notification-item">
                    <div class="notification-info">
                        <h5>Email сповіщення</h5>
                        <p>Отримувати повідомлення про послуги та рахунки на email</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked onchange="toggleNotification('email')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="notification-item">
                    <div class="notification-info">
                        <h5>Маркетингові розсилки</h5>
                        <p>Новини, акції та спеціальні пропозиції від STHost</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" onchange="toggleNotification('marketing')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="notification-item">
                    <div class="notification-info">
                        <h5>Технічні повідомлення</h5>
                        <p>Оновлення системи, технічні роботи та важливі новини</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Password Change -->
            <div class="settings-card">
                <h3>
                    <i class="bi bi-shield-lock"></i>
                    Зміна пароля
                </h3>

                <form id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Поточний пароль</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Новий пароль</label>
                        <input type="password" class="form-control" name="new_password" 
                               minlength="8" required id="newPassword">
                        <small style="color: var(--text-secondary); font-size: 13px;">
                            Мінімум 8 символів, включаючи великі та малі літери, цифри
                        </small>
                        <div id="passwordStrength" style="margin-top: 0.5rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Підтвердіть новий пароль</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               minlength="8" required>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="bi bi-key"></i> Змінити пароль
                    </button>
                </form>
            </div>

            <!-- Active Sessions -->
            <div class="settings-card">
                <h3>
                    <i class="bi bi-laptop"></i>
                    Активні сесії
                </h3>

                <div class="session-item">
                    <div class="session-info">
                        <div class="session-icon">
                            <i class="bi bi-laptop"></i>
                        </div>
                        <div>
                            <h5 style="margin: 0 0 0.25rem 0;">Поточний пристрій</h5>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">
                                <?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'Невідомо'; ?>
                            </p>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">
                                IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Невідомо'; ?>
                            </p>
                        </div>
                    </div>
                    <span class="session-current">Поточна</span>
                </div>

                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 1rem;">
                    <i class="bi bi-info-circle"></i> 
                    Якщо ви помітили підозрілу активність, негайно змініть пароль
                </p>
            </div>

            <!-- Danger Zone -->
            <div class="settings-card">
                <div class="danger-zone">
                    <h4>
                        <i class="bi bi-exclamation-triangle"></i>
                        Небезпечна зона
                    </h4>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Видалення акаунту призведе до видалення всіх ваших даних без можливості відновлення.
                        Це включає всі домени, VPS, хостинг-пакети та історію платежів.
                    </p>
                    <button class="btn-danger" onclick="confirmDeleteAccount()">
                        <i class="bi bi-trash"></i> Видалити акаунт назавжди
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        let selectedAvatarFile = null;

        // Toggle sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('mobile-visible');
        }

        // Show alert
        function showAlert(type, message) {
            const alertId = type === 'success' ? 'successAlert' : 'errorAlert';
            const messageId = type === 'success' ? 'successMessage' : 'errorMessage';
            
            document.getElementById(messageId).textContent = message;
            document.getElementById(alertId).classList.add('show');
            
            setTimeout(() => {
                document.getElementById(alertId).classList.remove('show');
            }, 5000);
        }

        // Preview avatar
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Перевірка типу файлу
            if (!file.type.startsWith('image/')) {
                showAlert('error', 'Будь ласка, виберіть зображення');
                return;
            }
            
            // Перевірка розміру (макс 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', 'Розмір файлу не повинен перевищувати 5MB');
                return;
            }
            
            selectedAvatarFile = file;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarDisplay = document.getElementById('avatarDisplay');
                avatarDisplay.innerHTML = `<img src="${e.target.result}" alt="Avatar">`;
                document.getElementById('uploadAvatarBtn').style.display = 'inline-block';
            };
            reader.readAsDataURL(file);
        }

        // Upload avatar
        async function uploadAvatar() {
            if (!selectedAvatarFile) {
                showAlert('error', 'Виберіть файл');
                return;
            }
            
            const formData = new FormData();
            formData.append('avatar', selectedAvatarFile);
            
            const uploadBtn = document.getElementById('uploadAvatarBtn');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Завантаження...';
            
            try {
                const response = await fetch('/api/user/upload_avatar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Аватар успішно завантажено!');
                    document.getElementById('removeAvatarBtn').style.display = 'inline-block';
                    document.getElementById('uploadAvatarBtn').style.display = 'none';
                    selectedAvatarFile = null;
                } else {
                    showAlert('error', result.message || 'Помилка завантаження аватара');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Помилка з\'єднання з сервером');
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Завантажити';
            }
        }

        // Remove avatar
        async function removeAvatar() {
            if (!confirm('Ви впевнені що хочете видалити аватар?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/user/remove_avatar.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Аватар успішно видалено!');
                    const avatarDisplay = document.getElementById('avatarDisplay');
                    const firstLetter = '<?php echo strtoupper(substr($user_email, 0, 1)); ?>';
                    avatarDisplay.innerHTML = firstLetter;
                    document.getElementById('removeAvatarBtn').style.display = 'none';
                } else {
                    showAlert('error', result.message || 'Помилка видалення аватара');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Помилка з\'єднання з сервером');
            }
        }

        // Update profile
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('/api/user/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Профіль успішно оновлено!');
                } else {
                    showAlert('error', result.message || 'Помилка оновлення профілю');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Помилка з\'єднання з сервером');
            }
        });

        // Password strength checker
        document.getElementById('newPassword')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let color = '';
            let text = '';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                color = 'var(--danger)';
                text = 'Слабкий пароль';
            } else if (strength <= 3) {
                color = 'var(--warning)';
                text = 'Середній пароль';
            } else {
                color = 'var(--success)';
                text = 'Сильний пароль';
            }
            
            strengthDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="flex: 1; height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px;">
                        <div style="width: ${strength * 20}%; height: 100%; background: ${color}; border-radius: 2px; transition: all 0.3s;"></div>
                    </div>
                    <span style="color: ${color}; font-size: 13px; font-weight: 600;">${text}</span>
                </div>
            `;
        });

        // Change password
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            
            // Перевірка паролів
            if (data.new_password !== data.confirm_password) {
                showAlert('error', 'Паролі не співпадають!');
                return;
            }
            
            // Перевірка складності пароля
            if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(data.new_password)) {
                showAlert('error', 'Пароль повинен містити великі і малі літери та цифри');
                return;
            }
            
            try {
                const response = await fetch('/api/user/change_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Пароль успішно змінено!');
                    e.target.reset();
                    document.getElementById('passwordStrength').innerHTML = '';
                } else {
                    showAlert('error', result.message || 'Помилка зміни пароля');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Помилка з\'єднання з сервером');
            }
        });

        // Toggle notifications
        function toggleNotification(type) {
            // TODO: Implement notification settings save
            console.log('Toggle notification:', type);
        }

        // Delete account confirmation
        function confirmDeleteAccount() {
            if (confirm('⚠️ УВАГА! Ви впевнені що хочете видалити акаунт?\n\nЦе призведе до:\n- Видалення всіх доменів\n- Видалення всіх VPS серверів\n- Видалення всіх хостинг-пакетів\n- Видалення історії платежів\n- Втрати всіх даних без можливості відновлення\n\nПродовжити?')) {
                const email = prompt('Для підтвердження введіть ваш email: <?php echo $user_email; ?>');
                if (email === '<?php echo $user_email; ?>') {
                    deleteAccount();
                } else {
                    showAlert('error', 'Email не співпадає');
                }
            }
        }

        // Delete account
        async function deleteAccount() {
            try {
                const response = await fetch('/api/user/delete_account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Акаунт успішно видалено. Дякуємо що були з нами!');
                    window.location.href = '/';
                } else {
                    showAlert('error', result.message || 'Помилка видалення акаунту');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Помилка з\'єднання з сервером');
            }
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
</parameter>
</invoke>