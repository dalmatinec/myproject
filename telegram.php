<?php
/**
 * WeToo — Telegram Bot Webhook
 * Файл: api/telegram.php
 *
 * Принимает обновления от Telegram Bot API.
 * Готов к расширению для:
 *   - Публикации новостей из Telegram-канала
 *   - Уведомлений администраторам
 *   - Синхронизации с telegram_sync таблицей
 *
 * Настройка вебхука:
 *   https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://yoursite.com/api/telegram
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Webhook доступен только если настроен токен бота
if (!TG_BOT_TOKEN) {
    http_response_code(503);
    echo json_encode(['error' => 'Telegram Bot не настроен']);
    exit;
}

// Получаем обновление от Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Пустое обновление']);
    exit;
}

// Логируем входящее обновление (для отладки, отключить на production)
// file_put_contents('/tmp/tg_updates.log', json_encode($update) . "\n", FILE_APPEND);

$update_id = $update['update_id'] ?? 0;
$message   = $update['message']        ?? null;
$channel_post = $update['channel_post'] ?? null;

// ============================================================
// Обработка поста из Telegram-канала
// Если канал совпадает с настройкой telegram_channel — создаём новость
// ============================================================
if ($channel_post) {
    $channel_name = ltrim($channel_post['chat']['username'] ?? '', '@');
    $expected_channel = get_setting('telegram_channel');

    if ($channel_name && $channel_name === $expected_channel) {
        $text       = $channel_post['text'] ?? $channel_post['caption'] ?? '';
        $message_id = $channel_post['message_id'] ?? 0;

        if ($text && $message_id) {
            // Первая строка текста — заголовок
            $lines   = explode("\n", $text, 2);
            $title   = trim($lines[0]);
            $content = trim($lines[1] ?? $text);

            if ($title && strlen($title) <= 255) {
                $pdo = db();

                // Проверяем что такой пост ещё не синхронизирован
                $check = $pdo->prepare("
                    SELECT id FROM telegram_sync
                    WHERE telegram_message_id = ? AND telegram_channel = ?
                    LIMIT 1
                ");
                $check->execute([$message_id, $channel_name]);

                if (!$check->fetch()) {
                    // Генерируем slug для новости
                    $slug = generate_slug($title, 'news', 'slug');

                    // Создаём новость
                    $stmt = $pdo->prepare("
                        INSERT INTO news (title, slug, content, source, is_published, published_at)
                        VALUES (?, ?, ?, 'telegram', 1, NOW())
                    ");
                    $stmt->execute([$title, $slug, nl2br(htmlspecialchars($content))]);
                    $news_id = (int)$pdo->lastInsertId();

                    // Записываем в telegram_sync
                    $pdo->prepare("
                        INSERT INTO telegram_sync
                            (entity_type, entity_id, telegram_message_id, telegram_channel, direction)
                        VALUES ('news', ?, ?, ?, 'incoming')
                    ")->execute([$news_id, $message_id, $channel_name]);
                }
            }
        }
    }
}

// Отвечаем Telegram 200 OK (иначе он будет повторять запросы)
http_response_code(200);
echo json_encode(['ok' => true]);
