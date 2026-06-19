cat > /home/claude/wetoo/api/config.php << 'PHPEOF'
<?php
/**
 * WeToo — Конфигурация системы
 * Файл: api/config.php
 *
 * Все параметры подключения генерируются установщиком install.php
 * Никаких хардкодных данных — всё через БД и этот файл конфигурации
 */

declare(strict_types=1);

// ============================================================
// ПАРАМЕТРЫ БАЗЫ ДАННЫХ
// Заполняются установщиком автоматически
// ============================================================
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'wetoo');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// ============================================================
// ПУТИ К ФАЙЛАМ
// ============================================================
define('ROOT_DIR',        dirname(__DIR__));
define('UPLOADS_DIR',     ROOT_DIR . '/images/profiles');
define('UPLOADS_URL',     '/images/profiles');
define('SCREENSHOTS_DIR', ROOT_DIR . '/images/payments');
define('SCREENSHOTS_URL', '/images/payments');

// ============================================================
// ПАРАМЕТРЫ ЗАГРУЗКИ ФОТО
// ============================================================
define('MAX_PHOTOS',          5);
define('MAX_FILE_SIZE',       10485760);  // 10 MB
define('WEBP_QUALITY',        82);
define('IMAGE_MAX_SIDE',      1600);
define('ALLOWED_MIME_TYPES',  ['image/jpeg', 'image/png', 'image/jpg']);

// ============================================================
// ПАРАМЕТРЫ БЕЗОПАСНОСТИ
// ============================================================
define('JWT_SECRET',      getenv('JWT_SECRET') ?: 'change-this-secret-in-production');
define('JWT_EXPIRE_HOURS',24);
define('RATE_LIMIT_WINDOW',60);
define('RATE_LIMIT_MAX',  60);
define('BCRYPT_COST',     12);

// ============================================================
// ПАРАМЕТРЫ API
// ============================================================
define('API_VERSION',     'v1');
define('DEFAULT_PAGE_SIZE',20);
define('MAX_PAGE_SIZE',   100);

// ============================================================
// TELEGRAM BOT
// Токен только из переменной окружения, не в коде
// ============================================================
define('TG_BOT_TOKEN',    getenv('TG_BOT_TOKEN') ?: '');
define('TG_API_URL',      'https://api.telegram.org/bot');

// ============================================================
// ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ — singleton
// ============================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            api_error('Ошибка подключения к базе данных', 503);
        }
    }
    return $pdo;
}

// ============================================================
// СТАНДАРТНЫЕ ОТВЕТЫ API
// ============================================================
function api_success(mixed $data = null, int $code = 200, array $meta = []): never
{
    http_response_code($code);
    $response = ['success' => true, 'data' => $data];
    if (!empty($meta)) $response['meta'] = $meta;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $code = 400, array $errors = []): never
{
    http_response_code($code);
    $response = ['success' => false, 'error' => $message];
    if (!empty($errors)) $response['errors'] = $errors;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// НАСТРОЙКИ ИЗ БД — кеш на время запроса
// ============================================================
function get_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = db()->prepare("SELECT setting_val FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['setting_val'] : $default;
    }
    return $cache[$key];
}

function get_settings(array $keys): array
{
    if (empty($keys)) return [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = db()->prepare("SELECT setting_key, setting_val FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $result = array_fill_keys($keys, '');
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['setting_key']] = (string)$row['setting_val'];
    }
    return $result;
}

// ============================================================
// CORS
// ============================================================
function cors_headers(): void
{
    $origin = get_setting('site_url', '*');
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ============================================================
// RATE LIMITING — файловый кеш, не нагружает БД
// ============================================================
function check_rate_limit(string $identifier = ''): void
{
    if (!$identifier) $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cache_dir  = sys_get_temp_dir() . '/wetoo_rl';
    $cache_file = $cache_dir . '/' . md5($identifier) . '.json';
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0700, true);
    $now  = time();
    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($cache_file)) {
        $saved = json_decode(file_get_contents($cache_file), true);
        if ($saved && ($now - $saved['window_start']) < RATE_LIMIT_WINDOW) {
            $data = $saved;
        }
    }
    $data['count']++;
    file_put_contents($cache_file, json_encode($data), LOCK_EX);
    if ($data['count'] > RATE_LIMIT_MAX) {
        header('Retry-After: ' . (RATE_LIMIT_WINDOW - ($now - $data['window_start'])));
        api_error('Слишком много запросов. Попробуйте позже.', 429);
    }
}

// ============================================================
// ГЕНЕРАЦИЯ УНИКАЛЬНОГО SLUG
// ============================================================
function generate_slug(string $text, string $table = '', string $column = 'slug'): string
{
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo',
        'Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M',
        'Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U',
        'Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch',
        'Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
    ];
    $slug = strtr($text, $translit);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 120);
    if ($table) {
        $base = $slug;
        $i = 1;
        while (true) {
            $stmt = db()->prepare("SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1");
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $i++;
        }
    }
    if (!$slug) $slug = 'profile-' . bin2hex(random_bytes(4));
    return $slug;
}

// ============================================================
// ХЕЛПЕРЫ ВХОДНЫХ ДАННЫХ
// ============================================================
function input_int(string $key, int $default = 0, array $source = []): int
{
    $source = $source ?: $_GET;
    return isset($source[$key]) ? (int)$source[$key] : $default;
}

function input_str(string $key, string $default = '', array $source = []): string
{
    $source = $source ?: $_GET;
    return isset($source[$key]) ? trim((string)$source[$key]) : $default;
}

function input_json(): array
{
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?? []) : [];
    }
    return $body;
}

// ============================================================
// ЗАПИСЬ ПРОСМОТРА — дедупликация: 1 IP = 1 просмотр в час
// ============================================================
function record_view(int $profile_id): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    $stmt = db()->prepare("
        SELECT id FROM views
        WHERE profile_id = ? AND ip_address = ?
          AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$profile_id, $ip]);
    if (!$stmt->fetch()) {
        db()->prepare("INSERT INTO views (profile_id, ip_address, user_agent) VALUES (?,?,?)")
            ->execute([$profile_id, $ip, $ua]);
    }
}

// ============================================================
// ЗАПИСЬ КЛИКА ПО КОНТАКТУ — дедупликация: 1 IP = 1 клик в 10 минут
// ============================================================
function record_contact_click(int $profile_id, string $contact_type): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    $stmt = db()->prepare("
        SELECT id FROM contact_clicks
        WHERE profile_id = ? AND contact_type = ? AND ip_address = ?
          AND clicked_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$profile_id, $contact_type, $ip]);
    if (!$stmt->fetch()) {
        db()->prepare("INSERT INTO contact_clicks (profile_id, contact_type, ip_address, user_agent) VALUES (?,?,?,?)")
            ->execute([$profile_id, $contact_type, $ip, $ua]);
    }
}
PHPEOF