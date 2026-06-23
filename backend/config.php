<?php
// config.php - Конфигурационный файл проекта PrivatClub

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'privatclub');
define('DB_USER', 'privatclub_user');
define('DB_PASS', 'your_secure_password_here');

// Настройки приложения
define('SITE_NAME', 'PrivatClub');
define('SITE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', SITE_PATH . '/images/');
define('UPLOAD_URL', '/images/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.cookie_samesite', 'Lax');

// Режим отладки (отключить на продакшене)
define('DEBUG_MODE', false);

// Подключение к базе данных
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Ошибка подключения к базе данных: ' . $e->getMessage());
            } else {
                die('Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.');
            }
        }
    }
    
    return $pdo;
}

// Проверка установки
function isInstalled() {
    return file_exists(__DIR__ . '/install.lock');
}

// Проверка авторизации администратора
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Требование авторизации
function requireAdmin() {
    if (!isAdmin()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Необходима авторизация']);
        exit;
    }
}

// JSON ответ
function jsonResponse($data, $code = 200) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Получение настройки (settings.setting_key, settings.setting_value)
function getSetting($key) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log('Ошибка получения настройки: ' . $e->getMessage());
        }
        return null;
    }
}

// Обновление настройки (settings.setting_key, settings.setting_value)
function updateSetting($key, $value) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log('Ошибка обновления настройки: ' . $e->getMessage());
        }
        return false;
    }
}

// Создание необходимых директорий
function createDirectories() {
    $directories = [
        SITE_PATH . '/images'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
            chmod($dir, 0755);
        }
    }
    
    return true;
}

// Валидация и очистка данных
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Генерация безопасного имени файла
function generateSafeFilename($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(16));
    return $name . '.' . $ext;
}

// Получение IP адреса
function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Запись статистики (stats.action, stats.profile_id, stats.ip)
function logStat($action, $profileId = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO stats (action, profile_id, ip) VALUES (?, ?, ?)");
        $stmt->execute([$action, $profileId, getClientIP()]);
        return true;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log('Ошибка записи статистики: ' . $e->getMessage());
        }
        return false;
    }
}

// Проверка расширения файла
function isAllowedExtension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS);
}

// Запуск сессии если ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}