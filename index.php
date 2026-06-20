<?php
/**
 * WeToo — Главный роутер API
 * Файл: api/index.php
 *
 * Принимает все запросы на /api/* и перенаправляет
 * на соответствующий обработчик.
 *
 * Маршрутная таблица:
 *   /api/auth/*       → auth.php
 *   /api/profiles/*   → profiles.php
 *   /api/cities/*     → cities.php
 *   /api/plans/*      → plans.php
 *   /api/payments/*   → payments.php
 *   /api/news/*       → news.php
 *   /api/settings/*   → settings.php
 *   /api/upload/*     → upload.php
 *   /api/admin/*      → admin.php
 *   /api/telegram/*   → telegram.php (Telegram Bot Webhook)
 */

declare(strict_types=1);

// Устанавливаем тип ответа по умолчанию
header('Content-Type: application/json; charset=utf-8');

// Базовый путь к файлам API
define('API_DIR', __DIR__);

// Получаем путь запроса
$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri   = trim($uri, '/');
$parts = explode('/', $uri);

// Ожидаем: api/{resource}/...
// $parts[0] = 'api'
// $parts[1] = resource (profiles, cities, ...)
$resource = $parts[1] ?? '';

// Таблица маршрутов: ключ → файл обработчика
$routes = [
    'auth'     => 'auth.php',
    'profiles' => 'profiles.php',
    'cities'   => 'cities.php',
    'plans'    => 'plans.php',
    'payments' => 'payments.php',
    'news'     => 'news.php',
    'settings' => 'settings.php',
    'upload'   => 'upload.php',
    'admin'    => 'admin.php',
    'telegram' => 'telegram.php',
];

if (isset($routes[$resource])) {
    $file = API_DIR . '/' . $routes[$resource];
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(501);
        echo json_encode(['success' => false, 'error' => 'Модуль временно недоступен']);
    }
} elseif (!$resource || $resource === 'api') {
    // Информация об API
    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'name'      => 'WeToo API',
        'version'   => 'v1',
        'endpoints' => array_keys($routes),
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Маршрут не найден']);
}
