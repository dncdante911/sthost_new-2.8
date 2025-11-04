<?php
// sthost/client/vps/templates.php

define('SECURE_ACCESS', true);
session_start();

// Подключаем необходимые файлы
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/classes/VPSManager.php'; // Указываем правильный путь к менеджеру

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    header('Location: /auth/login.php');
    exit;
}

// Получаем шапку сайта
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

$vpsManager = new VPSManager($pdo);
$templatesResult = $vpsManager->getOSTemplates();
$templates = [];

if ($templatesResult['success']) {
    $templates = $templatesResult['templates'];
} else {
    // Можно обработать ошибку, если нужно
    $error_message = "Не удалось загрузить шаблоны ОС.";
}

?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-box-seam me-2"></i>Шаблоны Операционных Систем</h1>
        <a href="#" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Добавить шаблон</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="card-text">Здесь отображаются все доступные шаблоны ОС, которые можно использовать для создания новых VPS. Эти образы предварительно настроены для быстрого развертывания.</p>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php elseif (empty($templates)): ?>
                <div class="alert alert-info">Пока нет ни одного доступного шаблона ОС.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Название</th>
                                <th scope="col">Версия</th>
                                <th scope="col">Тип</th>
                                <th scope="col" class="text-center">Статус</th>
                                <th scope="col" class="text-end">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $index => $template): ?>
                                <tr>
                                    <th scope="row"><?php echo $template['id']; ?></th>
                                    <td>
                                        <i class="bi bi-ubuntu me-2 text-danger"></i> <strong><?php echo htmlspecialchars($template['display_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($template['version']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($template['type']); ?></span></td>
                                    <td class="text-center">
                                        <?php if ($template['is_active']): ?>
                                            <span class="badge bg-success">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Неактивен</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></button>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// Получаем подвал сайта
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
?>