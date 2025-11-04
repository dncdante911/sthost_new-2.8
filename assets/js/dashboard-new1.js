/**
 * ============================================
 * DASHBOARD JAVASCRIPT - StormHosting UA
 * Современный JS для панели управления
 * ============================================
 */

// ============================================
// ГЛАВНЫЙ КЛАСС ДАШБОАРДА
// ============================================
class StormDashboard {
    constructor() {
        this.isInitialized = false;
        this.updateInterval = null;
        this.notifications = [];
        
        // Конфигурация
        this.config = {
            updateInterval: 30000, // 30 секунд
            apiBaseUrl: '/api',
            notificationTimeout: 5000
        };
        
        this.init();
    }
    
    /**
     * Инициализация дашбоарда
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('🚀 Initializing StormHosting Dashboard...');
        
        // Инициализация компонентов
        this.initVPSControls();
        this.initNotifications();
        this.initTooltips();
        this.initAutoUpdates();
        this.initAnimations();
        
        // События
        this.bindEvents();
        
        this.isInitialized = true;
        console.log('✅ Dashboard initialized successfully!');
    }
    
    /**
     * Инициализация VPS управления
     */
    initVPSControls() {
        // Кнопки управления VPS
        const vpsButtons = document.querySelectorAll('[data-vps-id]');
        
        vpsButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const vpsId = button.dataset.vpsId;
                const action = this.getActionFromButton(button);
                
                if (vpsId && action) {
                    this.controlVPS(vpsId, action);
                }
            });
        });
        
        console.log(`📡 VPS controls initialized for ${vpsButtons.length} buttons`);
    }
    
    /**
     * Управление VPS
     */
    async controlVPS(vpsId, action) {
        const button = document.querySelector(`[data-vps-id="${vpsId}"].vps-${action}`);
        if (!button) return;
        
        // Показываем состояние загрузки
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
        button.disabled = true;
        
        // Подтверждение для критических действий
        if (action === 'stop') {
            const confirmed = await this.showConfirmation(
                'Остановить VPS?',
                `Вы уверены, что хотите остановить VPS?`,
                'warning'
            );
            if (!confirmed) {
                button.innerHTML = originalText;
                button.disabled = false;
                return;
            }
        }
        
        try {
            const response = await fetch('/client/vps/api/control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    vps_id: vpsId,
                    action: action,
                    csrf_token: this.getCSRFToken()
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('success', `VPS ${action} выполнен успешно!`);
                
                // Обновляем статус VPS через 2 секунды
                setTimeout(() => this.updateVPSStatus(vpsId), 2000);
                
                // Логируем действие для 2FA (если будет активировано)
                this.logSecurityAction(`vps_${action}`, { vps_id: vpsId });
                
            } else {
                throw new Error(data.message || 'Произошла ошибка при выполнении операции');
            }
            
        } catch (error) {
            console.error('VPS control error:', error);
            this.showNotification('error', error.message || 'Ошибка при управлении VPS');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }
    
    /**
     * Обновление статуса VPS
     */
    async updateVPSStatus(vpsId) {
        try {
            const response = await fetch(`/client/vps/api/status.php?id=${vpsId}`);
            const data = await response.json();
            
            if (data.success) {
                const statusElement = document.querySelector(`[data-vps-id="${vpsId}"]`)
                    ?.closest('.vps-item')
                    ?.querySelector('.status-badge');
                
                if (statusElement) {
                    statusElement.className = `status-badge status-${data.status}`;
                    statusElement.textContent = data.status;
                }
                
                // Обновляем статистику
                this.updateDashboardStats();
            }
        } catch (error) {
            console.error('Status update error:', error);
        }
    }
    
    /**
     * Определение действия по кнопке
     */
    getActionFromButton(button) {
        if (button.classList.contains('vps-start')) return 'start';
        if (button.classList.contains('vps-stop')) return 'stop';
        if (button.classList.contains('vps-restart')) return 'restart';
        return null;
    }
    
    /**
     * Система уведомлений
     */
    initNotifications() {
        // Создаем контейнер для уведомлений
        if (!document.getElementById('notification-container')) {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
    }
    
    /**
     * Показать уведомление
     */
    showNotification(type, message, duration = null) {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        const id = 'notification-' + Date.now();
        
        notification.id = id;
        notification.className = `notification notification-${type} notification-enter`;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        notification.innerHTML = `
            <div class="notification-content">
                <i class="bi bi-${icons[type] || 'info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="dashboard.closeNotification('${id}')">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.classList.remove('notification-enter');
        }, 100);
        
        // Автоматическое закрытие
        const timeout = duration || this.config.notificationTimeout;
        setTimeout(() => {
            this.closeNotification(id);
        }, timeout);
        
        this.notifications.push({ id, element: notification });
    }
    
    /**
     * Закрыть уведомление
     */
    closeNotification(id) {
        const notification = document.getElementById(id);
        if (!notification) return;
        
        notification.classList.add('notification-exit');
        
        setTimeout(() => {
            notification.remove();
            this.notifications = this.notifications.filter(n => n.id !== id);
        }, 300);
    }
    
    /**
     * Показать диалог подтверждения
     */
    async showConfirmation(title, message, type = 'info') {
        return new Promise((resolve) => {
            // Создаем модальное окно
            const modal = document.createElement('div');
            modal.className = 'confirmation-modal';
            modal.innerHTML = `
                <div class="confirmation-overlay"></div>
                <div class="confirmation-dialog">
                    <div class="confirmation-header">
                        <h5>${title}</h5>
                    </div>
                    <div class="confirmation-body">
                        <p>${message}</p>
                    </div>
                    <div class="confirmation-actions">
                        <button class="btn btn-secondary" onclick="closeConfirmation(false)">Отмена</button>
                        <button class="btn btn-${type === 'warning' ? 'danger' : 'primary'}" onclick="closeConfirmation(true)">Подтвердить</button>
                    </div>
                </div>
            `;
            
            // Функция закрытия
            window.closeConfirmation = (result) => {
                modal.remove();
                delete window.closeConfirmation;
                resolve(result);
            };
            
            document.body.appendChild(modal);
        });
    }
    
    /**
     * Инициализация тултипов
     */
    initTooltips() {
        // Инициализация Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    /**
     * Автообновление данных
     */
    initAutoUpdates() {
        // Обновляем статистику каждые 30 секунд
        this.updateInterval = setInterval(() => {
            this.updateDashboardStats();
        }, this.config.updateInterval);
        
        console.log('🔄 Auto-updates enabled');
    }
    
    /**
     * Обновление статистики дашбоарда
     */
    async updateDashboardStats() {
        try {
            const response = await fetch('/api/dashboard/stats.php');
            const data = await response.json();
            
            if (data.success) {
                // Обновляем счетчики
                this.updateStatsCounters(data.stats);
                
                // Обновляем баланс
                if (data.stats.balance !== undefined) {
                    const balanceEl = document.querySelector('.balance-amount');
                    if (balanceEl) {
                        balanceEl.textContent = `${data.stats.balance.toFixed(2)} грн`;
                    }
                }
            }
        } catch (error) {
            console.error('Stats update error:', error);
        }
    }
    
    /**
     * Обновление счетчиков статистики
     */
    updateStatsCounters(stats) {
        const counters = {
            'vps': stats.vps,
            'domains': stats.domains,
            'hosting': stats.hosting,
            'invoices': stats.pending_invoices
        };
        
        Object.entries(counters).forEach(([key, value]) => {
            const counter = document.querySelector(`.stats-${key} h3`);
            if (counter && counter.textContent != value) {
                this.animateCounter(counter, parseInt(counter.textContent) || 0, value);
            }
        });
    }
    
    /**
     * Анимация счетчиков
     */
    animateCounter(element, from, to) {
        const duration = 1000;
        const steps = 30;
        const stepValue = (to - from) / steps;
        const stepDuration = duration / steps;
        
        let current = from;
        let step = 0;
        
        const timer = setInterval(() => {
            step++;
            current += stepValue;
            
            if (step >= steps) {
                current = to;
                clearInterval(timer);
            }
            
            element.textContent = Math.round(current);
        }, stepDuration);
    }
    
    /**
     * Инициализация анимаций
     */
    initAnimations() {
        // Анимация появления карточек при скролле
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);
        
        // Наблюдаем за карточками
        document.querySelectorAll('.content-card, .stats-card').forEach(card => {
            observer.observe(card);
        });
    }
    
    /**
     * Привязка событий
     */
    bindEvents() {
        // Обработка клавиатурных сочетаний
        document.addEventListener('keydown', (e) => {
            // Ctrl + R - обновить статистику
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.updateDashboardStats();
                this.showNotification('info', 'Статистика обновлена');
            }
        });
        
        // Обработка видимости страницы
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Страница скрыта - останавливаем обновления
                if (this.updateInterval) {
                    clearInterval(this.updateInterval);
                }
            } else {
                // Страница видима - возобновляем обновления
                this.initAutoUpdates();
                this.updateDashboardStats();
            }
        });
        
        // Обработка ошибок сети
        window.addEventListener('online', () => {
            this.showNotification('success', 'Соединение восстановлено');
            this.updateDashboardStats();
        });
        
        window.addEventListener('offline', () => {
            this.showNotification('warning', 'Соединение потеряно', 10000);
        });
    }
    
    /**
     * Получение CSRF токена
     */
    getCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return token || '';
    }
    
    /**
     * Логирование действий безопасности (для будущей SMS 2FA)
     */
    logSecurityAction(action, details = {}) {
        // Закомментировано для будущей реализации SMS 2FA
        /*
        fetch('/api/security/log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: action,
                details: details,
                timestamp: new Date().toISOString(),
                csrf_token: this.getCSRFToken()
            })
        }).catch(console.error);
        */
        console.log('Security action logged:', action, details);
    }
    
    /**
     * Проверка требования 2FA (закомментировано)
     */
    async checkRequire2FA(action) {
        // Будущая реализация SMS 2FA
        /*
        const criticalActions = ['vps_stop', 'vps_restart', 'password_change', 'delete_vps'];
        
        if (criticalActions.includes(action)) {
            try {
                const response = await fetch('/api/security/require-2fa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, csrf_token: this.getCSRFToken() })
                });
                
                const data = await response.json();
                
                if (data.require_2fa) {
                    return await this.show2FADialog();
                }
            } catch (error) {
                console.error('2FA check error:', error);
            }
        }
        */
        
        return true; // Пока всегда разрешаем
    }
    
    /**
     * Диалог 2FA (закомментировано)
     */
    async show2FADialog() {
        /*
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'sms-2fa-modal';
            modal.innerHTML = `
                <div class="modal-overlay"></div>
                <div class="modal-dialog">
                    <div class="modal-header">
                        <h5>Подтверждение по SMS</h5>
                    </div>
                    <div class="modal-body">
                        <p>Для выполнения этого действия введите код из SMS:</p>
                        <input type="text" class="form-control" id="sms-code" placeholder="Код из SMS" maxlength="6">
                        <div class="sms-status mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="close2FA(false)">Отмена</button>
                        <button class="btn btn-primary" onclick="verify2FA()">Подтвердить</button>
                    </div>
                </div>
            `;
            
            window.close2FA = (result) => {
                modal.remove();
                delete window.close2FA;
                delete window.verify2FA;
                resolve(result);
            };
            
            window.verify2FA = async () => {
                const code = document.getElementById('sms-code').value;
                if (code.length !== 6) {
                    document.querySelector('.sms-status').innerHTML = 
                        '<div class="text-danger">Введите 6-значный код</div>';
                    return;
                }
                
                // Проверка кода через API
                try {
                    const response = await fetch('/api/security/verify-2fa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code, csrf_token: this.getCSRFToken() })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        close2FA(true);
                    } else {
                        document.querySelector('.sms-status').innerHTML = 
                            '<div class="text-danger">Неверный код</div>';
                    }
                } catch (error) {
                    document.querySelector('.sms-status').innerHTML = 
                        '<div class="text-danger">Ошибка проверки кода</div>';
                }
            };
            
            document.body.appendChild(modal);
            setTimeout(() => document.getElementById('sms-code').focus(), 100);
        });
        */
        return true;
    }
    
    /**
     * Уничтожение дашбоарда
     */
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        // Закрываем все уведомления
        this.notifications.forEach(notification => {
            this.closeNotification(notification.id);
        });
        
        this.isInitialized = false;
        console.log('🔥 Dashboard destroyed');
    }
}

// ============================================
// ДОПОЛНИТЕЛЬНЫЕ УТИЛИТЫ
// ============================================

/**
 * Утилиты для работы с API
 */
class DashboardAPI {
    static async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
    
    static async getVPSList() {
        return await this.request('/client/vps/api/list.php');
    }
    
    static async getVPSStats(vpsId) {
        return await this.request(`/client/vps/api/stats.php?id=${vpsId}`);
    }
    
    static async controlVPS(vpsId, action) {
        return await this.request('/client/vps/api/control.php', {
            method: 'POST',
            body: JSON.stringify({ vps_id: vpsId, action: action })
        });
    }
}

/**
 * Менеджер производительности
 */
class PerformanceManager {
    static init() {
        // Prefetch важных страниц
        this.prefetchPages();
        
        // Мониторинг производительности
        this.monitorPerformance();
    }
    
    static prefetchPages() {
        const importantPages = [
            '/client/vps/',
            '/client/profile.php',
            '/pages/vps.php'
        ];
        
        importantPages.forEach(page => {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = page;
            document.head.appendChild(link);
        });
    }
    
    static monitorPerformance() {
        if ('performance' in window) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        console.log(`📊 Page load: ${Math.round(perfData.loadEventEnd - perfData.loadEventStart)}ms`);
                        console.log(`📊 DOM ready: ${Math.round(perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart)}ms`);
                    }
                }, 0);
            });
        }
    }
}

// ============================================
// CSS для уведомлений и модальных окон
// ============================================
const notificationStyles = `
<style>
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    padding: 16px;
    min-width: 300px;
    max-width: 400px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.notification-success {
    border-left-color: #10b981;
}

.notification-error {
    border-left-color: #ef4444;
}

.notification-warning {
    border-left-color: #f59e0b;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.notification-content i {
    font-size: 1.25rem;
}

.notification-success i {
    color: #10b981;
}

.notification-error i {
    color: #ef4444;
}

.notification-warning i {
    color: #f59e0b;
}

.notification-info i {
    color: #3b82f6;
}

.notification-close {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: color 0.2s;
}

.notification-close:hover {
    color: #6b7280;
    background: #f3f4f6;
}

.notification-enter {
    transform: translateX(100%);
    opacity: 0;
}

.notification-exit {
    transform: translateX(100%);
    opacity: 0;
}

.confirmation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.confirmation-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.confirmation-dialog {
    background: white;
    border-radius: 16px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 500px;
    width: 90%;
    position: relative;
    z-index: 1;
}

.confirmation-header {
    padding: 24px 24px 0;
}

.confirmation-header h5 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.confirmation-body {
    padding: 16px 24px 24px;
    color: #6b7280;
}

.confirmation-actions {
    padding: 0 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-in {
    animation: slideInUp 0.6s ease forwards;
}

@media (max-width: 768px) {
    .notification-container {
        left: 20px;
        right: 20px;
    }
    
    .notification {
        min-width: auto;
        max-width: none;
    }
    
    .confirmation-dialog {
        margin: 20px;
        width: calc(100% - 40px);
    }
}
</style>
`;

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎯 Initializing StormHosting Dashboard...');
    
    // Добавляем стили для уведомлений
    document.head.insertAdjacentHTML('beforeend', notificationStyles);
    
    // Инициализируем дашбоард
    window.dashboard = new StormDashboard();
    
    // Инициализируем менеджер производительности
    PerformanceManager.init();
    
    // Экспортируем API для глобального доступа
    window.DashboardAPI = DashboardAPI;
    
    console.log('🎉 Dashboard fully loaded and ready!');
    console.log(`
    🚀 StormHosting UA Dashboard
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Version: 2.0
    Features: VPS Management, Real-time Updates, Notifications
    Status: ✅ Active
    
    Available commands:
    - dashboard.updateDashboardStats() - Update statistics
    - dashboard.showNotification(type, message) - Show notification
    - DashboardAPI.getVPSList() - Get VPS list via API
    `);
});

// ============================================
// ОБРАБОТКА ОШИБОК
// ============================================
window.addEventListener('error', (event) => {
    console.error('Dashboard Error:', {
        message: event.message,
        source: event.filename,
        line: event.lineno,
        column: event.colno,
        error: event.error
    });
    
    if (window.dashboard) {
        window.dashboard.showNotification('error', 'Произошла ошибка в интерфейсе');
    }
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled Promise Rejection:', event.reason);
    
    if (window.dashboard) {
        window.dashboard.showNotification('error', 'Ошибка при выполнении операции');
    }
});

// ============================================
// ОЧИСТКА ПРИ УХОДЕ СО СТРАНИЦЫ
// ============================================
window.addEventListener('beforeunload', () => {
    if (window.dashboard) {
        window.dashboard.destroy();
    }
});

// ============================================
// УТИЛИТЫ ДЛЯ ОТЛАДКИ
// ============================================
if (typeof console !== 'undefined' && console.log) {
    // Easter egg для разработчиков
    console.log(`
    %c🌟 StormHosting UA - Advanced Dashboard
    %c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Made with ❤️ by StormHosting Development Team
    
    Если вы видите это сообщение, значит вы разработчик! 👨‍💻
    Присоединяйтесь к нашей команде: jobs@sthost.pro
    `,
    'color: #667eea; font-size: 16px; font-weight: bold;',
    'color: #764ba2; font-size: 12px;'
    );
}