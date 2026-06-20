<?php
// =============================================
// WeToo - Installer v2 (ФИНАЛЬНАЯ ВЕРСИЯ)
// =============================================

$lockFile = __DIR__ . '/../.installed';
if (file_exists($lockFile)) {
    die('Сайт уже установлен. Для переустановки удалите файл .installed');
}

require_once 'config.php';

$error = null;
$success = false;

/**
 * Полная очистка всех таблиц WeToo
 */
function rollbackTables($pdo) {
    $tables = ['settings', 'admins', 'cities', 'plans', 'profiles', 'profile_photos', 'payments', 'news', 'views', 'complaints', 'telegram_messages'];
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach (array_reverse($tables) as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUsername = trim($_POST['username'] ?? '');
    $adminPassword = $_POST['password'] ?? '';
    $adminPasswordConfirm = $_POST['password_confirm'] ?? '';
    $siteUrl = trim($_POST['site_url'] ?? '');
    
    if (strlen($adminUsername) < 3) {
        $error = 'Логин должен быть минимум 3 символа';
    } elseif (strlen($adminPassword) < 6) {
        $error = 'Пароль должен быть минимум 6 символов';
    } elseif ($adminPassword !== $adminPasswordConfirm) {
        $error = 'Пароли не совпадают';
    } elseif (empty($siteUrl)) {
        $error = 'Укажите URL сайта';
    }
    
    if (!$error) {
        try {
            // Проверяем, что БД пустая
            $stmt = $pdo->query("SHOW TABLES");
            if ($stmt->rowCount() > 0) {
                throw new Exception('В базе данных уже есть таблицы. Удалите их вручную или используйте чистую БД.');
            }
            
            // 1. Создаем папку images/
            createDirectories();
            
            // 2. Читаем schema.sql
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception('Файл schema.sql не найден');
            }
            
            $sql = file_get_contents($schemaFile);
            if (!$sql) {
                throw new Exception('Не удалось прочитать schema.sql');
            }
            
            // 3. Выполняем SQL-команды
            $commands = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($cmd) => !empty($cmd) && strpos($cmd, 'CREATE DATABASE') === false && strpos($cmd, 'USE ') === false
            );
            
            foreach ($commands as $command) {
                $pdo->exec($command);
            }
            
            // 4. Проверяем все необходимые таблицы
            $tables = ['settings', 'admins', 'cities', 'plans', 'profiles', 'profile_photos', 'payments', 'news', 'views', 'complaints', 'telegram_messages'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() === 0) {
                    rollbackTables($pdo);
                    throw new Exception("Таблица $table не создалась. Установка отменена.");
                }
            }
            
            // 5. Создаем администратора
            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, 'superadmin')");
            $stmt->execute([$adminUsername, $passwordHash]);
            
            // 6. Сохраняем настройки
            $settings = [
                'site_title' => 'WeToo',
                'site_description' => 'Платформа проверенных анкет',
                'site_url' => $siteUrl,
                'telegram_bot_token' => '',
                'telegram_channel_username' => '',
                'telegram_support_username' => '',
                'telegram_exchange_username' => '',
                'btc_wallet' => '',
                'eth_wallet' => '',
                'usdt_trc20_wallet' => ''
            ];
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            // 7. Создаем lock-файл с проверкой
            $result = file_put_contents($lockFile, date('Y-m-d H:i:s'));
            if ($result === false) {
                rollbackTables($pdo);
                throw new Exception('Не удалось создать файл блокировки .installed');
            }
            
            $success = true;
            
        } catch (Exception $e) {
            $error = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка WeToo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f0f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 50px; max-width: 500px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .logo { font-size: 32px; font-weight: 700; color: #6C63FF; text-align: center; margin-bottom: 8px; }
        .subtitle { text-align: center; color: #888; margin-bottom: 30px; font-size: 14px; }
        .desc { color: #666; font-size: 14px; margin-bottom: 24px; }
        .success-box { background: #e8f5e9; border: 2px solid #4CAF50; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px; }
        .success-box h2 { color: #2e7d32; font-size: 20px; margin-bottom: 8px; }
        .success-box p { color: #555; font-size: 14px; }
        .error-box { background: #ffebee; border: 2px solid #e53935; border-radius: 12px; padding: 16px; margin-bottom: 20px; color: #c62828; font-size: 14px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; color: #444; }
        input { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #6C63FF; }
        .btn { width: 100%; padding: 14px; background: #6C63FF; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #5a52d9; }
        .footer { text-align: center; margin-top: 20px; color: #aaa; font-size: 12px; }
        .credentials { background: #f8f7ff; border-radius: 10px; padding: 16px; margin: 16px 0; }
        .credentials strong { color: #6C63FF; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">WeToo</div>
        <div class="subtitle">Установка сервиса анкет</div>
        
        <?php if ($success): ?>
            <div class="success-box">
                <h2>✅ Установка завершена!</h2>
                <p>Сайт успешно установлен.</p>
                <div class="credentials">
                    <p><strong>Логин:</strong> <?= htmlspecialchars($adminUsername) ?></p>
                    <p><strong>Пароль:</strong> (установлен вами)</p>
                </div>
                <a href="/admin.html" style="display:inline-block;margin-top:12px;padding:10px 30px;background:#6C63FF;color:white;text-decoration:none;border-radius:8px;">Перейти в админку</a>
                <br><br>
                <a href="/" style="color:#6C63FF;text-decoration:none;">На главную →</a>
                <p style="margin-top:12px;font-size:12px;color:#999;">⚠️ Файл install.php рекомендуется удалить после установки</p>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <p class="desc">Создайте администратора и настройте базовые параметры сайта.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Логин администратора *</label>
                    <input type="text" name="username" placeholder="admin" required minlength="3" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" placeholder="Минимум 6 символов" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Повторите пароль *</label>
                    <input type="password" name="password_confirm" placeholder="Повторите пароль" required>
                </div>
                
                <div class="form-group">
                    <label>URL сайта *</label>
                    <input type="url" name="site_url" placeholder="https://your-domain.com" required value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>">
                    <small style="color:#999;font-size:12px;">Укажите полный URL, включая https://</small>
                </div>
                
                <button type="submit" class="btn">Установить WeToo</button>
            </form>
            
            <div class="footer">
                <p>При установке будут созданы все таблицы в БД <strong><?= DB_NAME ?></strong></p>
                <p style="margin-top:4px;">Пользователь БД: <?= DB_USER ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>