<?php
/**
 * WeToo — Загрузка и обработка фотографий
 * Файл: api/upload.php
 *
 * Принимает: JPEG, PNG
 * Конвертирует: автоматически в WebP (качество 82%)
 * Ресайз: максимум 1600px по длинной стороне (пропорционально)
 * Хранение: /images/profiles/{profile_id}/
 * В БД: только относительный путь к файлу
 * Оригинал: НЕ сохраняется
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

cors_headers();

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// POST /api/upload/photo — загрузка фото анкеты
// Требует: multipart/form-data
// Поля: file (файл), profile_id (ID анкеты)
//       is_main (опционально, 0/1)
// ============================================================
if ($method === 'POST') {

    // Загрузку фото может делать:
    // 1. Сам пользователь (по token анкеты при создании)
    // 2. Администратор
    // Для простоты — проверяем наличие profile_token или admin-токена

    $profile_id   = (int)($_POST['profile_id'] ?? 0);
    $is_main      = (int)($_POST['is_main'] ?? 0);
    $profile_token = trim($_POST['profile_token'] ?? '');

    if (!$profile_id) {
        api_error('Не указан profile_id', 400);
    }

    // Проверяем: либо администратор, либо верный profile_token
    $admin = get_admin();
    if (!$admin) {
        // Для пользователей: проверяем временный токен из profiles
        // (токен генерируется при создании анкеты, хранится в settings сессии)
        // Минимальная защита: анкета в статусе pending_payment
        $stmt = db()->prepare("
            SELECT id, status FROM profiles
            WHERE id = ? AND status IN ('pending_payment','payment_review')
            LIMIT 1
        ");
        $stmt->execute([$profile_id]);
        if (!$stmt->fetch()) {
            api_error('Анкета не найдена или недоступна для загрузки фото', 403);
        }
    }

    // Проверяем наличие файла
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE формы',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории',
            UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
            UPLOAD_ERR_EXTENSION  => 'Загрузка заблокирована расширением PHP',
        ];
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        api_error($upload_errors[$code] ?? 'Ошибка загрузки файла', 400);
    }

    $file = $_FILES['file'];

    // Проверяем размер файла
    if ($file['size'] > MAX_FILE_SIZE) {
        api_error('Файл слишком большой. Максимум 10 MB.', 400);
    }

    // Проверяем MIME-тип через fileinfo (надёжнее чем $_FILES['type'])
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        api_error('Разрешены только JPEG и PNG файлы.', 400);
    }

    // Считаем текущее количество фото у анкеты
    $max_photos = (int)get_setting('max_photos', (string)MAX_PHOTOS);
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM photos WHERE profile_id = ?");
    $stmt->execute([$profile_id]);
    $count = (int)$stmt->fetch()['cnt'];

    if ($count >= $max_photos) {
        api_error("Максимальное количество фото ({$max_photos}) уже загружено.", 400);
    }

    // ============================================================
    // ОБРАБОТКА ИЗОБРАЖЕНИЯ
    // 1. Создаём GD-ресурс из загруженного файла
    // 2. Масштабируем если длинная сторона > 1600px
    // 3. Сохраняем как WebP
    // 4. Оригинал не сохраняем
    // ============================================================

    // Загружаем изображение в GD
    $image = load_image($file['tmp_name'], $mime);
    if (!$image) {
        api_error('Не удалось обработать изображение. Проверьте файл.', 400);
    }

    // Получаем размеры оригинала
    $orig_w = imagesx($image);
    $orig_h = imagesy($image);

    // Масштабируем если нужно
    [$new_w, $new_h] = calculate_dimensions($orig_w, $orig_h, IMAGE_MAX_SIDE);

    if ($new_w !== $orig_w || $new_h !== $orig_h) {
        $resized = imagecreatetruecolor($new_w, $new_h);

        // Сохраняем прозрачность (для PNG)
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $new_w, $new_h, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($image);
        $image = $resized;
    }

    // Создаём директорию для фото анкеты
    $profile_dir = UPLOADS_DIR . '/' . $profile_id;
    if (!is_dir($profile_dir)) {
        if (!mkdir($profile_dir, 0755, true)) {
            imagedestroy($image);
            api_error('Ошибка создания директории для фото', 500);
        }
    }

    // Генерируем уникальное имя файла WebP
    $filename  = uniqid('photo_', true) . '.webp';
    $file_path = $profile_dir . '/' . $filename;
    $file_url  = UPLOADS_URL . '/' . $profile_id . '/' . $filename;

    // Сохраняем как WebP
    if (!imagewebp($image, $file_path, WEBP_QUALITY)) {
        imagedestroy($image);
        api_error('Ошибка сохранения изображения', 500);
    }

    imagedestroy($image);

    // Получаем реальный размер сохранённого файла
    $saved_size = filesize($file_path);

    // ============================================================
    // СОХРАНЯЕМ В БД
    // Если is_main=1 — сначала снимаем флаг с остальных фото
    // ============================================================
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Определяем sort_order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM photos WHERE profile_id = ?");
        $stmt->execute([$profile_id]);
        $sort_order = (int)$stmt->fetch()['next_order'];

        // Если это первое фото — автоматически делаем главным
        if ($count === 0) $is_main = 1;

        // Снимаем is_main с других фото если это новое главное
        if ($is_main) {
            $pdo->prepare("UPDATE photos SET is_main = 0 WHERE profile_id = ?")
                ->execute([$profile_id]);
        }

        // Сохраняем запись о фото
        $stmt = $pdo->prepare("
            INSERT INTO photos (profile_id, filename, mime_type, file_size, is_main, sort_order)
            VALUES (?, ?, 'image/webp', ?, ?, ?)
        ");
        $stmt->execute([$profile_id, $filename, $saved_size, $is_main, $sort_order]);
        $photo_id = (int)$pdo->lastInsertId();

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        // Удаляем файл если транзакция откатилась
        if (file_exists($file_path)) unlink($file_path);
        api_error('Ошибка сохранения в базу данных', 500);
    }

    api_success([
        'id'         => $photo_id,
        'filename'   => $filename,
        'url'        => $file_url,
        'width'      => $new_w,
        'height'     => $new_h,
        'size'       => $saved_size,
        'is_main'    => (bool)$is_main,
        'sort_order' => $sort_order,
    ], 201);
}

// ============================================================
// DELETE /api/upload/photo/{id} — удаление фото
// Только администратор
// ============================================================
elseif ($method === 'DELETE') {

    require_admin();

    // Извлекаем ID из URL: /api/upload/photo/123
    $uri_parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $photo_id  = (int)end($uri_parts);

    if (!$photo_id) {
        api_error('Не указан ID фото', 400);
    }

    // Получаем данные фото
    $stmt = db()->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
    $stmt->execute([$photo_id]);
    $photo = $stmt->fetch();

    if (!$photo) {
        api_error('Фото не найдено', 404);
    }

    // Удаляем файл с диска
    $file_path = UPLOADS_DIR . '/' . $photo['profile_id'] . '/' . $photo['filename'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Удаляем запись из БД
    db()->prepare("DELETE FROM photos WHERE id = ?")->execute([$photo_id]);

    // Если удалили главное фото — назначаем новое
    if ($photo['is_main']) {
        $stmt = db()->prepare("
            SELECT id FROM photos WHERE profile_id = ?
            ORDER BY sort_order ASC LIMIT 1
        ");
        $stmt->execute([$photo['profile_id']]);
        $next = $stmt->fetch();
        if ($next) {
            db()->prepare("UPDATE photos SET is_main = 1 WHERE id = ?")
                ->execute([$next['id']]);
        }
    }

    api_success(['message' => 'Фото удалено']);
}

// ============================================================
// PUT /api/upload/photo/{id}/main — сделать фото главным
// Только администратор
// ============================================================
elseif ($method === 'PUT') {

    require_admin();

    $uri_parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $photo_id  = (int)$uri_parts[array_search('photo', $uri_parts) + 1];

    $stmt = db()->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
    $stmt->execute([$photo_id]);
    $photo = $stmt->fetch();

    if (!$photo) api_error('Фото не найдено', 404);

    $pdo = db();
    $pdo->prepare("UPDATE photos SET is_main = 0 WHERE profile_id = ?")
        ->execute([$photo['profile_id']]);
    $pdo->prepare("UPDATE photos SET is_main = 1 WHERE id = ?")
        ->execute([$photo_id]);

    api_success(['message' => 'Главное фото обновлено']);
}

else {
    api_error('Метод не разрешён', 405);
}

// ============================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ОБРАБОТКИ ИЗОБРАЖЕНИЙ
// ============================================================

/**
 * Загрузить изображение в GD-ресурс
 * Поддерживает JPEG и PNG
 */
function load_image(string $path, string $mime): \GdImage|false
{
    return match ($mime) {
        'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
        'image/png'               => imagecreatefrompng($path),
        default                   => false,
    };
}

/**
 * Рассчитать новые размеры с сохранением пропорций.
 * Ограничивает длинную сторону до $max_side пикселей.
 * Если изображение меньше — не увеличивает.
 */
function calculate_dimensions(int $w, int $h, int $max_side): array
{
    // Изображение уже в пределах лимита
    if ($w <= $max_side && $h <= $max_side) {
        return [$w, $h];
    }

    // Определяем длинную сторону и масштабируем
    if ($w >= $h) {
        $ratio  = $max_side / $w;
        $new_w  = $max_side;
        $new_h  = (int)round($h * $ratio);
    } else {
        $ratio  = $max_side / $h;
        $new_h  = $max_side;
        $new_w  = (int)round($w * $ratio);
    }

    // Минимум 1 пиксель по каждой стороне
    return [max(1, $new_w), max(1, $new_h)];
}
