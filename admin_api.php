<?php
/**
 * WeToo — Дашборд администратора
 * Файл: api/admin.php
 *
 * GET /api/admin/dashboard  — общая статистика
 * GET /api/admin/profiles   — все анкеты (с фильтрами по статусу)
 * GET /api/admin/reports    — жалобы на анкеты
 * PUT /api/admin/reports/{id} — обработать жалобу
 * GET /api/admin/admins     — список администраторов
 * POST /api/admin/admins    — добавить администратора
 * DELETE /api/admin/admins/{id} — удалить администратора
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

// Все маршруты admin.php требуют авторизации
$current_admin = require_admin();

$method  = $_SERVER['REQUEST_METHOD'];
$parts   = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$segment = $parts[2] ?? '';
$sub_id  = isset($parts[3]) ? (int)$parts[3] : 0;

// ============================================================
// GET /api/admin/dashboard — сводная статистика
// ============================================================
if ($method === 'GET' && $segment === 'dashboard') {

    $pdo = db();

    // Статистика анкет по статусам
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM profiles
        GROUP BY status
    ");
    $profiles_by_status = [];
    foreach ($stmt->fetchAll() as $row) {
        $profiles_by_status[$row['status']] = (int)$row['count'];
    }

    // Статистика платежей
    $stmt = $pdo->query("SELECT * FROM v_payment_stats LIMIT 1");
    $payment_stats = $stmt->fetch();

    // Новые жалобы
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM profile_reports WHERE status = 'new'");
    $new_reports = (int)$stmt->fetch()['cnt'];

    // Анкеты истекающие в ближайшие 3 дня
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM profiles
        WHERE status = 'active'
          AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
    ");
    $expiring_soon = (int)$stmt->fetch()['cnt'];

    // Просмотры за сегодня
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM views
        WHERE viewed_at >= CURDATE()
    ");
    $views_today = (int)$stmt->fetch()['cnt'];

    // Клики за сегодня
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt FROM contact_clicks
        WHERE clicked_at >= CURDATE()
    ");
    $clicks_today = (int)$stmt->fetch()['cnt'];

    // Топ-5 анкет по просмотрам
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.view_count, p.click_telegram, p.click_whatsapp,
               c.name AS city_name
        FROM profiles p
        JOIN cities c ON c.id = p.city_id
        WHERE p.status = 'active'
        ORDER BY p.view_count DESC
        LIMIT 5
    ");
    $top_profiles = $stmt->fetchAll();

    api_success([
        'profiles'       => $profiles_by_status,
        'payments'       => $payment_stats,
        'new_reports'    => $new_reports,
        'expiring_soon'  => $expiring_soon,
        'views_today'    => $views_today,
        'clicks_today'   => $clicks_today,
        'top_profiles'   => $top_profiles,
    ]);
}

// ============================================================
// GET /api/admin/profiles — все анкеты с фильтрами
// Параметры: status, city_id, page, per_page, search
// ============================================================
elseif ($method === 'GET' && $segment === 'profiles') {

    $status   = input_str('status');
    $city_id  = input_int('city_id');
    $search   = input_str('search');
    $page     = max(1, input_int('page', 1));
    $per_page = min(100, max(1, input_int('per_page', 25)));
    $offset   = ($page - 1) * $per_page;

    $where  = [];
    $params = [];

    if ($status) {
        $where[]  = 'p.status = ?';
        $params[] = $status;
    }
    if ($city_id) {
        $where[]  = 'p.city_id = ?';
        $params[] = $city_id;
    }
    if ($search) {
        $where[]  = '(p.name LIKE ? OR p.id = ?)';
        $params[] = '%' . $search . '%';
        $params[] = (int)$search;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = db()->prepare("SELECT COUNT(*) as total FROM profiles p $where_sql");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetch()['total'];

    $stmt = db()->prepare("
        SELECT
            p.id, p.name, p.age, p.status, p.is_vip, p.vip_until,
            p.slug, p.view_count, p.click_telegram, p.click_whatsapp,
            p.published_at, p.expires_at, p.moderated_at,
            p.created_at, p.updated_at,
            c.name AS city_name,
            pl.name AS plan_name,
            pl.price_usd,
            a.display_name AS moderated_by_name,
            -- Главное фото
            (SELECT CONCAT(?, '/', p.id, '/', ph.filename)
             FROM photos ph WHERE ph.profile_id = p.id
             ORDER BY ph.is_main DESC, ph.sort_order ASC LIMIT 1
            ) AS main_photo,
            -- Telegram
            (SELECT pc.value FROM profile_contacts pc
             WHERE pc.profile_id = p.id AND pc.type = 'telegram' LIMIT 1) AS telegram,
            -- Количество фото
            (SELECT COUNT(*) FROM photos ph WHERE ph.profile_id = p.id) AS photo_count,
            -- Количество жалоб
            (SELECT COUNT(*) FROM profile_reports pr WHERE pr.profile_id = p.id AND pr.status = 'new') AS new_reports
        FROM profiles p
        JOIN cities c ON c.id = p.city_id
        LEFT JOIN plans pl ON pl.id = p.plan_id
        LEFT JOIN admins a ON a.id = p.moderated_by
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge([UPLOADS_URL], $params, [$per_page, $offset]));
    $profiles = $stmt->fetchAll();

    foreach ($profiles as &$p) {
        $p['id']            = (int)$p['id'];
        $p['age']           = (int)$p['age'];
        $p['is_vip']        = (bool)$p['is_vip'];
        $p['view_count']    = (int)$p['view_count'];
        $p['click_telegram']= (int)$p['click_telegram'];
        $p['click_whatsapp']= (int)$p['click_whatsapp'];
        $p['photo_count']   = (int)$p['photo_count'];
        $p['new_reports']   = (int)$p['new_reports'];
    }
    unset($p);

    api_success($profiles, 200, [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => (int)ceil($total / $per_page),
    ]);
}

// ============================================================
// GET /api/admin/reports — жалобы на анкеты
// ============================================================
elseif ($method === 'GET' && $segment === 'reports') {

    $status   = input_str('status', 'new');
    $page     = max(1, input_int('page', 1));
    $per_page = min(50, max(1, input_int('per_page', 25)));
    $offset   = ($page - 1) * $per_page;

    $where  = $status ? 'WHERE pr.status = ?' : '';
    $params = $status ? [$status] : [];

    $count_stmt = db()->prepare("SELECT COUNT(*) as total FROM profile_reports pr $where");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetch()['total'];

    $stmt = db()->prepare("
        SELECT
            pr.id, pr.profile_id, pr.reason, pr.comment,
            pr.ip_address, pr.status, pr.admin_note, pr.created_at,
            p.name AS profile_name, p.slug AS profile_slug,
            a.display_name AS reviewed_by
        FROM profile_reports pr
        JOIN profiles p ON p.id = pr.profile_id
        LEFT JOIN admins a ON a.id = pr.admin_id
        $where
        ORDER BY pr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));

    api_success($stmt->fetchAll(), 200, [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => (int)ceil($total / $per_page),
    ]);
}

// ============================================================
// PUT /api/admin/reports/{id} — обработать жалобу
// Тело: { "status": "reviewed|dismissed|action_taken", "note": "..." }
// ============================================================
elseif ($method === 'PUT' && $segment === 'reports' && $sub_id) {

    $body   = input_json();
    $status = trim($body['status'] ?? '');
    $note   = trim($body['note']   ?? '');

    $valid = ['reviewed', 'dismissed', 'action_taken'];
    if (!in_array($status, $valid, true)) {
        api_error('Неверный статус жалобы', 400);
    }

    db()->prepare("
        UPDATE profile_reports
        SET status = ?, admin_id = ?, admin_note = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$status, $current_admin['id'], $note ?: null, $sub_id]);

    api_success(['message' => 'Жалоба обработана']);
}

// ============================================================
// GET /api/admin/admins — список администраторов
// ============================================================
elseif ($method === 'GET' && $segment === 'admins') {

    $stmt = db()->query("
        SELECT id, username, display_name, telegram, is_active, last_login_at, created_at
        FROM admins
        ORDER BY created_at ASC
    ");
    $admins = $stmt->fetchAll();
    foreach ($admins as &$a) {
        $a['id']        = (int)$a['id'];
        $a['is_active'] = (bool)$a['is_active'];
    }
    unset($a);
    api_success($admins);
}

// ============================================================
// POST /api/admin/admins — добавить администратора
// ============================================================
elseif ($method === 'POST' && $segment === 'admins') {

    $body     = input_json();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $name     = trim($body['display_name'] ?? '');

    if (!$username || !$password || !$name) {
        api_error('Укажите логин, пароль и имя', 400);
    }
    if (strlen($password) < 8) {
        api_error('Пароль минимум 8 символов', 400);
    }

    // Проверяем уникальность логина
    $stmt = db()->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) api_error('Логин уже занят', 409);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmt = db()->prepare("
        INSERT INTO admins (username, password_hash, display_name, telegram, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $username, $hash, $name,
        ltrim(trim($body['telegram'] ?? ''), '@') ?: null,
    ]);

    api_success(['id' => (int)db()->lastInsertId(), 'username' => $username], 201);
}

// ============================================================
// DELETE /api/admin/admins/{id} — удалить администратора
// Нельзя удалить самого себя
// ============================================================
elseif ($method === 'DELETE' && $segment === 'admins' && $sub_id) {

    if ($sub_id === (int)$current_admin['id']) {
        api_error('Нельзя удалить самого себя', 403);
    }

    // Проверяем что не удаляем последнего администратора
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM admins WHERE is_active = 1");
    if ((int)$stmt->fetch()['cnt'] <= 1) {
        api_error('Нельзя удалить единственного администратора', 403);
    }

    db()->prepare("DELETE FROM admins WHERE id = ?")->execute([$sub_id]);
    api_success(['message' => 'Администратор удалён']);
}

else {
    api_error('Маршрут не найден', 404);
}
