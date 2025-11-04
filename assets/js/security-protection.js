/**
 * ============================================
 * ЗАЩИТА САЙТА ОТ КОПИРОВАНИЯ И DEVTOOLS
 * StormHosting UA - Security Protection
 * ============================================
 */

(function() {
    'use strict';

    // ============================================
    // БЛОКИРОВКА ПРАВОЙ КНОПКИ МЫШИ
    // ============================================
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        showWarning('⚠️ Правая кнопка мыши отключена на этом сайте');
        return false;
    });

    // ============================================
    // БЛОКИРОВКА ГОРЯЧИХ КЛАВИШ DEVTOOLS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.keyCode === 123) {
            e.preventDefault();
            showWarning('⚠️ Инструменты разработчика отключены');
            return false;
        }

        // Ctrl+Shift+I (Инспектор)
        if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
            e.preventDefault();
            showWarning('⚠️ Инспектор элементов отключен');
            return false;
        }

        // Ctrl+Shift+J (Консоль)
        if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
            e.preventDefault();
            showWarning('⚠️ Консоль разработчика отключена');
            return false;
        }

        // Ctrl+Shift+C (Выбор элемента)
        if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
            e.preventDefault();
            showWarning('⚠️ Выбор элементов отключен');
            return false;
        }

        // Ctrl+U (Просмотр исходного кода)
        if (e.ctrlKey && e.keyCode === 85) {
            e.preventDefault();
            showWarning('⚠️ Просмотр исходного кода отключен');
            return false;
        }

        // Ctrl+S (Сохранение страницы)
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            showWarning('⚠️ Сохранение страницы отключено');
            return false;
        }

        // Ctrl+P (Печать)
        if (e.ctrlKey && e.keyCode === 80) {
            e.preventDefault();
            showWarning('⚠️ Печать страницы отключена');
            return false;
        }

        // F12 для Mac
        if (e.metaKey && e.altKey && e.keyCode === 73) {
            e.preventDefault();
            showWarning('⚠️ Инструменты разработчика отключены');
            return false;
        }
    });

    // ============================================
    // БЛОКИРОВКА ВЫДЕЛЕНИЯ ТЕКСТА
    // ============================================
    document.addEventListener('selectstart', function(e) {
        // Разрешаем выделение только в input и textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return true;
        }
        e.preventDefault();
        return false;
    });

    // ============================================
    // БЛОКИРОВКА КОПИРОВАНИЯ
    // ============================================
    document.addEventListener('copy', function(e) {
        // Разрешаем копирование только из input и textarea
        if (document.activeElement.tagName === 'INPUT' ||
            document.activeElement.tagName === 'TEXTAREA') {
            return true;
        }
        e.preventDefault();
        showWarning('⚠️ Копирование содержимого отключено');
        return false;
    });

    // ============================================
    // БЛОКИРОВКА ВЫРЕЗАНИЯ
    // ============================================
    document.addEventListener('cut', function(e) {
        if (document.activeElement.tagName === 'INPUT' ||
            document.activeElement.tagName === 'TEXTAREA') {
            return true;
        }
        e.preventDefault();
        return false;
    });

    // ============================================
    // БЛОКИРОВКА DRAG & DROP
    // ============================================
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    });

    // ============================================
    // ДЕТЕКЦИЯ DEVTOOLS (продвинутый метод)
    // ============================================
    var devtoolsOpen = false;
    var threshold = 160; // Разница в размерах окна при открытых DevTools

    // Проверка через console.log
    var element = new Image();
    Object.defineProperty(element, 'id', {
        get: function() {
            devtoolsOpen = true;
            redirectToHome();
            throw new Error('DevTools обнаружены');
        }
    });

    // Периодическая проверка размеров окна
    setInterval(function() {
        if (window.outerWidth - window.innerWidth > threshold ||
            window.outerHeight - window.innerHeight > threshold) {
            if (!devtoolsOpen) {
                devtoolsOpen = true;
                handleDevToolsOpen();
            }
        } else {
            devtoolsOpen = false;
        }
    }, 1000);

    // Проверка через отладчик
    setInterval(function() {
        var before = new Date();
        debugger;
        var after = new Date();
        if (after - before > 100) {
            handleDevToolsOpen();
        }
    }, 3000);

    // Проверка через console.log
    var checkDevTools = function() {
        if (console.log.toString().indexOf('native code') === -1) {
            handleDevToolsOpen();
        }
    };

    // Проверка через регулярные выражения
    setInterval(function() {
        try {
            console.log('%c', element);
        } catch(e) {
            // DevTools открыты
        }
    }, 1000);

    // ============================================
    // ОБРАБОТЧИК ОТКРЫТИЯ DEVTOOLS
    // ============================================
    function handleDevToolsOpen() {
        // Очищаем содержимое страницы
        document.body.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100vh;font-family:Arial;font-size:24px;color:#dc3545;text-align:center;"><div><h1>⚠️ ПРЕДУПРЕЖДЕНИЕ</h1><p>Обнаружено использование инструментов разработчика</p><p>Доступ к странице ограничен</p><button onclick="location.reload()" style="margin-top:20px;padding:10px 20px;font-size:16px;cursor:pointer;">Закрыть инструменты и обновить</button></div></div>';
    }

    // Редирект на главную при обнаружении DevTools
    function redirectToHome() {
        setTimeout(function() {
            // window.location.href = '/';
        }, 100);
    }

    // ============================================
    // ПОКАЗ ПРЕДУПРЕЖДЕНИЯ
    // ============================================
    function showWarning(message) {
        // Проверяем существует ли уже уведомление
        if (document.getElementById('security-warning')) {
            return;
        }

        var warning = document.createElement('div');
        warning.id = 'security-warning';
        warning.style.cssText = 'position:fixed;top:20px;right:20px;background:#dc3545;color:white;padding:15px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:999999;font-family:Arial,sans-serif;font-size:14px;max-width:300px;animation:slideIn 0.3s ease-out;';
        warning.textContent = message;

        // Добавляем CSS анимацию
        if (!document.getElementById('security-warning-style')) {
            var style = document.createElement('style');
            style.id = 'security-warning-style';
            style.textContent = '@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}';
            document.head.appendChild(style);
        }

        document.body.appendChild(warning);

        // Удаляем через 3 секунды
        setTimeout(function() {
            if (warning.parentNode) {
                warning.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(function() {
                    if (warning.parentNode) {
                        warning.parentNode.removeChild(warning);
                    }
                }, 300);
            }
        }, 3000);
    }

    // ============================================
    // ЗАЩИТА ОТ АВТОМАТИЧЕСКИХ СКРИПТОВ
    // ============================================

    // Блокируем автоматическое выполнение скриптов
    var originalEval = window.eval;
    window.eval = function() {
        showWarning('⚠️ Выполнение eval() заблокировано');
        return null;
    };

    // Блокируем создание новых скриптов
    var originalCreateElement = document.createElement;
    document.createElement = function(tagName) {
        if (tagName.toLowerCase() === 'script') {
            console.warn('Создание script элементов ограничено');
        }
        return originalCreateElement.call(document, tagName);
    };

    // ============================================
    // WATERMARK (водяной знак)
    // ============================================
    function addWatermark() {
        var watermark = document.createElement('div');
        watermark.style.cssText = 'position:fixed;bottom:10px;right:10px;font-size:10px;color:rgba(0,0,0,0.1);pointer-events:none;z-index:999998;user-select:none;';
        watermark.textContent = '© StormHosting UA';
        document.body.appendChild(watermark);
    }

    // Добавляем водяной знак после загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addWatermark);
    } else {
        addWatermark();
    }

    // ============================================
    // ЗАЩИТА ОТ IFRAME ВСТРАИВАНИЯ
    // ============================================
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // ============================================
    // ЗАЩИТА ИЗОБРАЖЕНИЙ
    // ============================================
    document.querySelectorAll('img').forEach(function(img) {
        img.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
    });

    // ============================================
    // КОНСОЛЬНОЕ ПРЕДУПРЕЖДЕНИЕ
    // ============================================
    console.log('%c⚠️ СТОП!', 'color: red; font-size: 50px; font-weight: bold;');
    console.log('%cЭта функция браузера предназначена для разработчиков.', 'font-size: 16px;');
    console.log('%cЕсли кто-то сказал вам скопировать и вставить что-то сюда, это мошенничество!', 'font-size: 16px; color: red;');
    console.log('%cВставка кода может предоставить злоумышленникам доступ к вашему аккаунту.', 'font-size: 16px; color: red;');
    console.log('%c© StormHosting UA - Все права защищены', 'font-size: 12px; color: #666;');

    // ============================================
    // ОТКЛЮЧЕНИЕ ОТЛАДКИ В PRODUCTION
    // ============================================
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        // Переопределяем console в production
        var noop = function() {};
        ['log', 'debug', 'info', 'warn', 'error'].forEach(function(method) {
            // console[method] = noop; // Раскомментируйте для полного отключения консоли
        });
    }

    // ============================================
    // ЗАЩИТА ОТ ТАМПЕРИНГА
    // ============================================

    // Проверка целостности критичных элементов
    setInterval(function() {
        // Проверяем наличие основных элементов страницы
        if (!document.body) {
            location.reload();
        }
    }, 5000);

})();

// ============================================
// ЭКСПОРТ (если нужен модульный подход)
// ============================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        enabled: true,
        version: '1.0.0'
    };
}
