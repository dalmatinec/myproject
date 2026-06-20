<?php
// =============================================
// WeToo Backend - Config
// =============================================

// ===== 1. НАСТРОЙКИ БД =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'wetoo');
define('DB_USER', 'wetoo_user');
define('DB_PASS', 'Elina180612');

// ===== 2. JWT =====
define('JWT_SECRET', 'xK9mN7pR3vW5qL2yT8fJ4bE6sH1uC0oA9gM6zN3vW5qL2yT8fJ4bE6sH1uC0o');
define('JWT_EXPIRE', 86400);

// ===== 3. ПУТИ =====
define('BASE_DIR', dirname(__DIR__));
define('IMAGES_DIR', BASE_DIR . '/images/');

// ===== 4. ФАЙЛЫ =====
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_PHOTOS', 5);

// ===== 5. ПОДКЛЮЧЕНИЕ К БД =====
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// ===== 6. ФУНКЦИИ =====

function getSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function generateSlug($string) {
    $translit = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
    ];

    $string = mb_strtolower($string, 'UTF-8');
    $string = strtr($string, $translit);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    $string = trim($string, '-');

    return $string . '-' . time();
}

function validateJWT($token) {
    if (empty($token)) return false;

    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    list($header64, $payload64, $signature) = $parts;

    $header = json_decode(base64_decode($header64), true);
    $payload = json_decode(base64_decode($payload64), true);

    if (!$header || !$payload) return false;

    if (isset($payload['exp']) && $payload['exp'] < time()) return false;

    $expectedSignature = hash_hmac('sha256', $header64 . '.' . $payload64, JWT_SECRET, true);
    $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

    if ($signature !== $expectedSignature) return false;

    return $payload;
}

function createJWT($userId, $username, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRE
    ]);

    $header64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payload64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $header64 . '.' . $payload64, JWT_SECRET, true);
    $signature64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $header64 . '.' . $payload64 . '.' . $signature64;
}

function requireAdmin($pdo) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    $payload = validateJWT($token);
    if (!$payload) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }

    return $payload;
}

function requireSuperAdmin($pdo) {
    $payload = requireAdmin($pdo);
    if ($payload['role'] !== 'superadmin') {
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden']));
    }
    return $payload;
}

function createDirectories() {
    if (!is_dir(IMAGES_DIR)) {
        mkdir(IMAGES_DIR, 0755, true);
    }
}