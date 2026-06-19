<?php
/**
 * WeToo — API платежей
 * Файл: api/payments.php
 *
 * GET    /api/payments/wallets           — кошельки для оплаты (публичный)
 * POST   /api/payments                   — создать платёж (пользователь отправляет TXID/скриншот)
 * GET    /api/payments?status=review     — список платежей на проверке (админ)
 * PUT    /api/payments/{id}/approve      — одобрить платёж (админ)
 * PUT    /api/payments/{id}/reject       — отклонить платёж (админ)
 * GET    /api/payments/wallets           — управление кошельками (для публичного отображения)
 * POST   /api/payments/wallets           — добавить кошелёк (админ)
 * PUT    /api/payments/wallets/{id}      — обновить кошелёк (админ)
 * DELETE /api/payments/wallets/{id}      — удалить кошелёк (админ)
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

cors_headers();

$method = $_SERVER['REQUEST_METHOD'];
$parts  = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
// Ожидаем: api/payments/{segment}/{action}
$segment = $parts[2] ?? '';
$sub_id  = isset($parts[3]) ? (int)$parts[3] : 0;
$action  = $parts[3] ?? '';

// ============================================================
// GET /api/payments/wallets — список кошельков из БД
// Публичный — пользователь видит куда платить
// ============================================================
if ($method === 'GET' && $segment === 'wallets') {

    $admin = get_admin();
    $where = $admin ? '' : 'WHERE is_active = 1';

    $stmt = db()->prepare("
        SELECT id, currency, network, address, label, is_active, sort_order
        FROM payment_wallets
        $where
        ORDER BY sort_order ASC, currency ASC
    ");
    $stmt->execute();
    $wallets = $stmt->fetchAll();

    foreach ($wallets as &$w) {
        $w['id']        = (int)$w['id'];
        $w['is_active'] = (bool)$w['is_active'];
        $w['sort_order']= (int)$w['sort_order'];
    }
    unset($w);

    api_success($wallets);
}

// ============================================================
// POST /api/payments/wallets — добавить кошелёк (админ)
// ============================================================
elseif ($method === 'POST' && $segment === 'wallets') {
    require_admin();
    $body = input_json();

    $currency = strtoupper(trim($body['currency'] ?? ''));
    $address  = trim($body['address'] ?? '');

    if (!$currency) api_error('Укажите валюту', 400);
    if (!$address)  api_error('Укажите адрес кошелька', 400);

    $stmt = db()->prepare("
        INSERT INTO payment_wallets (currency, network, address, label, is_active, sort_order)
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $currency,
        trim($body['network'] ?? '') ?: null,
        $address,
        trim($body['label'] ?? '') ?: null,
        (int)($body['sort_order'] ?? 0),
    ]);

    api_success(['id' => (int)db()->lastInsertId()], 201);
}

// ============================================================
// PUT /api/payments/wallets/{id} — обновить кошелёк (админ)
// ============================================================
elseif ($method === 'PUT' && $segment === 'wallets' && $sub_id) {
    require_admin();
    $body    = input_json();
    $updates = [];
    $params  = [];

    foreach (['currency','network','address','label','is_active','sort_order'] as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = $body[$field] === '' ? null : $body[$field];
        }
    }
    if (empty($updates)) api_error('Нет данных', 400);
    $params[] = $sub_id;
    db()->prepare("UPDATE payment_wallets SET " . implode(',', $updates) . " WHERE id = ?")->execute($params);
    api_success(['message' => 'Кошелёк обновлён']);
}

// ============================================================
// DELETE /api/payments/wallets/{id} — удалить кошелёк (админ)
// ============================================================
elseif ($method === 'DELETE' && $segment === 'wallets' && $sub_id) {
    require_admin();
    db()->prepare("DELETE FROM payment_wallets WHERE id = ?")->execute([$sub_id]);
    api_success(['message' => 'Кошелёк удалён']);
}

// ============================================================
// GET /api/payments — список платежей (только админ)
// Параметры: status=review|pending|approved|rejected, page, per_page
// ============================================================
elseif ($method === 'GET' && !$segment) {
    require_admin();

    $status   = input_str('status');
    $page     = max(1, input_int('page', 1));
    $per_page = min(50, max(1, input_int('per_page', 20)));
    $offset   = ($page - 1) * $per_page;

    $where  = [];
    $params = [];

    if ($status) {
        $where[]  = 'pay.status = ?';
        $params[] = $status;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = db()->prepare("SELECT COUNT(*) as total FROM payments pay $where_sql");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetch()['total'];

    $stmt = db()->prepare("
        SELECT
            pay.id,
            pay.profile_id,
            pay.wallet_id,
            pay.amount_usd,
            pay.currency,
            pay.txid,
            pay.screenshot_file,
            pay.status,
            pay.admin_note,
            pay.reviewed_at,
            pay.created_at,
            p.name        AS profile_name,
            p.slug        AS profile_slug,
            p.status      AS profile_status,
            c.name        AS city_name,
            pw.currency   AS wallet_currency,
            pw.network    AS wallet_network,
            pw.address    AS wallet_address
        FROM payments pay
        JOIN profiles p ON p.id = pay.profile_id
        JOIN cities   c ON c.id = p.city_id
        LEFT JOIN payment_wallets pw ON pw.id = pay.wallet_id
        $where_sql
        ORDER BY pay.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $payments = $stmt->fetchAll();

    foreach ($payments as &$pay) {
        $pay['id']         = (int)$pay['id'];
        $pay['profile_id'] = (int)$pay['profile_id'];
        $pay['amount_usd'] = $pay['amount_usd'] ? (float)$pay['amount_usd'] : null;
        if ($pay['screenshot_file']) {
            $pay['screenshot_url'] = SCREENSHOTS_URL . '/' . $pay['screenshot_file'];
        }
    }
    unset($pay);

    api_success($payments, 200, [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => (int)ceil($total / $per_page),
    ]);
}

// ============================================================
// POST /api/payments — пользователь отправляет подтверждение оплаты
// Принимает: multipart/form-data или JSON
// Поля: profile_id, wallet_id, currency, txid, screenshot (файл)
// ============================================================
elseif ($method === 'POST' && !$segment) {

    check_rate_limit('payment_submit_' . ($_SERVER['REMOTE_ADDR'] ?? ''));

    // Поддерживаем и multipart и JSON
    $is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;
    $profile_id   = $is_multipart ? (int)($_POST['profile_id'] ?? 0) : (int)(input_json()['profile_id'] ?? 0);
    $wallet_id    = $is_multipart ? (int)($_POST['wallet_id']  ?? 0) : (int)(input_json()['wallet_id']  ?? 0);
    $currency     = $is_multipart ? trim($_POST['currency'] ?? '') : trim(input_json()['currency'] ?? '');
    $txid         = $is_multipart ? trim($_POST['txid']     ?? '') : trim(input_json()['txid']     ?? '');

    if (!$profile_id) api_error('Не указан profile_id', 400);

    // Проверяем анкету
    $stmt = db()->prepare("
        SELECT p.id, p.status, pl.price_usd
        FROM profiles p
        LEFT JOIN plans pl ON pl.id = p.plan_id
        WHERE p.id = ?
          AND p.status IN ('pending_payment', 'payment_review')
        LIMIT 1
    ");
    $stmt->execute([$profile_id]);
    $profile = $stmt->fetch();

    if (!$profile) {
        api_error('Анкета не найдена или оплата не требуется', 404);
    }

    // Обязателен либо TXID, либо скриншот
    $screenshot_file = null;

    if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        // Обрабатываем скриншот оплаты (сохраняем как есть, без конвертации)
        if (!is_dir(SCREENSHOTS_DIR)) mkdir(SCREENSHOTS_DIR, 0755, true);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['screenshot']['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg','image/png','image/jpg','image/webp','application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            api_error('Скриншот должен быть изображением или PDF', 400);
        }

        $ext = match($mime) {
            'image/jpeg','image/jpg' => 'jpg',
            'image/png'              => 'png',
            'image/webp'             => 'webp',
            'application/pdf'        => 'pdf',
            default                  => 'bin',
        };

        $screenshot_file = 'pay_' . $profile_id . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], SCREENSHOTS_DIR . '/' . $screenshot_file)) {
            api_error('Ошибка сохранения скриншота', 500);
        }
    }

    if (!$txid && !$screenshot_file) {
        api_error('Укажите TXID транзакции или загрузите скриншот оплаты', 400);
    }

    // Получаем сумму из плана если не передана
    $amount = (float)(input_json()['amount_usd'] ?? $_POST['amount_usd'] ?? $profile['price_usd'] ?? 0);

    db()->prepare("
        INSERT INTO payments (profile_id, wallet_id, amount_usd, currency, txid, screenshot_file, status)
        VALUES (?, ?, ?, ?, ?, ?, 'review')
    ")->execute([
        $profile_id,
        $wallet_id ?: null,
        $amount > 0 ? $amount : null,
        $currency ?: null,
        $txid ?: null,
        $screenshot_file,
    ]);

    // Триггер автоматически переведёт анкету в payment_review
    api_success([
        'message' => 'Данные об оплате получены. Администратор проверит их в ближайшее время.',
        'status'  => 'payment_review',
    ], 201);
}

// ============================================================
// PUT /api/payments/{id}/approve — одобрить платёж (админ)
// ============================================================
elseif ($method === 'PUT' && is_numeric($segment) && $action === 'approve') {

    $admin      = require_admin();
    $payment_id = (int)$segment;
    $body       = input_json();

    $stmt = db()->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment)                       api_error('Платёж не найден', 404);
    if ($payment['status'] === 'approved') api_error('Платёж уже одобрен', 409);

    db()->prepare("
        UPDATE payments
        SET status = 'approved',
            admin_id = ?,
            admin_note = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ")->execute([
        $admin['id'],
        trim($body['note'] ?? '') ?: null,
        $payment_id,
    ]);

    // Триггер trg_payment_status_change автоматически активирует анкету

    api_success(['message' => 'Платёж одобрен. Анкета опубликована.']);
}

// ============================================================
// PUT /api/payments/{id}/reject — отклонить платёж (админ)
// ============================================================
elseif ($method === 'PUT' && is_numeric($segment) && $action === 'reject') {

    $admin      = require_admin();
    $payment_id = (int)$segment;
    $body       = input_json();
    $note       = trim($body['note'] ?? '');

    if (!$note) api_error('Укажите причину отклонения', 400);

    $stmt = db()->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) api_error('Платёж не найден', 404);

    db()->prepare("
        UPDATE payments
        SET status = 'rejected',
            admin_id = ?,
            admin_note = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ")->execute([$admin['id'], $note, $payment_id]);

    // Триггер переведёт анкету в payment_rejected

    api_success(['message' => 'Платёж отклонён.']);
}

else {
    api_error('Маршрут не найден', 404);
}
