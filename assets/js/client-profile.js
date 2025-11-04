/**
 * ============================================
 * /assets/js/client-profile.js
 * Функционал для страницы профиля (client/profile.php)
 * Включает обработку табов и логику форм.
 * ============================================
 */

document.addEventListener('DOMContentLoaded', function() {
    // --- 1. Обработка переключения вкладок (если используются табы Bootstrap) ---
    const tabTriggers = document.querySelectorAll('#profileTabs button');

    tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            const targetId = this.getAttribute('data-bs-target');
            
            // Удаляем класс 'active' со всех кнопок и контента
            document.querySelectorAll('#profileTabs button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show', 'active'));

            // Добавляем класс 'active' к нажатой кнопке
            this.classList.add('active');

            // Показываем целевой контент
            const targetPane = document.querySelector(targetId);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
        });
    });

    // --- 2. Обработка формы смены пароля ---
    const passwordForm = document.getElementById('passwordChangeForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                alert('Новый пароль и его подтверждение не совпадают!');
                return;
            }

            // Проверка сложности пароля (пример)
            if (newPassword.length < 8) {
                alert('Пароль должен быть не менее 8 символов.');
                return;
            }
            
            // Здесь должна быть логика AJAX-отправки
            alert('Попытка сменить пароль...');

            // Пример заглушки AJAX
            setTimeout(() => {
                const responseSuccess = true; // Замените на реальный ответ сервера

                if (responseSuccess) {
                    alert('Пароль успешно изменен!');
                    passwordForm.reset();
                } else {
                    alert('Ошибка при смене пароля. Пожалуйста, проверьте текущий пароль.');
                }
            }, 1000);
        });
    }

    // --- 3. Логика для загрузки аватара (если есть) ---
    const avatarInput = document.getElementById('avatarUpload');
    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (file.size > 2097152) { // 2MB limit (пример)
                    alert("Размер файла не должен превышать 2MB.");
                    this.value = ""; 
                    return;
                }
                
                // Отображение превью
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentAvatar').src = e.target.result;
                    document.getElementById('avatarSubmitBtn').disabled = false;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});