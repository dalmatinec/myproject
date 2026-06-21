<?php
// =============================================
// WeToo - Telegram Bot API (Production)
// =============================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleWebhook();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleWebhook() {
    global $pdo;
    
    require_once 'config.php';
    
    // 1. Проверяем, что $pdo существует
    if (!isset($pdo)) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }
    
    // 2. Проверяем входные данные
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
    
    // 3. Проверяем, что это сообщение от пользователя
    if (!isset($data['message'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        return;
    }
    
    $message = $data['message'];
    
    // 4. Защита от бесконечного цикла (бот не отвечает сам себе)
    if (isset($message['from']['is_bot']) && $message['from']['is_bot'] === true) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored_bot']);
        return;
    }
    
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'] ?? 0;
    $username = $message['from']['username'] ?? '';
    $firstName = $message['from']['first_name'] ?? '';
    $lastName = $message['from']['last_name'] ?? '';
    $text = trim($message['text'] ?? '');
    
    // 5. Получаем настройки
    $settings = getSettings($pdo);
    $botToken = $settings['telegram_bot_token'] ?? '';
    $supportUsername = $settings['telegram_support_username'] ?? '';
    $exchangeUsername = $settings['telegram_exchange_username'] ?? '';
    $siteUrl = $settings['site_url'] ?? '';
    
    if (empty($botToken)) {
        http_response_code(500);
        echo json_encode(['error' => 'Bot token not configured']);
        return;
    }
    
    // 6. Проверяем, что сообщение не слишком длинное
    if (strlen($text) > 10000) {
        $text = substr($text, 0, 10000) . '... (обрезано)';
    }
    
    // 7. Обрабатываем команды
    $response = '';
    
    if ($text === '/start') {
        $response = "👋 Добро пожаловать в WeToo!\n\n";
        $response .= "Я бот платформы проверенных анкет.\n\n";
        $response .= "📋 Нажмите кнопку ниже, чтобы управлять анкетами.\n";
        $response .= "📰 Читайте свежие новости.\n";
        $response .= "💬 Свяжитесь с поддержкой.\n";
        $response .= "💱 Помощь с обменом криптовалюты.";
    } elseif ($text === '📋 Анкеты') {
        $response = "📋 Управление анкетами:\n\n";
        $response .= "• Создать новую анкету: " . $siteUrl . "/add-profile.html\n";
        $response .= "• Просмотреть анкеты: " . $siteUrl . "/profiles.html";
    } elseif ($text === '📰 Новости') {
        $response = "📰 Свежие новости:\n\n";
        $response .= "Читайте все новости на сайте: " . $siteUrl . "/news.html";
        
        try {
            $stmt = $pdo->query("SELECT title, telegram_url FROM news ORDER BY published_at DESC LIMIT 5");
            $news = $stmt->fetchAll();
            if ($news) {
                $response .= "\n\n📌 Последние новости:\n";
                $i = 1;
                foreach ($news as $item) {
                    $response .= $i . ". " . $item['title'] . "\n";
                    $response .= "   " . $item['telegram_url'] . "\n";
                    $i++;
                }
            }
        } catch (Exception $e) {
            // Игнорируем
        }
    } elseif ($text === '💬 Поддержка') {
        if (!empty($supportUsername)) {
            $response = "💬 Свяжитесь с поддержкой:\n\n";
            $response .= "Напишите нам в Telegram: @" . $supportUsername;
        } else {
            $response = "💬 Поддержка временно недоступна. Попробуйте позже.";
        }
    } elseif ($text === '💱 Обмен криптовалюты') {
        if (!empty($exchangeUsername)) {
            $response = "💱 Помощь с обменом криптовалюты:\n\n";
            $response .= "Наш специалист поможет с обменом: @" . $exchangeUsername;
        } else {
            $response = "💱 Услуга обмена временно недоступна. Попробуйте позже.";
        }
    } elseif ($text === '🌐 Сайт') {
        $response = "🌐 Перейти на сайт WeToo:\n\n" . $siteUrl;
    } else {
        // Сохраняем обращение в БД
        try {
            $messageType = 'other';
            if (stripos($text, 'помощ') !== false || stripos($text, 'help') !== false) {
                $messageType = 'support';
            } elseif (stripos($text, 'обмен') !== false || stripos($text, 'крипт') !== false) {
                $messageType = 'exchange';
            } elseif (stripos($text, 'жалоб') !== false || stripos($text, 'complaint') !== false) {
                $messageType = 'complaint';
            }
            
            // Обрезаем текст для БД
            $dbText = substr($text, 0, 5000);
            
            $stmt = $pdo->prepare("
                INSERT INTO telegram_messages 
                (telegram_user_id, telegram_chat_id, telegram_username, first_name, last_name, message_type, message_text, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
            ");
            $stmt->execute([$userId, $chatId, $username, $firstName, $lastName, $messageType, $dbText]);
        } catch (Exception $e) {
            // Логируем ошибку, но не показываем пользователю
            error_log('Telegram bot DB error: ' . $e->getMessage());
        }
        
        $response = "❓ Я не понимаю эту команду.\n\n";
        $response .= "Пожалуйста, воспользуйтесь кнопками меню ниже.\n";
        $response .= "Если у вас есть вопрос, напишите его сюда — мы ответим.";
    }
    
    // 8. Отправляем ответ
    sendTelegramMessage($botToken, $chatId, $response);
}

function sendTelegramMessage($botToken, $chatId, $text) {
    // Обрезаем текст для Telegram (4096 символов максимум)
    if (strlen($text) > 4000) {
        $text = substr($text, 0, 4000) . '... (обрезано)';
    }
    
    $keyboard = [
        'keyboard' => [
            [
                ['text' => '📋 Анкеты'],
                ['text' => '📰 Новости']
            ],
            [
                ['text' => '💬 Поддержка'],
                ['text' => '💱 Обмен криптовалюты']
            ],
            [
                ['text' => '🌐 Сайт']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode($keyboard),
        'parse_mode' => 'HTML'
    ];
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $response;
}