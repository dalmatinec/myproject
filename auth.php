<?php
/**
 * WeToo — Аутентификация администраторов
 * Файл: api/auth.php
 *
 * Реализует JWT без сторонних библиотек.
 * Токен передаётся в заголовке: Authorization: Bearer <token>
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

// ============================================================
// JWT — минимальная реализация без зависимостей
// Алгоритм: HMAC-SHA256
// ============================================================

/**
 * Создать JWT-токен для администратора
 */
function jwt_create(int $admin_id, string $username): string
{
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'sub' => $admin_id,
        'usr' => $username,
        'iat' => time(),
        'exp' => time() + (JWT_EXPIRE_HOURS * 3600),
    ]));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    return "$header.$payload.$signature";
}

/**
 * Проверить JWT-токен и вернуть payload или false
 */
function jwt_verify(string $token): array|false
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;

    // Проверяем подпись
    $expected = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    if (!hash_equals($expected, $signature)) return false;

    // Декодируем payload
    $data = json_decode(base64url_decode($payload), true);
    if (!$data) return false;

    // Проверяем срок действия
    if (isset($data['exp']) && $data['exp'] < time()) return false;

    return $data;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Получить текущего аутентифицированного администратора.
 * Если не авторизован — возвращает null (не прерывает выполнение).
 */
function get_admin(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;

    $data = jwt_verify(trim($m[1]));
    if (!$data) return null;

    // Проверяем что администратор всё ещё активен в БД
    $stmt = db()->prepare("
        SELECT id, username, display_name, telegram, is_active
        FROM admins
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$data['sub']]);
    return $stmt->fetch() ?: null;
}

/**
 * Требовать аутентификацию администратора.
 * Прерывает выполнение с 401 если не авторизован.
 */
function require_admin(): array
{
    $admin = get_admin();
    if (!$admin) {
        api_error('Требуется авторизация', 401);
    }
    return $admin;
}

// ============================================================
// МАРШРУТИЗАЦИЯ ЗАПРОСОВ АВТОРИЗАЦИИ
// POST /api/auth/login  — вход администратора
// POST /api/auth/logout — выход (клиентский, токен не хранится)
// GET  /api/auth/me     — данные текущего администратора
// ============================================================
cors_headers();

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts  = explode('/', $path);
// Ожидаем: api/auth/{action}
$action = $parts[2] ?? '';

// ============================================================
// POST /api/auth/login
// Тело: { "username": "...", "password": "..." }
// ============================================================
if ($method === 'POST' && $action === 'login') {

    check_rate_limit('auth_login_' . ($_SERVER['REMOTE_ADDR'] ?? ''));

    $body     = input_json();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$password) {
        api_error('Введите логин и пароль', 400);
    }

    // Ищем администратора по логину
    $stmt = db()->prepare("
        SELECT id, username, password_hash, display_name, telegram, is_active
        FROM admins
        WHERE username = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Проверяем пароль через bcrypt
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        // Намеренная задержка для защиты от перебора
        usleep(500000); // 0.5 секунды
        api_error('Неверный логин или пароль', 401);
    }

    // Обновляем дату последнего входа
    db()->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")
        ->execute([$admin['id']]);

    // Создаём JWT-токен
    $token = jwt_create((int)$admin['id'], $admin['username']);

    api_success([
        'token'        => $token,
        'expires_in'   => JWT_EXPIRE_HOURS * 3600,
        'admin' => [
            'id'           => (int)$admin['id'],
            'username'     => $admin['username'],
            'display_name' => $admin['display_name'],
            'telegram'     => $admin['telegram'],
        ],
    ]);
}

// ============================================================
// GET /api/auth/me — данные текущего администратора
// ============================================================
elseif ($method === 'GET' && $action === 'me') {

    $admin = require_admin();

    api_success([
        'id'           => (int)$admin['id'],
        'username'     => $admin['username'],
        'display_name' => $admin['display_name'],
        'telegram'     => $admin['telegram'],
    ]);
}

// ============================================================
// PUT /api/auth/password — смена пароля администратора
// Тело: { "current_password": "...", "new_password": "..." }
// ============================================================
elseif ($method === 'PUT' && $action === 'password') {

    $admin = require_admin();
    $body  = input_json();

    $current = $body['current_password'] ?? '';
    $new     = $body['new_password'] ?? '';

    if (!$current || !$new) {
        api_error('Укажите текущий и новый пароль', 400);
    }
    if (strlen($new) < 8) {
        api_error('Новый пароль должен быть не менее 8 символов', 400);
    }

    // Получаем текущий хеш
    $stmt = db()->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$admin['id']]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password_hash'])) {
        api_error('Неверный текущий пароль', 401);
    }

    $new_hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    db()->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$new_hash, $admin['id']]);

    api_success(['message' => 'Пароль успешно изменён']);
}

else {
    api_error('Маршрут не найден', 404);
}
