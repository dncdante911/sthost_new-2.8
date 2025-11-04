/**
 * ============================================
 * VPS MANAGEMENT JAVASCRIPT - StormHosting UA
 * JavaScript для управления VPS серверами
 * ============================================
 */

// ============================================
// ГЛАВНЫЙ КЛАСС УПРАВЛЕНИЯ VPS
// ============================================
class VPSManager {
    constructor() {
        this.isInitialized = false;
        this.updateInterval = null;
        this.notifications = [];
        
        // Конфигурация
        this.config = {
            updateInterval: 15000, // 15 секунд
            apiBaseUrl: '/client/vps/api',
            confirmCriticalActions: true
        };
        
        this.init();
    }
    
    /**
     * Инициализация
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('🚀 Initializing VPS Management System...');
        
        // Инициализация компонентов
        this.initVPSControls();
        this.initNotifications();
        this.initModals();
        this.initAutoUpdates();
        this.initTooltips();
        
        // Привязка событий
        this.bindEvents();
        
        this.isInitialized = true;
        console.log('✅ VPS Management System initialized!');
    }
    
    /**
     * Инициализация VPS управления
     */
    initVPSControls() {
        // Кнопки действий VPS
        document.querySelectorAll('.vps-action-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                const vpsId = button.dataset.vpsId;
                const action = button.dataset.action;
                
                if (vpsId && action) {
                    this.executeVPSAction(vpsId, action, button);
                }
            });
        });
        
        // Кнопка обновления списка VPS
        const refreshButton = document.querySelector('[onclick="refreshVPSList()"]');
        if (refreshButton) {
            refreshButton.removeAttribute('onclick');
            refreshButton.addEventListener('click', () => this.refreshVPSList());
        }
        
        console.log('📡 VPS controls initialized');
    }
    
    /**
     * Выполнение действия VPS
     */
    async executeVPSAction(vpsId, action, button) {
        // Проверяем критические действия
        if (this.config.confirmCriticalActions) {
            const criticalActions = ['stop', 'restart', 'reset_password'];
            if (criticalActions.includes(action)) {
                const confirmed = await this.showConfirmation(
                    this.getActionTitle(action),
                    this.getActionMessage(action),
                    'warning'
                );
                
                if (!confirmed) return;
            }
        }
        
        // Показываем состояние загрузки
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
        button.disabled = true;
        button.classList.add('btn-loading');
        
        try {
            const response = await this.apiRequest('/control.php', {
                method: 'POST',
                body: JSON.stringify({
                    vps_id: parseInt(vpsId),
                    action: action,
                    csrf_token: this.getCSRFToken()
                })
            });
            
            if (response.success) {
                this.showNotification('success', response.message || `Действие "${action}" выполнено успешно`);
                
                // Обновляем статус VPS
                setTimeout(() => {
                    this.updateVPSStatus(vpsId);
                    this.updateStats();
                }, 2000);
                
                // Логируем успешное действие
                this.logAction('vps_action_success', {
                    vps_id: vpsId,
                    action: action
                });
                
            } else {
                throw new Error(response.message || 'Неизвестная ошибка');
            }
            
        } catch (error) {
            console.error('VPS action error:', error);
            this.showNotification('error', error.message || 'Ошибка при выполнении действия');
            
            // Логируем ошибку
            this.logAction('vps_action_error', {
                vps_id: vpsId,
                action: action,
                error: error.message
            });
            
        } finally {
            button.innerHTML = originalHTML;
            button.disabled = false;
            button.classList.remove('btn-loading');
        }
    }
    
    /**
     * Обновление статуса VPS
     */
    async updateVPSStatus(vpsId) {
        try {
            const response = await this.apiRequest(`/status.php?id=${vpsId}`);
            
            if (response.success && response.vps) {
                const vpsCard = document.querySelector(`[data-vps-id="${vpsId}"]`);
                if (!vpsCard) return;
                
                // Обновляем статус
                const statusBadge = vpsCard.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = `status-badge status-${response.vps.status}`;
                    statusBadge.textContent = response.vps.status;
                }
                
                // Обновляем состояние питания
                const powerStatus = vpsCard.querySelector('.power-status');
                if (powerStatus) {
                    powerStatus.className = `power-status power-${response.vps.power_state}`;
                    powerStatus.textContent = response.vps.power_state;
                }
                
                // Обновляем кнопки действий
                this.updateActionButtons(vpsCard, response.vps);
                
                // Обновляем использование ресурсов
                if (response.vps.resource_usage) {
                    this.updateResourceUsage(vpsCard, response.vps.resource_usage);
                }
            }
            
        } catch (error) {
            console.error('Status update error:', error);
        }
    }
    
    /**
     * Обновление кнопок действий
     */
    updateActionButtons(vpsCard, vpsData) {
        const startBtn = vpsCard.querySelector('[data-action="start"]');
        const stopBtn = vpsCard.querySelector('[data-action="stop"]');
        const restartBtn = vpsCard.querySelector('[data-action="restart"]');
        
        const isRunning = vpsData.power_state === 'running';
        const isStopped = vpsData.power_state === 'stopped';
        
        if (startBtn) startBtn.disabled = isRunning;
        if (stopBtn) stopBtn.disabled = isStopped;
        if (restartBtn) restartBtn.disabled = isStopped;
    }
    
    /**
     * Обновление использования ресурсов
     */
    updateResourceUsage(vpsCard, usage) {
        const usageSection = vpsCard.querySelector('.vps-usage');
        if (!usageSection) return;
        
        // Обновляем CPU
        const cpuFill = usageSection.querySelector('.usage-item:first-child .usage-fill');
        const cpuValue = usageSection.querySelector('.usage-item:first-child .usage-value');
        if (cpuFill && cpuValue) {
            cpuFill.style.width = `${usage.cpu_usage}%`;
            cpuValue.textContent = `${Math.round(usage.cpu_usage)}%`;
        }
        
        // Обновляем RAM
        const ramFill = usageSection.querySelector('.usage-item:last-child .usage-fill');
        const ramValue = usageSection.querySelector('.usage-item:last-child .usage-value');
        if (ramFill && ramValue) {
            ramFill.style.width = `${usage.memory_usage}%`;
            ramValue.textContent = `${Math.round(usage.memory_usage)}%`;
        }
    }
    
    /**
     * Обновление списка VPS
     */
    async refreshVPSList() {
        const refreshBtn = document.querySelector('[onclick="refreshVPSList()"]') || 
                          document.querySelector('button[onclick="refreshVPSList()"]');
        
        if (refreshBtn) {
            const originalHTML = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Обновление...';
            refreshBtn.disabled = true;
        }
        
        try {
            // Получаем обновленный список VPS
            const response = await this.apiRequest('/list.php');
            
            if (response.success) {
                // Обновляем каждый VPS
                for (const vps of response.vps_list) {
                    await this.updateVPSStatus(vps.id);
                }
                
                // Обновляем статистику
                this.updateStats();
                
                this.showNotification('success', 'Список VPS обновлен');
            } else {
                throw new Error(response.message || 'Ошибка обновления списка');
            }
            
        } catch (error) {
            console.error('Refresh error:', error);
            this.showNotification('error', 'Ошибка при обновлении списка VPS');
            
        } finally {
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Обновить';
                refreshBtn.disabled = false;
            }
        }
    }
    
    /**
     * Инициализация модальных окон
     */
    initModals() {
        // Форма создания VPS
        const createForm = document.getElementById('createVPSForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => this.handleCreateVPS(e));
        }
        
        // Валидация hostname в реальном времени
        const hostnameInput = document.getElementById('hostname');
        if (hostnameInput) {
            hostnameInput.addEventListener('input', (e) => this.validateHostname(e.target));
        }
        
        console.log('🎭 Modals initialized');
    }
    
    /**
     * Обработка создания VPS
     */
    async handleCreateVPS(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Валидация формы
        if (!this.validateCreateForm(form)) {
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalHTML = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Создание...';
        submitBtn.disabled = true;
        
        try {
            const data = {
                plan_id: parseInt(formData.get('plan_id')),
                os_template_id: parseInt(formData.get('os_template_id')),
                hostname: formData.get('hostname').trim(),
                root_password: formData.get('root_password') || null,
                csrf_token: this.getCSRFToken()
            };
            
            const response = await this.apiRequest('/create.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            if (response.success) {
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('createVPSModal'));
                modal.hide();
                
                // Очищаем форму
                form.reset();
                
                this.showNotification('success', 'VPS успешно создан! Процесс установки может занять несколько минут.');
                
                // Перезагружаем список VPS через 5 секунд
                setTimeout(() => {
                    window.location.reload();
                }, 5000);
                
            } else {
                throw new Error(response.message || 'Ошибка создания VPS');
            }
            
        } catch (error) {
            console.error('Create VPS error:', error);
            this.showNotification('error', error.message || 'Ошибка при создании VPS');
            
        } finally {
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
        }
    }
    
    /**
     * Валидация формы создания VPS
     */
    validateCreateForm(form) {
        let isValid = true;
        
        // Проверяем выбор плана
        const planRadios = form.querySelectorAll('input[name="plan_id"]');
        const isPlanSelected = Array.from(planRadios).some(radio => radio.checked);
        if (!isPlanSelected) {
            this.showNotification('error', 'Выберите тарифный план');
            isValid = false;
        }
        
        // Проверяем выбор ОС
        const osRadios = form.querySelectorAll('input[name="os_template_id"]');
        const isOSSelected = Array.from(osRadios).some(radio => radio.checked);
        if (!isOSSelected) {
            this.showNotification('error', 'Выберите операционную систему');
            isValid = false;
        }
        
        // Проверяем hostname
        const hostnameInput = form.querySelector('#hostname');
        if (!this.validateHostname(hostnameInput)) {
            isValid = false;
        }
        
        // Проверяем согласие с условиями
        const agreeCheckbox = form.querySelector('#agree_terms');
        if (!agreeCheckbox.checked) {
            this.showNotification('error', 'Необходимо согласиться с условиями использования');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Валидация hostname
     */
    validateHostname(input) {
        const hostname = input.value.trim();
        const pattern = /^[a-zA-Z0-9-]+$/;
        
        // Убираем предыдущие ошибки
        input.classList.remove('is-invalid');
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
        
        if (!hostname) {
            this.addInputError(input, 'Имя хоста обязательно');
            return false;
        }
        
        if (hostname.length < 3) {
            this.addInputError(input, 'Имя хоста должно содержать минимум 3 символа');
            return false;
        }
        
        if (hostname.length > 63) {
            this.addInputError(input, 'Имя хоста не может быть длиннее 63 символов');
            return false;
        }
        
        if (!pattern.test(hostname)) {
            this.addInputError(input, 'Имя хоста может содержать только буквы, цифры и дефисы');
            return false;
        }
        
        if (hostname.startsWith('-') || hostname.endsWith('-')) {
            this.addInputError(input, 'Имя хоста не может начинаться или заканчиваться дефисом');
            return false;
        }
        
        input.classList.add('is-valid');
        return true;
    }
    
    /**
     * Добавление ошибки к полю ввода
     */
    addInputError(input, message) {
        input.classList.add('is-invalid');
        
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        
        input.parentNode.appendChild(feedback);
    }
    
    /**
     * Инициализация системы уведомлений
     */
    initNotifications() {
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
    showNotification(type, message, duration = 5000) {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        const id = 'notification-' + Date.now();
        
        notification.id = id;
        notification.className = `notification notification-${type} notification-enter`;
        
        const icons = {
            success: 'check-circle-fill',
            error: 'exclamation-triangle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };
        
        notification.innerHTML = `
            <div class="notification-content">
                <i class="bi bi-${icons[type]}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="vpsManager.closeNotification('${id}')">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.classList.remove('notification-enter');
        }, 100);
        
        // Автоматическое закрытие
        setTimeout(() => {
            this.closeNotification(id);
        }, duration);
        
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
     * Диалог подтверждения
     */
    async showConfirmation(title, message, type = 'info') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirmation-modal';
            modal.innerHTML = `
                <div class="confirmation-overlay"></div>
                <div class="confirmation-dialog">
                    <div class="confirmation-header">
                        <h5 class="confirmation-title">${title}</h5>
                    </div>
                    <div class="confirmation-body">
                        <p>${message}</p>
                    </div>
                    <div class="confirmation-actions">
                        <button class="btn btn-secondary" onclick="closeConfirmation(false)">Отмена</button>
                        <button class="btn btn-${type === 'warning' ? 'danger' : 'primary'}" onclick="closeConfirmation(true)">
                            Подтвердить
                        </button>
                    </div>
                </div>
            `;
            
            window.closeConfirmation = (result) => {
                modal.remove();
                delete window.closeConfirmation;
                resolve(result);
            };
            
            document.body.appendChild(modal);
        });
    }
    
    /**
     * Инициализация автообновлений
     */
    initAutoUpdates() {
        // Автообновление статусов VPS каждые 15 секунд
        this.updateInterval = setInterval(() => {
            this.refreshVPSStatuses();
        }, this.config.updateInterval);
        
        console.log('🔄 Auto-updates enabled');
    }
    
    /**
     * Автообновление статусов VPS
     */
    async refreshVPSStatuses() {
        const vpsCards = document.querySelectorAll('[data-vps-id]');
        
        for (const card of vpsCards) {
            const vpsId = card.dataset.vpsId;
            if (vpsId) {
                await this.updateVPSStatus(vpsId);
            }
        }
    }
    
    /**
     * Обновление статистики
     */
    async updateStats() {
        try {
            const response = await this.apiRequest('/stats.php');
            
            if (response.success && response.stats) {
                // Обновляем статистические карточки
                this.updateStatsCards(response.stats);
            }
            
        } catch (error) {
            console.error('Stats update error:', error);
        }
    }
    
    /**
     * Обновление статистических карточек
     */
    updateStatsCards(stats) {
        const cards = {
            'total': stats.total || 0,
            'running': stats.running || 0,
            'stopped': stats.stopped || 0,
            'resources': stats.total_cpu || 0
        };
        
        Object.entries(cards).forEach(([key, value]) => {
            const card = document.querySelector(`.stats-${key} h3`);
            if (card) {
                this.animateCounter(card, parseInt(card.textContent) || 0, value);
            }
        });
        
        // Обновляем дополнительную информацию о ресурсах
        const resourcesCard = document.querySelector('.stats-resources small');
        if (resourcesCard && stats.total_ram_gb) {
            resourcesCard.textContent = `${stats.total_ram_gb.toFixed(1)} GB RAM`;
        }
    }
    
    /**
     * Анимация счетчиков
     */
    animateCounter(element, from, to) {
        if (from === to) return;
        
        const duration = 1000;
        const steps = 20;
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
     * Инициализация подсказок
     */
    initTooltips() {
        // Инициализация Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Добавляем подсказки к кнопкам действий
        document.querySelectorAll('.vps-action-btn').forEach(button => {
            if (!button.hasAttribute('title')) {
                const action = button.dataset.action;
                const titles = {
                    'start': 'Запустить VPS',
                    'stop': 'Остановить VPS',
                    'restart': 'Перезагрузить VPS',
                    'reset_password': 'Сбросить пароль root'
                };
                
                if (titles[action]) {
                    button.setAttribute('title', titles[action]);
                    button.setAttribute('data-bs-toggle', 'tooltip');
                    new bootstrap.Tooltip(button);
                }
            }
        });
    }
    
    /**
     * Привязка событий
     */
    bindEvents() {
        // Обработка видимости страницы
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Останавливаем автообновления
                if (this.updateInterval) {
                    clearInterval(this.updateInterval);
                }
            } else {
                // Возобновляем автообновления
                this.initAutoUpdates();
                this.refreshVPSStatuses();
            }
        });
        
        // Обработка горячих клавиш
        document.addEventListener('keydown', (e) => {
            // F5 или Ctrl+R - обновить список VPS
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                this.refreshVPSList();
            }
        });
        
        // Обработка состояния сети
        window.addEventListener('online', () => {
            this.showNotification('success', 'Соединение восстановлено');
            this.refreshVPSStatuses();
        });
        
        window.addEventListener('offline', () => {
            this.showNotification('warning', 'Соединение потеряно', 10000);
        });
    }
    
    /**
     * API запрос
     */
    async apiRequest(endpoint, options = {}) {
        const url = this.config.apiBaseUrl + endpoint;
        
        const defaultOptions = {
            method: 'GET',
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
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
    
    /**
     * Получение CSRF токена
     */
    getCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token) {
            // Пытаемся получить из session storage или другого источника
            return document.querySelector('input[name="csrf_token"]')?.value || '';
        }
        return token;
    }
    
    /**
     * Логирование действий
     */
    logAction(action, details = {}) {
        const logData = {
            action: action,
            details: details,
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent,
            url: window.location.href
        };
        
        // Отправляем лог на сервер (опционально)
        fetch('/api/log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(logData)
        }).catch(console.error);
        
        console.log('Action logged:', logData);
    }
    
    /**
     * Получение заголовка действия
     */
    getActionTitle(action) {
        const titles = {
            'stop': 'Остановить VPS?',
            'restart': 'Перезагрузить VPS?',
            'reset_password': 'Сбросить пароль?'
        };
        
        return titles[action] || 'Подтвердите действие';
    }
    
    /**
     * Получение сообщения действия
     */
    getActionMessage(action) {
        const messages = {
            'stop': 'Вы уверены, что хотите остановить VPS? Все работающие процессы будут завершены.',
            'restart': 'Вы уверены, что хотите перезагрузить VPS? Это может временно прервать работу сервисов.',
            'reset_password': 'Вы уверены, что хотите сбросить пароль root? Новый пароль будет отправлен на email.'
        };
        
        return messages[action] || 'Это действие нельзя будет отменить.';
    }
    
    /**
     * Уничтожение менеджера
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
        console.log('🔥 VPS Manager destroyed');
    }
}

// ============================================
// ГЛОБАЛЬНЫЕ ФУНКЦИИ
// ============================================

/**
 * Показать модальное окно снапшотов
 */
function showSnapshotModal(vpsId) {
    const modal = new bootstrap.Modal(document.getElementById('snapshotModal'));
    const content = document.getElementById('snapshot-content');
    
    // Показываем загрузку
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-2">Загрузка снапшотов...</p>
        </div>
    `;
    
    modal.show();
    
    // Загружаем содержимое через AJAX
    fetch(`/client/vps/api/snapshots.php?vps_id=${vpsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = generateSnapshotHTML(data.snapshots, vpsId);
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Ошибка загрузки снапшотов: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Ошибка загрузки: ${error.message}
                </div>
            `;
        });
}

/**
 * Генерация HTML для снапшотов
 */
function generateSnapshotHTML(snapshots, vpsId) {
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Управление снапшотами</h6>
            <button class="btn btn-sm btn-primary" onclick="createSnapshot(${vpsId})">
                <i class="bi bi-camera"></i> Создать
            </button>
        </div>
    `;
    
    if (snapshots.length === 0) {
        html += `
            <div class="text-center text-muted py-4">
                <i class="bi bi-camera" style="font-size: 2rem;"></i>
                <p class="mt-2">Снапшоты не найдены</p>
            </div>
        `;
    } else {
        html += '<div class="list-group">';
        
        snapshots.forEach(snapshot => {
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${snapshot.name}</h6>
                            <p class="mb-1 text-muted small">${snapshot.description || 'Без описания'}</p>
                            <small class="text-muted">Создан: ${new Date(snapshot.created_at).toLocaleString()}</small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="restoreSnapshot(${snapshot.id}, '${snapshot.name}')">
                                <i class="bi bi-arrow-counterclockwise"></i> Восстановить
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteSnapshot(${snapshot.id}, '${snapshot.name}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    return html;
}

/**
 * Создание снапшота
 */
async function createSnapshot(vpsId) {
    const name = prompt('Введите имя снапшота:');
    if (!name) return;
    
    try {
        const response = await vpsManager.apiRequest('/control.php', {
            method: 'POST',
            body: JSON.stringify({
                vps_id: vpsId,
                action: 'create_snapshot',
                snapshot_name: name,
                csrf_token: vpsManager.getCSRFToken()
            })
        });
        
        if (response.success) {
            vpsManager.showNotification('success', 'Снапшот создается...');
            // Перезагружаем модальное окно
            setTimeout(() => showSnapshotModal(vpsId), 2000);
        } else {
            throw new Error(response.message);
        }
        
    } catch (error) {
        vpsManager.showNotification('error', error.message);
    }
}

/**
 * Восстановление снапшота
 */
async function restoreSnapshot(snapshotId, snapshotName) {
    const confirmed = await vpsManager.showConfirmation(
        'Восстановить снапшот?',
        `Вы уверены, что хотите восстановить снапшот "${snapshotName}"? Текущее состояние VPS будет потеряно.`,
        'warning'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await vpsManager.apiRequest('/control.php', {
            method: 'POST',
            body: JSON.stringify({
                vps_id: null, // Получим из снапшота
                action: 'restore_snapshot',
                snapshot_id: snapshotId,
                csrf_token: vpsManager.getCSRFToken()
            })
        });
        
        if (response.success) {
            vpsManager.showNotification('success', 'Снапшот восстанавливается...');
            // Закрываем модальное окно
            const modal = bootstrap.Modal.getInstance(document.getElementById('snapshotModal'));
            modal.hide();
        } else {
            throw new Error(response.message);
        }
        
    } catch (error) {
        vpsManager.showNotification('error', error.message);
    }
}

/**
 * Удаление снапшота
 */
async function deleteSnapshot(snapshotId, snapshotName) {
    const confirmed = await vpsManager.showConfirmation(
        'Удалить снапшот?',
        `Вы уверены, что хотите удалить снапшот "${snapshotName}"? Это действие нельзя отменить.`,
        'warning'
    );
    
    if (!confirmed) return;
    
    // Реализация удаления снапшота
    vpsManager.showNotification('info', 'Функция удаления снапшотов будет реализована в ближайшее время');
}

/**
 * Подтверждение удаления VPS
 */
async function confirmDeleteVPS(vpsId) {
    const confirmed = await vpsManager.showConfirmation(
        'Удалить VPS?',
        'Вы уверены, что хотите удалить VPS? Все данные будут потеряны безвозвратно!',
        'warning'
    );
    
    if (confirmed) {
        vpsManager.showNotification('info', 'Функция удаления VPS будет реализована после интеграции с биллингом');
    }
}

// ============================================
// CSS ДЛЯ УВЕДОМЛЕНИЙ
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
    min-width: 320px;
    max-width: 400px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.notification-success { border-left-color: #10b981; }
.notification-error { border-left-color: #ef4444; }
.notification-warning { border-left-color: #f59e0b; }

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.notification-success i { color: #10b981; }
.notification-error i { color: #ef4444; }
.notification-warning i { color: #f59e0b; }
.notification-info i { color: #3b82f6; }

.notification-close {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
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

.confirmation-title {
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
</style>
`;

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎯 Initializing VPS Management...');
    
    // Добавляем стили для уведомлений
    document.head.insertAdjacentHTML('beforeend', notificationStyles);
    
    // Инициализируем VPS Manager
    window.vpsManager = new VPSManager();
    
    console.log('🎉 VPS Management fully loaded!');
});

// ============================================
// ОЧИСТКА ПРИ УХОДЕ СО СТРАНИЦЫ
// ============================================
window.addEventListener('beforeunload', () => {
    if (window.vpsManager) {
        window.vpsManager.destroy();
    }
});

// ============================================
// ОБРАБОТКА ОШИБОК
// ============================================
window.addEventListener('error', (event) => {
    console.error('VPS Management Error:', event.error);
    if (window.vpsManager) {
        window.vpsManager.showNotification('error', 'Произошла ошибка в интерфейсе');
    }
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled Promise Rejection:', event.reason);
    if (window.vpsManager) {
        window.vpsManager.showNotification('error', 'Ошибка при выполнении операции');
    }
});