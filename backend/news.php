<?php
// =============================================
// WeToo - News API
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// =============================================
// Надежный роутинг через REQUEST_URI
// =============================================
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace('/api/news', '', $path);
if (empty($path)) {
    $path = '/';
}

// =============================================
// Роутер
// =============================================
switch ($path) {
    case '/':
        if ($method === 'GET') {
            handleGetList($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/sync':
        if ($method === 'POST') {
            handleSync($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    default:
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = (int)$matches[1];
            if ($method === 'GET') {
                handleGetOne($pdo, $id);
            } elseif ($method === 'DELETE') {
                handleDelete($pdo, $id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
        break;
}

// =============================================
// Обработчики
// =============================================

/**
 * GET /api/news/
 * Получение списка новостей (публичный)
 * 
 * Query параметры:
 * - limit: int (default 10)
 * - page: int (default 1)
 */
function handleGetList($pdo) {
    $params = $_GET;
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min(50, max(1, (int)($params['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    try {
        // Счетчик
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM news");
        $total = $countStmt->fetch()['total'];
        
        // Основной запрос
        $stmt = $pdo->prepare("
            SELECT id, slug, title, content, image_url, telegram_url, published_at, created_at
            FROM news
            ORDER BY published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $news = $stmt->fetchAll();
        
        echo json_encode([
            'data' => $news,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * GET /api/news/{id}
 * Получение одной новости (публичный)
 */
function handleGetOne($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, slug, title, content, image_url, telegram_url, published_at, created_at
            FROM news
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news) {
            http_response_code(404);
            echo json_encode(['error' => 'News not found']);
            return;
        }
        
        echo json_encode($news);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * POST /api/news/sync
 * Синхронизация новостей из Telegram канала (только для админа)
 * 
 * Запускается по cron: php /path/to/backend/news.php/sync
 * Или вручную через админку
 */
function handleSync($pdo) {
    // Проверяем, что запрос пришел от админа (если через HTTP)
    // Если запуск через cron, можно разрешить без авторизации
    $payload = null;
    try {
        $payload = requireAdmin($pdo);
        if (!$payload) {
            // Если через cron, токена нет — но мы разрешаем
            // Проверяем, что это CLI-запрос
            if (php_sapi_name() !== 'cli') {
                return;
            }
        }
    } catch (Exception $e) {
        // Для cron-запросов без авторизации
        if (php_sapi_name() !== 'cli') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
    }
    
    try {
        $settings = getSettings($pdo);
        $channelUsername = $settings['telegram_channel_username'] ?? '';
        $botToken = $settings['telegram_bot_token'] ?? '';
        $siteUrl = $settings['site_url'] ?? '';
        
        if (empty($channelUsername) || empty($botToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram channel or bot token not configured']);
            return;
        }
        
        // Получаем последние посты из канала через Telegram API
        $apiUrl = "https://api.telegram.org/bot{$botToken}/getUpdates";
        $response = file_get_contents($apiUrl);
        if (!$response) {
            throw new Exception('Failed to fetch from Telegram API');
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['ok']) || !$data['ok']) {
            throw new Exception('Telegram API error: ' . ($data['description'] ?? 'Unknown error'));
        }
        
        $saved = 0;
        $skipped = 0;
        
        // Обрабатываем обновления
        foreach ($data['result'] as $update) {
            // Проверяем наличие поста в канале
            if (isset($update['channel_post'])) {
                $post = $update['channel_post'];
                $telegramPostId = $post['message_id'];
                
                // Проверяем, существует ли уже пост
                $stmt = $pdo->prepare("SELECT id FROM news WHERE telegram_post_id = ?");
                $stmt->execute([$telegramPostId]);
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                // Извлекаем текст и фото
                $text = $post['text'] ?? $post['caption'] ?? '';
                $title = explode("\n", $text)[0] ?? 'Новость';
                $content = $text;
                
                $imageUrl = null;
                $telegramUrl = "https://t.me/{$channelUsername}/{$telegramPostId}";
                
                // Проверяем наличие фото
                if (isset($post['photo']) && !empty($post['photo'])) {
                    $photo = end($post['photo']);
                    $fileId = $photo['file_id'];
                    
                    // Получаем ссылку на файл
                    $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
                    $fileResponse = file_get_contents($fileUrl);
                    $fileData = json_decode($fileResponse, true);
                    
                    if ($fileData && isset($fileData['result']['file_path'])) {
                        $imageUrl = "https://api.telegram.org/file/bot{$botToken}/" . $fileData['result']['file_path'];
                    }
                }
                
                // Генерируем slug
                $slug = generateSlug($title);
                // Проверяем уникальность slug
                $stmt = $pdo->prepare("SELECT id FROM news WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    $slug = $slug . '-' . $telegramPostId;
                }
                
                // Сохраняем новость
                $publishedAt = date('Y-m-d H:i:s', $post['date'] ?? time());
                $stmt = $pdo->prepare("
                    INSERT INTO news (telegram_post_id, slug, title, content, image_url, telegram_url, published_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $telegramPostId,
                    $slug,
                    $title,
                    $content,
                    $imageUrl,
                    $telegramUrl,
                    $publishedAt
                ]);
                
                $saved++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'saved' => $saved,
            'skipped' => $skipped,
            'message' => "Saved {$saved} new posts, skipped {$skipped} duplicates"
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Sync error: ' . $e->getMessage()]);
    }
}

/**
 * DELETE /api/news/{id}
 * Удаление новости (только для админа)
 */
function handleDelete($pdo, $id) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'News not found']);
            return;
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}