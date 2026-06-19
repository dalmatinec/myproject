<?php
/**
 * WeToo — API анкет
 * Файл: api/profiles.php
 *
 * GET    /api/profiles              — список активных анкет (с фильтрами)
 * GET    /api/profiles/{slug}       — одна анкета по slug
 * POST   /api/profiles              — создать заявку на анкету
 * PUT    /api/profiles/{id}         — обновить анкету (админ)
 * DELETE /api/profiles/{id}         — удалить анкету (админ)
 * POST   /api/profiles/{id}/click   — зафиксировать клик по контакту
 * POST   /api/profiles/{id}/report  — пожаловаться на анкету
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

$method    = $_SERVER['REQUEST_METHOD'];
$uri       = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts     = explode('/', $uri);

// Определяем сегменты URL: api/profiles/{id_or_slug}/{action}
$id_or_slug = $parts[2] ?? '';
$action     = $parts[3] ?? '';

// ============================================================
// GET /api/profiles — список активных анкет
// Параметры:
//   city_id    — фильтр по городу
//   city_slug  — фильтр по slug города
//   page       — страница (с 1)
//   per_page   — анкет на страницу (макс 100)
//   vip_only   — только VIP
//   search     — поиск по имени (только для Telegram-бота)
// ============================================================
if ($method === 'GET' && !$id_or_slug) {

    $city_id   = input_int('city_id');
    $city_slug = input_str('city_slug');
    $page      = max(1, input_int('page', 1));
    $per_page  = min(MAX_PAGE_SIZE, max(1, input_int('per_page', DEFAULT_PAGE_SIZE)));
    $vip_only  = input_str('vip_only') === '1';
    $search    = input_str('search');
    $offset    = ($page - 1) * $per_page;

    // Если передан city_slug — находим city_id
    if ($city_slug && !$city_id) {
        $stmt = db()->prepare("SELECT id FROM cities WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$city_slug]);
        $city_row = $stmt->fetch();
        if ($city_row) $city_id = (int)$city_row['id'];
    }

    // Собираем условия WHERE
    $where  = ["p.status = 'active'", "(p.expires_at IS NULL OR p.expires_at > NOW())"];
    $params = [];

    if ($city_id) {
        $where[]  = 'p.city_id = ?';
        $params[] = $city_id;
    }
    if ($vip_only) {
        $where[]  = 'p.is_vip = 1';
        $where[]  = '(p.vip_until IS NULL OR p.vip_until > NOW())';
    }
    if ($search) {
        $where[]  = 'p.name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    // Считаем общее количество
    $count_stmt = db()->prepare("SELECT COUNT(*) as total FROM profiles p $where_sql");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetch()['total'];

    // VIP анкеты идут первыми, внутри VIP — по дате публикации DESC
    // Обычные анкеты — по дате публикации DESC
    $sql = "
        SELECT
            p.id,
            p.name,
            p.age,
            p.description,
            p.is_vip,
            p.vip_until,
            p.slug,
            p.view_count,
            p.click_telegram,
            p.click_whatsapp,
            p.published_at,
            p.expires_at,
            c.id   AS city_id,
            c.name AS city_name,
            c.slug AS city_slug,
            -- Главное фото
            (SELECT CONCAT(?, '/', p.id, '/', ph.filename)
             FROM photos ph
             WHERE ph.profile_id = p.id
             ORDER BY ph.is_main DESC, ph.sort_order ASC
             LIMIT 1
            ) AS main_photo,
            -- Telegram
            (SELECT pc.value FROM profile_contacts pc
             WHERE pc.profile_id = p.id AND pc.type = 'telegram' LIMIT 1) AS telegram,
            -- WhatsApp
            (SELECT pc.value FROM profile_contacts pc
             WHERE pc.profile_id = p.id AND pc.type = 'whatsapp' LIMIT 1) AS whatsapp
        FROM profiles p
        JOIN cities c ON c.id = p.city_id
        $where_sql
        ORDER BY
            p.is_vip DESC,
            p.published_at DESC
        LIMIT ? OFFSET ?
    ";

    // Добавляем UPLOADS_URL как первый параметр для CONCAT
    $query_params = array_merge([UPLOADS_URL], $params, [$per_page, $offset]);
    $stmt = db()->prepare($sql);
    $stmt->execute($query_params);
    $profiles = $stmt->fetchAll();

    // Типизируем числовые поля
    foreach ($profiles as &$p) {
        $p['id']            = (int)$p['id'];
        $p['age']           = (int)$p['age'];
        $p['is_vip']        = (bool)$p['is_vip'];
        $p['view_count']    = (int)$p['view_count'];
        $p['click_telegram']= (int)$p['click_telegram'];
        $p['click_whatsapp']= (int)$p['click_whatsapp'];
        $p['city_id']       = (int)$p['city_id'];
    }
    unset($p);

    api_success($profiles, 200, [
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'total_pages'=> (int)ceil($total / $per_page),
    ]);
}

// ============================================================
// GET /api/profiles/{slug} — одна анкета по slug
// Записывает просмотр (с дедупликацией по IP)
// ============================================================
elseif ($method === 'GET' && $id_or_slug && !$action) {

    $slug = $id_or_slug;

    // Ищем активную анкету по slug
    $stmt = db()->prepare("
        SELECT
            p.id,
            p.name,
            p.age,
            p.description,
            p.is_vip,
            p.vip_until,
            p.slug,
            p.view_count,
            p.click_telegram,
            p.click_whatsapp,
            p.status,
            p.published_at,
            p.expires_at,
            c.id   AS city_id,
            c.name AS city_name,
            c.slug AS city_slug
        FROM profiles p
        JOIN cities c ON c.id = p.city_id
        WHERE p.slug = ?
          AND p.status = 'active'
          AND (p.expires_at IS NULL OR p.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $profile = $stmt->fetch();

    if (!$profile) {
        api_error('Анкета не найдена', 404);
    }

    $profile_id = (int)$profile['id'];

    // Загружаем все фото анкеты
    $stmt = db()->prepare("
        SELECT id, filename, is_main, sort_order,
               CONCAT(?, '/', ?, '/', filename) AS url
        FROM photos
        WHERE profile_id = ?
        ORDER BY is_main DESC, sort_order ASC
    ");
    $stmt->execute([UPLOADS_URL, $profile_id, $profile_id]);
    $photos = $stmt->fetchAll();

    foreach ($photos as &$ph) {
        $ph['id']       = (int)$ph['id'];
        $ph['is_main']  = (bool)$ph['is_main'];
        $ph['sort_order']=(int)$ph['sort_order'];
    }
    unset($ph);

    // Загружаем все контакты анкеты
    $stmt = db()->prepare("
        SELECT type, value, label
        FROM profile_contacts
        WHERE profile_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$profile_id]);
    $contacts = $stmt->fetchAll();

    // Записываем просмотр
    record_view($profile_id);

    // Типизируем
    $profile['id']            = $profile_id;
    $profile['age']           = (int)$profile['age'];
    $profile['is_vip']        = (bool)$profile['is_vip'];
    $profile['view_count']    = (int)$profile['view_count'] + 1; // +1 текущий просмотр
    $profile['click_telegram']= (int)$profile['click_telegram'];
    $profile['click_whatsapp']= (int)$profile['click_whatsapp'];
    $profile['city_id']       = (int)$profile['city_id'];
    $profile['photos']        = $photos;
    $profile['contacts']      = $contacts;

    api_success($profile);
}

// ============================================================
// POST /api/profiles — создать заявку на анкету
// Публичный эндпоинт. Анкета создаётся со статусом pending_payment.
// Тело: { name, age, city_id, description, telegram, whatsapp, plan_id }
// ============================================================
elseif ($method === 'POST' && !$id_or_slug) {

    check_rate_limit('profile_create_' . ($_SERVER['REMOTE_ADDR'] ?? ''));

    $body = input_json();

    // Валидация обязательных полей
    $errors = [];
    $name   = trim($body['name'] ?? '');
    $age    = (int)($body['age'] ?? 0);
    $city_id= (int)($body['city_id'] ?? 0);
    $plan_id= (int)($body['plan_id'] ?? 0);
    $desc   = trim($body['description'] ?? '');
    $tg     = trim(ltrim($body['telegram'] ?? '', '@'));
    $wa     = preg_replace('/\D/', '', $body['whatsapp'] ?? '');

    if (!$name)          $errors['name']    = 'Укажите имя';
    if (strlen($name) > 128) $errors['name'] = 'Имя слишком длинное (максимум 128 символов)';
    if ($age < 18 || $age > 99) $errors['age'] = 'Возраст должен быть от 18 до 99 лет';
    if (!$city_id)       $errors['city_id'] = 'Выберите город';
    if (!$plan_id)       $errors['plan_id'] = 'Выберите тариф';

    // Проверяем город в БД
    if ($city_id) {
        $stmt = db()->prepare("SELECT id FROM cities WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$city_id]);
        if (!$stmt->fetch()) $errors['city_id'] = 'Выбранный город недоступен';
    }

    // Проверяем тариф в БД
    if ($plan_id) {
        $stmt = db()->prepare("SELECT id FROM plans WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$plan_id]);
        if (!$stmt->fetch()) $errors['plan_id'] = 'Выбранный тариф недоступен';
    }

    // Хотя бы один контакт обязателен
    if (!$tg && !$wa) {
        $errors['contacts'] = 'Укажите хотя бы один контакт: Telegram или WhatsApp';
    }

    if (!empty($errors)) {
        api_error('Ошибка валидации', 422, $errors);
    }

    // Генерируем уникальный slug из имени
    $slug = generate_slug($name, 'profiles', 'slug');

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Создаём анкету
        $stmt = $pdo->prepare("
            INSERT INTO profiles (city_id, plan_id, name, age, description, slug, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_payment')
        ");
        $stmt->execute([$city_id, $plan_id, $name, $age, $desc ?: null, $slug]);
        $profile_id = (int)$pdo->lastInsertId();

        // Сохраняем контакты в profile_contacts
        $sort = 0;
        if ($tg) {
            $pdo->prepare("
                INSERT INTO profile_contacts (profile_id, type, value, sort_order)
                VALUES (?, 'telegram', ?, ?)
            ")->execute([$profile_id, $tg, $sort++]);
        }
        if ($wa) {
            $pdo->prepare("
                INSERT INTO profile_contacts (profile_id, type, value, sort_order)
                VALUES (?, 'whatsapp', ?, ?)
            ")->execute([$profile_id, $wa, $sort++]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        api_error('Ошибка создания анкеты. Попробуйте ещё раз.', 500);
    }

    // Возвращаем данные созданной анкеты
    api_success([
        'id'     => $profile_id,
        'slug'   => $slug,
        'status' => 'pending_payment',
        'name'   => $name,
        'age'    => $age,
    ], 201);
}

// ============================================================
// PUT /api/profiles/{id} — обновить анкету (только администратор)
// ============================================================
elseif ($method === 'PUT' && $id_or_slug && !$action) {

    $admin = require_admin();
    $id    = (int)$id_or_slug;
    $body  = input_json();

    // Проверяем существование анкеты
    $stmt = db()->prepare("SELECT * FROM profiles WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    if (!$profile) api_error('Анкета не найдена', 404);

    // Собираем только переданные поля для обновления
    $updates = [];
    $params  = [];

    $allowed_fields = ['name','age','description','city_id','plan_id','status','is_vip','vip_until'];
    foreach ($allowed_fields as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = $body[$field] === '' ? null : $body[$field];
        }
    }

    // Обработка статуса active — фиксируем модерацию
    if (isset($body['status'])) {
        if ($body['status'] === 'active' && $profile['status'] !== 'active') {
            $updates[] = 'moderated_by = ?';
            $params[]  = $admin['id'];
            $updates[] = 'moderated_at = NOW()';
            if (empty($profile['published_at'])) {
                $updates[] = 'published_at = NOW()';
            }
        }
        if (isset($body['moderation_note'])) {
            $updates[] = 'moderation_note = ?';
            $params[]  = trim($body['moderation_note']);
        }
    }

    if (empty($updates)) {
        api_error('Нет данных для обновления', 400);
    }

    $params[] = $id;
    db()->prepare("UPDATE profiles SET " . implode(', ', $updates) . " WHERE id = ?")
        ->execute($params);

    // Обновляем контакты если переданы
    if (isset($body['contacts']) && is_array($body['contacts'])) {
        db()->prepare("DELETE FROM profile_contacts WHERE profile_id = ?")->execute([$id]);
        $sort = 0;
        foreach ($body['contacts'] as $contact) {
            $type  = trim($contact['type']  ?? '');
            $value = trim($contact['value'] ?? '');
            if ($type && $value) {
                db()->prepare("
                    INSERT INTO profile_contacts (profile_id, type, value, sort_order)
                    VALUES (?, ?, ?, ?)
                ")->execute([$id, $type, $value, $sort++]);
            }
        }
    }

    api_success(['message' => 'Анкета обновлена', 'id' => $id]);
}

// ============================================================
// DELETE /api/profiles/{id} — удалить анкету (только администратор)
// ============================================================
elseif ($method === 'DELETE' && $id_or_slug && !$action) {

    require_admin();
    $id = (int)$id_or_slug;

    $stmt = db()->prepare("SELECT id FROM profiles WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) api_error('Анкета не найдена', 404);

    // Удаляем файлы фотографий с диска
    $stmt = db()->prepare("SELECT filename FROM photos WHERE profile_id = ?");
    $stmt->execute([$id]);
    $photos = $stmt->fetchAll();

    foreach ($photos as $photo) {
        $file = UPLOADS_DIR . '/' . $id . '/' . $photo['filename'];
        if (file_exists($file)) unlink($file);
    }

    // Директория анкеты
    $dir = UPLOADS_DIR . '/' . $id;
    if (is_dir($dir)) rmdir($dir);

    // Удаляем анкету (CASCADE удалит photos, contacts, views, clicks, reports)
    db()->prepare("DELETE FROM profiles WHERE id = ?")->execute([$id]);

    api_success(['message' => 'Анкета удалена']);
}

// ============================================================
// POST /api/profiles/{id}/click — зафиксировать клик по контакту
// Публичный эндпоинт
// Тело: { "type": "telegram" }
// ============================================================
elseif ($method === 'POST' && $id_or_slug && $action === 'click') {

    $profile_id   = (int)$id_or_slug;
    $body         = input_json();
    $contact_type = trim($body['type'] ?? '');

    if (!$contact_type) {
        api_error('Не указан тип контакта', 400);
    }

    // Проверяем что анкета активна
    $stmt = db()->prepare("SELECT id FROM profiles WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$profile_id]);
    if (!$stmt->fetch()) api_error('Анкета не найдена', 404);

    record_contact_click($profile_id, $contact_type);

    api_success(['message' => 'Клик зафиксирован']);
}

// ============================================================
// POST /api/profiles/{id}/report — пожаловаться на анкету
// Публичный эндпоинт
// Тело: { "reason": "spam|fake|inappropriate|other", "comment": "..." }
// ============================================================
elseif ($method === 'POST' && $id_or_slug && $action === 'report') {

    check_rate_limit('report_' . ($_SERVER['REMOTE_ADDR'] ?? ''));

    $profile_id = (int)$id_or_slug;
    $body       = input_json();
    $reason     = trim($body['reason'] ?? '');
    $comment    = trim($body['comment'] ?? '');
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';

    $valid_reasons = ['spam', 'fake', 'inappropriate', 'other'];
    if (!in_array($reason, $valid_reasons, true)) {
        api_error('Укажите причину жалобы', 400);
    }

    // Проверяем анкету
    $stmt = db()->prepare("SELECT id FROM profiles WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$profile_id]);
    if (!$stmt->fetch()) api_error('Анкета не найдена', 404);

    // Защита от дублирования: не более 1 жалобы с IP за 24 часа на одну анкету
    $stmt = db()->prepare("
        SELECT id FROM profile_reports
        WHERE profile_id = ? AND ip_address = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$profile_id, $ip]);
    if ($stmt->fetch()) {
        api_error('Вы уже отправили жалобу на эту анкету', 429);
    }

    db()->prepare("
        INSERT INTO profile_reports (profile_id, reason, comment, ip_address)
        VALUES (?, ?, ?, ?)
    ")->execute([$profile_id, $reason, $comment ?: null, $ip]);

    api_success(['message' => 'Жалоба отправлена. Администратор рассмотрит её в ближайшее время.']);
}

else {
    api_error('Маршрут не найден', 404);
}
