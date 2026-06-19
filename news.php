<?php
/**
 * WeToo — API новостей
 * Файл: api/news.php
 *
 * GET    /api/news          — список новостей (публичный)
 * GET    /api/news/{slug}   — одна новость
 * POST   /api/news          — создать новость (админ)
 * PUT    /api/news/{id}     — обновить новость (админ)
 * DELETE /api/news/{id}     — удалить новость (админ)
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

$method     = $_SERVER['REQUEST_METHOD'];
$parts      = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$id_or_slug = $parts[2] ?? '';

// GET /api/news — список опубликованных новостей
if ($method === 'GET' && !$id_or_slug) {

    $admin    = get_admin();
    $page     = max(1, input_int('page', 1));
    $per_page = min(50, max(1, input_int('per_page', 10)));
    $offset   = ($page - 1) * $per_page;

    // Администратор видит все новости, пользователь — только опубликованные
    $where  = $admin ? '' : "WHERE n.is_published = 1 AND n.published_at <= NOW()";

    $count_stmt = db()->prepare("SELECT COUNT(*) as total FROM news n $where");
    $count_stmt->execute();
    $total = (int)$count_stmt->fetch()['total'];

    $stmt = db()->prepare("
        SELECT
            n.id, n.title, n.slug, n.preview_image,
            n.source, n.is_published, n.published_at, n.created_at,
            -- Краткое превью (первые 200 символов без HTML)
            LEFT(REGEXP_REPLACE(n.content, '<[^>]+>', ''), 200) AS excerpt,
            a.display_name AS author_name
        FROM news n
        LEFT JOIN admins a ON a.id = n.admin_id
        $where
        ORDER BY n.published_at DESC, n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$per_page, $offset]);
    $news = $stmt->fetchAll();

    foreach ($news as &$n) {
        $n['id']           = (int)$n['id'];
        $n['is_published'] = (bool)$n['is_published'];
    }
    unset($n);

    api_success($news, 200, [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => (int)ceil($total / $per_page),
    ]);
}

// GET /api/news/{slug} — одна новость
elseif ($method === 'GET' && $id_or_slug) {

    $admin = get_admin();
    $where = $admin
        ? 'WHERE n.slug = ? OR n.id = ?'
        : "WHERE (n.slug = ? OR n.id = ?) AND n.is_published = 1";

    $id_val = is_numeric($id_or_slug) ? (int)$id_or_slug : 0;

    $stmt = db()->prepare("
        SELECT n.*, a.display_name AS author_name
        FROM news n
        LEFT JOIN admins a ON a.id = n.admin_id
        $where
        LIMIT 1
    ");
    $stmt->execute([$id_or_slug, $id_val]);
    $news = $stmt->fetch();

    if (!$news) api_error('Новость не найдена', 404);

    $news['id']           = (int)$news['id'];
    $news['is_published'] = (bool)$news['is_published'];

    // Синхронизация с Telegram (ссылка на пост)
    $stmt = db()->prepare("
        SELECT telegram_message_id, telegram_channel
        FROM telegram_sync
        WHERE entity_type = 'news' AND entity_id = ?
        LIMIT 1
    ");
    $stmt->execute([$news['id']]);
    $tg_sync = $stmt->fetch();
    if ($tg_sync) {
        $news['telegram_url'] = 'https://t.me/' . $tg_sync['telegram_channel'] . '/' . $tg_sync['telegram_message_id'];
    }

    api_success($news);
}

// POST /api/news — создать новость
elseif ($method === 'POST') {

    $admin = require_admin();
    $body  = input_json();

    $title   = trim($body['title']   ?? '');
    $content = trim($body['content'] ?? '');

    if (!$title)   api_error('Укажите заголовок', 400);
    if (!$content) api_error('Укажите текст новости', 400);

    $slug        = generate_slug($title, 'news', 'slug');
    $is_pub      = (int)($body['is_published'] ?? 0);
    $pub_at      = $is_pub ? ($body['published_at'] ?? date('Y-m-d H:i:s')) : null;
    $tg_channel  = trim($body['telegram_channel'] ?? '') ?: get_setting('telegram_channel');

    $stmt = db()->prepare("
        INSERT INTO news (admin_id, title, slug, content, preview_image, source, is_published, published_at)
        VALUES (?, ?, ?, ?, ?, 'manual', ?, ?)
    ");
    $stmt->execute([
        $admin['id'],
        $title,
        $slug,
        $content,
        trim($body['preview_image'] ?? '') ?: null,
        $is_pub,
        $pub_at,
    ]);
    $news_id = (int)db()->lastInsertId();

    api_success(['id' => $news_id, 'slug' => $slug], 201);
}

// PUT /api/news/{id}
elseif ($method === 'PUT' && $id_or_slug) {

    require_admin();
    $id   = (int)$id_or_slug;
    $body = input_json();

    $updates = [];
    $params  = [];

    foreach (['title','content','preview_image','is_published','published_at'] as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = $body[$field] === '' ? null : $body[$field];
        }
    }

    // При публикации — фиксируем дату
    if (isset($body['is_published']) && $body['is_published'] && !isset($body['published_at'])) {
        $updates[] = 'published_at = NOW()';
    }

    if (empty($updates)) api_error('Нет данных для обновления', 400);
    $params[] = $id;
    db()->prepare("UPDATE news SET " . implode(',', $updates) . " WHERE id = ?")->execute($params);

    api_success(['message' => 'Новость обновлена']);
}

// DELETE /api/news/{id}
elseif ($method === 'DELETE' && $id_or_slug) {
    require_admin();
    db()->prepare("DELETE FROM news WHERE id = ?")->execute([(int)$id_or_slug]);
    api_success(['message' => 'Новость удалена']);
}

else {
    api_error('Маршрут не найден', 404);
}
