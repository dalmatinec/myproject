<?php
/**
 * WeToo — API городов
 * Файл: api/cities.php
 *
 * GET  /api/cities        — список активных городов
 * POST /api/cities        — добавить город (админ)
 * PUT  /api/cities/{id}   — обновить город (админ)
 * DELETE /api/cities/{id} — удалить город (админ)
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

$method  = $_SERVER['REQUEST_METHOD'];
$parts   = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$city_id = isset($parts[2]) ? (int)$parts[2] : 0;

// GET /api/cities — список городов из БД
if ($method === 'GET' && !$city_id) {

    $stmt = db()->prepare("
        SELECT
            c.id,
            c.name,
            c.slug,
            c.sort_order,
            c.is_active,
            -- Количество активных анкет в городе
            (SELECT COUNT(*) FROM profiles p
             WHERE p.city_id = c.id
               AND p.status = 'active'
               AND (p.expires_at IS NULL OR p.expires_at > NOW())
            ) AS profile_count
        FROM cities c
        WHERE c.is_active = 1
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $stmt->execute();
    $cities = $stmt->fetchAll();

    foreach ($cities as &$c) {
        $c['id']            = (int)$c['id'];
        $c['sort_order']    = (int)$c['sort_order'];
        $c['is_active']     = (bool)$c['is_active'];
        $c['profile_count'] = (int)$c['profile_count'];
    }
    unset($c);

    api_success($cities);
}

// POST /api/cities — добавить город
elseif ($method === 'POST') {
    require_admin();
    $body = input_json();
    $name = trim($body['name'] ?? '');
    if (!$name) api_error('Укажите название города', 400);

    $slug = generate_slug($name, 'cities', 'slug');

    $stmt = db()->prepare("
        INSERT INTO cities (name, slug, sort_order, is_active)
        VALUES (?, ?, ?, 1)
    ");
    $stmt->execute([$name, $slug, (int)($body['sort_order'] ?? 0)]);

    api_success(['id' => (int)db()->lastInsertId(), 'name' => $name, 'slug' => $slug], 201);
}

// PUT /api/cities/{id}
elseif ($method === 'PUT' && $city_id) {
    require_admin();
    $body = input_json();

    $updates = [];
    $params  = [];
    foreach (['name','slug','sort_order','is_active'] as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = $body[$field];
        }
    }
    if (empty($updates)) api_error('Нет данных для обновления', 400);
    $params[] = $city_id;
    db()->prepare("UPDATE cities SET " . implode(',', $updates) . " WHERE id = ?")->execute($params);
    api_success(['message' => 'Город обновлён']);
}

// DELETE /api/cities/{id}
elseif ($method === 'DELETE' && $city_id) {
    require_admin();

    // Проверяем нет ли анкет в этом городе
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM profiles WHERE city_id = ?");
    $stmt->execute([$city_id]);
    if ((int)$stmt->fetch()['cnt'] > 0) {
        api_error('Нельзя удалить город с анкетами. Сначала удалите или переместите анкеты.', 409);
    }

    db()->prepare("DELETE FROM cities WHERE id = ?")->execute([$city_id]);
    api_success(['message' => 'Город удалён']);
}

else {
    api_error('Маршрут не найден', 404);
}
