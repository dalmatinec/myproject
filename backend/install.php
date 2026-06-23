<?php
// install.php - Установщик проекта PrivatClub

// Подключение конфигурации
require_once 'config.php';

// Проверка, что установка уже выполнена (используем функцию из config.php)
if (isInstalled()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Installation already completed']);
    exit;
}

// Проверка подключения к БД
try {
    $pdo = getDB();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Создание директорий
if (!createDirectories()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to create directories']);
    exit;
}

// Чтение схемы БД
$schemaFile = dirname(__DIR__) . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'schema.sql not found']);
    exit;
}

$schema = file_get_contents($schemaFile);
if ($schema === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to read schema.sql']);
    exit;
}

// Разделение запросов (игнорируем CREATE DATABASE и USE)
$queries = array_filter(array_map('trim', explode(';', $schema)), function($query) {
    if (empty($query)) {
        return false;
    }
    $upperQuery = strtoupper($query);
    return strpos($upperQuery, 'CREATE DATABASE') === false && 
           strpos($upperQuery, 'USE ') === false;
});

// Выполнение запросов
try {
    foreach ($queries as $query) {
        $pdo->exec($query);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to create tables: ' . $e->getMessage()]);
    exit;
}

// Создание администратора
$username = 'bigbro';
$password = 'gangster2026';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, 'superadmin')");
    $stmt->execute([$username, $passwordHash]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to create admin: ' . $e->getMessage()]);
    exit;
}

// Создание файла блокировки установки (соответствует isInstalled() из config.php)
if (!file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'))) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to create installation lock file']);
    exit;
}

// Удаление install.php после успешной установки
@unlink(__FILE__);

// Вывод успешного результата
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Installation completed'
]);