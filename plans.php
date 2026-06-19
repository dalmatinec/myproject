<?php
/**
 * WeToo — API тарифных планов
 * Файл: api/plans.php
 *
 * GET    /api/plans      — список активных тарифов (публичный)
 * POST   /api/plans      — создать тариф (админ)
 * PUT    /api/plans/{id} — обновить тариф (админ)
 * DELETE /api/plans/{id} — удалить тариф (админ)
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

$method  = $_SERVER['REQUEST_METHOD'];
$parts   = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$plan_id = isset($parts[2]) ? (int)$parts[2] : 0;

// GET /api/plans — список тарифов из БД (без хардкода)
if ($method === 'GET' && !$plan_id) {

    // Для администратора — все тарифы, для публики — только активные
    $admin      = get_admin();
    $where      = $admin ? '' : 'WHERE is_active = 1';

    $stmt = db()->prepare("
        SELECT id, name, description, duration_days, vip_days, price_usd, is_active, sort_order
        FROM plans
        $where
        ORDER BY sort_order ASC, price_usd ASC
    ");
    $stmt->execute();
    $plans = $stmt->fetchAll();

    foreach ($plans as &$p) {
        $p['id']           = (int)$p['id'];
        $p['duration_days']= (int)$p['duration_days'];
        $p['vip_days']     = (int)$p['vip_days'];
        $p['price_usd']    = (float)$p['price_usd'];
        $p['is_active']    = (bool)$p['is_active'];
        $p['is_vip']       = $p['vip_days'] > 0;  // удобный флаг для фронтенда
        $p['sort_order']   = (int)$p['sort_order'];
    }
    unset($p);

    api_success($plans);
}

// POST /api/plans — создать тариф
elseif ($method === 'POST') {
    require_admin();
    $body = input_json();

    $errors = [];
    $name   = trim($body['name'] ?? '');
    $days   = (int)($body['duration_days'] ?? 0);
    $price  = (float)($body['price_usd'] ?? 0);

    if (!$name)    $errors['name']         = 'Укажите название тарифа';
    if ($days < 1) $errors['duration_days']= 'Укажите срок размещения (дней)';
    if ($price <= 0) $errors['price_usd']  = 'Укажите корректную цену';

    if (!empty($errors)) api_error('Ошибка валидации', 422, $errors);

    $stmt = db()->prepare("
        INSERT INTO plans (name, description, duration_days, vip_days, price_usd, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        trim($body['description'] ?? '') ?: null,
        $days,
        (int)($body['vip_days'] ?? 0),
        $price,
        isset($body['is_active']) ? (int)$body['is_active'] : 1,
        (int)($body['sort_order'] ?? 0),
    ]);

    api_success(['id' => (int)db()->lastInsertId(), 'name' => $name], 201);
}

// PUT /api/plans/{id} — обновить тариф
elseif ($method === 'PUT' && $plan_id) {
    require_admin();
    $body = input_json();

    $updates = [];
    $params  = [];
    foreach (['name','description','duration_days','vip_days','price_usd','is_active','sort_order'] as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = $body[$field] === '' ? null : $body[$field];
        }
    }
    if (empty($updates)) api_error('Нет данных для обновления', 400);
    $params[] = $plan_id;
    db()->prepare("UPDATE plans SET " . implode(',', $updates) . " WHERE id = ?")->execute($params);
    api_success(['message' => 'Тариф обновлён']);
}

// DELETE /api/plans/{id}
elseif ($method === 'DELETE' && $plan_id) {
    require_admin();
    db()->prepare("DELETE FROM plans WHERE id = ?")->execute([$plan_id]);
    api_success(['message' => 'Тариф удалён']);
}

else {
    api_error('Маршрут не найден', 404);
}
