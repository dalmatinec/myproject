<?php
// =============================================
// WeToo - News API (Production — Webhook Only)
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// =============================================
// Роутинг через REQUEST_URI
// =============================================
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace('/api/news', '', $path);
if (empty($path)) {
    $path = '/';
}

switch ($path) {
    case '/':
        if ($method === 'GET') {
            handleGetList($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/webhook':
        // Только POST для Telegram Webhook
        if ($method === 'POST') {
            handleWebhook($pdo);
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
// ПОЛУЧЕНИЕ НОВОСТЕЙ (ПУБЛИЧНЫЙ API)
// =============================================

function handleGetList($pdo) {
    $params = $_GET;
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min(50, max(1, (int)($params['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM news");
        $total = $countStmt->fetch()['total'];
        
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
        echo json_encode(['error' => 'Database error']);
    }
}

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
        echo json_encode(['error' => 'Database error']);
    }
}

// =============================================
// WEBHOOK — ЕДИНСТВЕННЫЙ СПОСОБ ПОЛУЧЕНИЯ НОВОСТЕЙ
// =============================================

function handleWebhook($pdo) {
    // 1. Проверяем, что запрос от Telegram
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty request']);
        return;
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    // 2. Проверяем наличие channel_post
    if (!isset($data['channel_post'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        return;
    }
    
    // 3. Получаем настройки
    $settings = getSettings($pdo);
    $botToken = $settings['telegram_bot_token'] ?? '';
    $channelUsername = $settings['telegram_channel_username'] ?? '';
    
    if (empty($botToken)) {
        http_response_code(500);
        echo json_encode(['error' => 'Bot token not configured']);
        return;
    }
    
    $post = $data['channel_post'];
    $telegramPostId = $post['message_id'];
    $chatId = $post['chat']['id'] ?? 0;
    $chatUsername = $post['chat']['username'] ?? '';
    
    // 4. Проверяем, что пост из нашего канала
    if (!empty($channelUsername) && $chatUsername !== ltrim($channelUsername, '@')) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored_wrong_channel']);
        return;
    }
    
    // 5. Защита от дублей (Telegram может дублировать webhook-запросы)
    try {
        $stmt = $pdo->prepare("SELECT id FROM news WHERE telegram_post_id = ?");
        $stmt->execute([$telegramPostId]);
        if ($stmt->fetch()) {
            http_response_code(200);
            echo json_encode(['status' => 'duplicate']);
            return;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        return;
    }
    
    // 6. Сохраняем новость
    try {
        $text = $post['text'] ?? $post['caption'] ?? '';
        
        // Первая строка — заголовок, остальное — текст
        $lines = explode("\n", $text);
        $title = $lines[0] ?? 'Новость';
        $content = $text;
        
        $telegramUrl = "https://t.me/{$chatUsername}/{$telegramPostId}";
        
        // 7. Обработка изображения
        $imageUrl = null;
        if (isset($post['photo']) && !empty($post['photo'])) {
            $photo = end($post['photo']);
            $fileId = $photo['file_id'];
            
            $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
            $fileResponse = @file_get_contents($fileUrl);
            if ($fileResponse) {
                $fileData = json_decode($fileResponse, true);
                if ($fileData && isset($fileData['ok']) && $fileData['ok'] && isset($fileData['result']['file_path'])) {
                    $imageUrl = "https://api.telegram.org/file/bot{$botToken}/" . $fileData['result']['file_path'];
                }
            }
        }
        
        // 8. Генерация slug
        $slug = generateSlug($title);
        $stmt = $pdo->prepare("SELECT id FROM news WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug = $slug . '-' . $telegramPostId;
        }
        
        // 9. Сохраняем в БД
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
        
        // 10. Возвращаем успех Telegram
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// =============================================
// УДАЛЕНИЕ НОВОСТИ (АДМИН)
// =============================================

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
        echo json_encode(['error' => 'Database error']);
    }
}