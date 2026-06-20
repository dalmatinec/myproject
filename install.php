<?php
/**
 * WeToo — Установщик системы
 * Файл: install.php
 *
 * Назначение:
 *   - Проверяет подключение к базе данных
 *   - Применяет SQL-схему
 *   - Создаёт первого администратора
 *   - После успешной установки блокирует повторный запуск
 *
 * ВАЖНО: Удалить или переименовать файл после установки!
 */

declare(strict_types=1);

// Запрет кеширования страницы установщика
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: text/html; charset=utf-8');

// Константы путей
define('ROOT_DIR',    __DIR__);
define('DB_CONFIG',   ROOT_DIR . '/api/config.php');
define('SCHEMA_DIR',  ROOT_DIR . '/database');
define('LOCK_FILE',   ROOT_DIR . '/install.lock');

// Если установка уже выполнена — блокируем доступ
if (file_exists(LOCK_FILE)) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:#c00">Установка уже выполнена. Файл install.php заблокирован.</h2>');
}

// ============================================================
// Обработка формы установки
// ============================================================
$errors   = [];
$success  = false;
$step     = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Шаг 1: Параметры БД → тест подключения
    if ($_POST['action'] === 'test_db') {
        $db_host = trim($_POST['db_host'] ?? '');
        $db_port = (int)($_POST['db_port'] ?? 3306);
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';

        if (!$db_host || !$db_name || !$db_user) {
            $errors[] = 'Заполните все поля подключения к базе данных.';
        } else {
            try {
                $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                // Сохраняем параметры в сессии для следующего шага
                session_start();
                $_SESSION['install_db'] = compact('db_host','db_port','db_name','db_user','db_pass');
                $step = 2;
            } catch (PDOException $e) {
                $errors[] = 'Ошибка подключения: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // Шаг 2: Применение схемы + создание администратора
    elseif ($_POST['action'] === 'install') {
        session_start();
        $db = $_SESSION['install_db'] ?? null;

        if (!$db) {
            $errors[] = 'Сессия истекла. Начните установку заново.';
        } else {
            // Валидация данных администратора
            $admin_username = trim($_POST['admin_username'] ?? '');
            $admin_name     = trim($_POST['admin_name'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_confirm  = $_POST['admin_confirm'] ?? '';
            $admin_telegram = trim($_POST['admin_telegram'] ?? '');
            $site_url       = rtrim(trim($_POST['site_url'] ?? ''), '/');

            if (strlen($admin_username) < 3) {
                $errors[] = 'Логин должен быть не менее 3 символов.';
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $admin_username)) {
                $errors[] = 'Логин может содержать только латинские буквы, цифры и _';
            }
            if (strlen($admin_password) < 8) {
                $errors[] = 'Пароль должен быть не менее 8 символов.';
            }
            if ($admin_password !== $admin_confirm) {
                $errors[] = 'Пароли не совпадают.';
            }
            if (!$admin_name) {
                $errors[] = 'Укажите отображаемое имя администратора.';
            }

            if (empty($errors)) {
                try {
                    $dsn = "mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);

                    // Применяем SQL-схему по частям
                    $schema_files = [
                        SCHEMA_DIR . '/schema_v2_part1.sql',
                        SCHEMA_DIR . '/schema_v2_part2.sql',
                        SCHEMA_DIR . '/schema_v2_part3.sql',
                    ];

                    foreach ($schema_files as $file) {
                        if (!file_exists($file)) {
                            throw new RuntimeException("Файл схемы не найден: " . basename($file));
                        }
                        $sql = file_get_contents($file);

                        // Выполняем каждый SQL-оператор отдельно
                        $pdo->exec($sql);
                    }

                    // Создаём первого администратора
                    $password_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $telegram_clean = ltrim($admin_telegram, '@');

                    $stmt = $pdo->prepare("
                        INSERT INTO admins (username, password_hash, display_name, telegram, is_active)
                        VALUES (:username, :hash, :name, :telegram, 1)
                    ");
                    $stmt->execute([
                        ':username' => $admin_username,
                        ':hash'     => $password_hash,
                        ':name'     => $admin_name,
                        ':telegram' => $telegram_clean ?: null,
                    ]);

                    // Обновляем URL сайта в настройках
                    if ($site_url) {
                        $pdo->prepare("UPDATE settings SET setting_val = ? WHERE setting_key = 'site_url'")
                            ->execute([$site_url]);

                        // Обновляем admin_telegram если указан
                        if ($telegram_clean) {
                            $pdo->prepare("UPDATE settings SET setting_val = ? WHERE setting_key = 'admin_telegram'")
                                ->execute([$telegram_clean]);
                        }
                    }

                    // Создаём файл конфигурации БД
                    $config_content = "<?php\n/**\n * WeToo — Конфигурация базы данных\n * Сгенерировано установщиком " . date('Y-m-d H:i:s') . "\n */\ndefine('DB_HOST', " . var_export($db['db_host'], true) . ");\ndefine('DB_PORT', " . var_export((string)$db['db_port'], true) . ");\ndefine('DB_NAME', " . var_export($db['db_name'], true) . ");\ndefine('DB_USER', " . var_export($db['db_user'], true) . ");\ndefine('DB_PASS', " . var_export($db['db_pass'], true) . ");\n";

                    if (!is_dir(ROOT_DIR . '/api')) {
                        mkdir(ROOT_DIR . '/api', 0755, true);
                    }
                    file_put_contents(DB_CONFIG, $config_content);

                    // Создаём lock-файл, блокирующий повторный запуск
                    file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\nInstalled successfully.\n");

                    $success = true;
                    session_destroy();

                } catch (Exception $e) {
                    $errors[] = 'Ошибка установки: ' . htmlspecialchars($e->getMessage());
                }
            }
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
        /* Стили установщика — минималистичный интерфейс */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0B1215;
            color: #F5F5F5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer {
            background: #132026;
            border: 1px solid #1e3040;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 520px;
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #14B8A6, #0F766E);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }
        .logo p { color: #B8C2CC; margin-top: 6px; font-size: 0.9rem; }
        h2 { font-size: 1.2rem; margin-bottom: 24px; color: #14B8A6; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #B8C2CC; font-size: 0.85rem; }
        input[type="text"],
        input[type="password"],
        input[type="url"],
        input[type="number"] {
            width: 100%;
            padding: 10px 14px;
            background: #0B1215;
            border: 1px solid #1e3040;
            border-radius: 8px;
            color: #F5F5F5;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #14B8A6;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #0F766E, #14B8A6);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .errors {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #fca5a5;
            font-size: 0.9rem;
        }
        .errors li { margin-left: 16px; margin-top: 4px; }
        .success-box {
            text-align: center;
            padding: 20px 0;
        }
        .success-box .icon { font-size: 3rem; margin-bottom: 16px; }
        .success-box h2 { color: #14B8A6; margin-bottom: 12px; }
        .success-box p { color: #B8C2CC; margin-bottom: 8px; font-size: 0.9rem; }
        .warning {
            background: rgba(200,169,107,0.15);
            border: 1px solid rgba(200,169,107,0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 20px;
            color: #C8A96B;
            font-size: 0.85rem;
        }
        .divider { border: none; border-top: 1px solid #1e3040; margin: 24px 0; }
        .hint { color: #B8C2CC; font-size: 0.8rem; margin-top: 4px; }
        .row { display: grid; grid-template-columns: 1fr 120px; gap: 12px; }
    </style>
</head>
<body>
<div class="installer">
    <div class="logo">
        <h1>WeToo</h1>
        <p>Установка системы</p>
    </div>

    <?php if ($success): ?>
        <!-- Успешная установка -->
        <div class="success-box">
            <div class="icon">✅</div>
            <h2>Установка завершена!</h2>
            <p>База данных настроена.</p>
            <p>Администратор создан.</p>
            <p>Файл конфигурации сохранён.</p>
            <hr class="divider">
            <a href="/admin.html" class="btn" style="text-decoration:none;display:block">
                Войти в панель администратора →
            </a>
        </div>
        <div class="warning">
            ⚠️ Немедленно удалите файл <strong>install.php</strong> с сервера или он будет автоматически заблокирован повторным запуском.
        </div>

    <?php elseif ($step === 2): ?>
        <!-- Шаг 2: Данные администратора -->
        <h2>Шаг 2 из 2 — Администратор</h2>

        <?php if ($errors): ?>
            <div class="errors">
                <strong>Ошибки:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="install">

            <div class="form-group">
                <label>Логин администратора *</label>
                <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>" required pattern="[a-zA-Z0-9_]+" minlength="3">
                <p class="hint">Только латинские буквы, цифры и _</p>
            </div>

            <div class="form-group">
                <label>Отображаемое имя *</label>
                <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Пароль * (минимум 8 символов)</label>
                <input type="password" name="admin_password" required minlength="8">
            </div>

            <div class="form-group">
                <label>Повторите пароль *</label>
                <input type="password" name="admin_confirm" required>
            </div>

            <hr class="divider">

            <div class="form-group">
                <label>Telegram администратора (без @)</label>
                <input type="text" name="admin_telegram" placeholder="username">
                <p class="hint">Будет использован как контакт поддержки на сайте</p>
            </div>

            <div class="form-group">
                <label>URL сайта</label>
                <input type="text" name="site_url" placeholder="https://wetoo.example.com" value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>">
            </div>

            <button type="submit" class="btn">Завершить установку →</button>
        </form>

    <?php else: ?>
        <!-- Шаг 1: Параметры БД -->
        <h2>Шаг 1 из 2 — База данных</h2>

        <?php if ($errors): ?>
            <div class="errors">
                <strong>Ошибки:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="test_db">

            <div class="form-group">
                <div class="row">
                    <div>
                        <label>Хост базы данных *</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                    </div>
                    <div>
                        <label>Порт</label>
                        <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Имя базы данных *</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'wetoo') ?>" required>
            </div>

            <div class="form-group">
                <label>Пользователь БД *</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Пароль БД</label>
                <input type="password" name="db_pass">
            </div>

            <button type="submit" class="btn">Проверить подключение →</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
