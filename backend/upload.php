<?php
// upload.php - Обработчик загрузки изображений с конвертацией в WebP

require_once 'config.php';

// Только для авторизованных администраторов
requireAdmin();

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed. Use POST'], 405);
}

// Проверка наличия файла
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Image file is required'], 400);
}

// Проверка размера файла
if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
    jsonResponse(['error' => 'File too large. Max size: 5MB'], 400);
}

// Создание папки для загрузок
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        jsonResponse(['error' => 'Failed to create upload directory'], 500);
    }
    chmod(UPLOAD_DIR, 0755);
}

// Получение информации о файле
$tmpPath = $_FILES['image']['tmp_name'];
$imageInfo = getimagesize($tmpPath);

if (!$imageInfo) {
    jsonResponse(['error' => 'Invalid image file'], 400);
}

$mimeType = $imageInfo['mime'];
$width = $imageInfo[0];
$height = $imageInfo[1];

// Создание ресурса GD в зависимости от типа
$resource = null;

switch ($mimeType) {
    case 'image/jpeg':
    case 'image/jpg':
        $resource = imagecreatefromjpeg($tmpPath);
        break;
    
    case 'image/png':
        $resource = imagecreatefrompng($tmpPath);
        if ($resource && !imageistruecolor($resource)) {
            imagepalettetotruecolor($resource);
        }
        imagealphablending($resource, false);
        imagesavealpha($resource, true);
        break;
    
    case 'image/gif':
        $resource = imagecreatefromgif($tmpPath);
        break;
    
    case 'image/webp':
        $resource = imagecreatefromwebp($tmpPath);
        break;
    
    default:
        jsonResponse(['error' => 'Unsupported image format. Allowed: JPEG, PNG, GIF, WebP'], 400);
}

if (!$resource) {
    jsonResponse(['error' => 'Failed to create image resource'], 500);
}

// Генерация безопасного имени
$fileName = bin2hex(random_bytes(16)) . '.webp';
$filePath = UPLOAD_DIR . $fileName;

// Сохранение в WebP с качеством 80%
$quality = 80;
if (!imagewebp($resource, $filePath, $quality)) {
    imagedestroy($resource);
    jsonResponse(['error' => 'Failed to save WebP image'], 500);
}

// Освобождение памяти
imagedestroy($resource);

// Установка прав
chmod($filePath, 0644);

// Возврат успешного результата
jsonResponse([
    'success' => true,
    'file_name' => $fileName,
    'file_url' => UPLOAD_URL . $fileName
]);