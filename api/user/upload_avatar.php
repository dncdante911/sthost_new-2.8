<?php
/**
 * API для завантаження аватара користувача
 * Файл: /api/user/upload_avatar.php
 */

define('SECURE_ACCESS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Перевірка авторізації
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Не авторизовано']);
    exit;
}

// Тільки POST запити
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Тільки POST запити']);
    exit;
}

$user_id = getUserId();

try {
    // Перевірка чи файл завантажено
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Помилка завантаження файлу']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    
    // Перевірка типу файлу
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Дозволені тільки зображення (JPEG, PNG, GIF, WebP)']);
        exit;
    }
    
    // Перевірка розміру (макс 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Розмір файлу не повинен перевищувати 5MB']);
        exit;
    }
    
    // Створюємо директорію для аватарів якщо не існує
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Генеруємо унікальне ім'я файлу
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Завантажуємо зображення
    $image = null;
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            break;
    }
    
    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Помилка обробки зображення']);
        exit;
    }
    
    // Отримуємо розміри
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Обрізаємо до квадрату 400x400
    $size = 400;
    $thumb = imagecreatetruecolor($size, $size);
    
    // Зберігаємо прозорість для PNG
    if ($mime_type === 'image/png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    
    // Обрізаємо по центру
    $min_dimension = min($width, $height);
    $src_x = ($width - $min_dimension) / 2;
    $src_y = ($height - $min_dimension) / 2;
    
    imagecopyresampled($thumb, $image, 0, 0, $src_x, $src_y, $size, $size, $min_dimension, $min_dimension);
    
    // Зберігаємо
    switch ($mime_type) {
        case 'image/jpeg':
            imagejpeg($thumb, $filepath, 90);
            break;
        case 'image/png':
            imagepng($thumb, $filepath, 8);
            break;
        case 'image/gif':
            imagegif($thumb, $filepath);
            break;
        case 'image/webp':
            imagewebp($thumb, $filepath, 90);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($thumb);
    
    // Видаляємо старий аватар
    $old_avatar = DatabaseConnection::fetchOne(
        "SELECT avatar FROM users WHERE id = ?",
        [$user_id]
    );
    
    if (!empty($old_avatar['avatar'])) {
        $old_path = $_SERVER['DOCUMENT_ROOT'] . $old_avatar['avatar'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }
    
    // Оновлюємо шлях в БД
    $avatar_url = '/uploads/avatars/' . $filename;
    DatabaseConnection::execute(
        "UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?",
        [$avatar_url, $user_id]
    );
    
    // Логуємо
    DatabaseConnection::insert(
        "INSERT INTO security_logs (ip_address, user_id, action, details, severity) VALUES (?, ?, ?, ?, ?)",
        [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $user_id,
            'avatar_upload',
            'Завантаження нового аватара',
            'low'
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Аватар успішно завантажено',
        'avatar_url' => $avatar_url
    ]);
    
} catch (Exception $e) {
    error_log('Upload avatar error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Помилка завантаження аватара'
    ]);
}
?>