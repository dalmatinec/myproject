<?php
// =============================================
// WeToo - Profiles API
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
$path = str_replace('/api/profiles', '', $path);
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
        } elseif ($method === 'POST') {
            handleCreate($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/cities':
        if ($method === 'GET') {
            handleGetCities($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/plans':
        if ($method === 'GET') {
            handleGetPlans($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    default:
        // Проверяем, есть ли ID в пути (например /123)
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = (int)$matches[1];
            if ($method === 'GET') {
                handleGetOne($pdo, $id);
            } elseif ($method === 'PUT') {
                handleUpdate($pdo, $id);
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
 * GET /api/profiles/
 * Получение списка анкет с фильтрами
 * 
 * Query параметры:
 * - city_id: int
 * - age_from: int
 * - age_to: int
 * - status: string (active, pending, rejected)
 * - vip: boolean (1/0)
 * - sort: string (new, vip, top)
 * - page: int
 * - limit: int
 * - search: string
 */
function handleGetList($pdo) {
    $params = $_GET;
    
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min(50, max(1, (int)($params['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    
    // Базовый запрос
    $where = ["p.status = 'active'"];
    $queryParams = [];
    $join = "";
    $orderBy = "p.created_at DESC";
    
    // Фильтр по городу
    if (!empty($params['city_id'])) {
        $where[] = "p.city_id = ?";
        $queryParams[] = (int)$params['city_id'];
    }
    
    // Фильтр по возрасту
    if (!empty($params['age_from'])) {
        $where[] = "p.age >= ?";
        $queryParams[] = (int)$params['age_from'];
    }
    if (!empty($params['age_to'])) {
        $where[] = "p.age <= ?";
        $queryParams[] = (int)$params['age_to'];
    }
    
    // Фильтр по статусу (для админки)
    if (!empty($params['status']) && in_array($params['status'], ['pending', 'active', 'rejected', 'blocked', 'expired'])) {
        $where = ["p.status = ?"];
        $queryParams = [(string)$params['status']];
    }
    
    // Фильтр VIP (только для публичной страницы)
    if (!empty($params['vip']) && $params['vip'] == 1) {
        $join = "INNER JOIN plans pl ON p.plan_id = pl.id";
        $where[] = "pl.name IN ('VIP', 'ТОП')";
        $where[] = "p.expires_at > NOW()";
    }
    
    // VIP и ТОП сортировка для главной
    if (!empty($params['sort'])) {
        if ($params['sort'] === 'vip') {
            $join = "LEFT JOIN plans pl ON p.plan_id = pl.id";
            $orderBy = "CASE WHEN pl.name IN ('VIP', 'ТОП') AND p.expires_at > NOW() THEN 1 ELSE 0 END DESC, p.created_at DESC";
        } elseif ($params['sort'] === 'new') {
            $orderBy = "p.created_at DESC";
        } elseif ($params['sort'] === 'top') {
            $join = "LEFT JOIN plans pl ON p.plan_id = pl.id";
            $orderBy = "CASE WHEN pl.name = 'ТОП' AND p.expires_at > NOW() THEN 1 ELSE 0 END DESC, p.created_at DESC";
        }
    }
    
    // Поиск
    if (!empty($params['search'])) {
        $search = '%' . $params['search'] . '%';
        $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.telegram LIKE ?)";
        $queryParams[] = $search;
        $queryParams[] = $search;
        $queryParams[] = $search;
    }
    
    $whereSQL = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    
    // Счетчик
    $countSQL = "SELECT COUNT(DISTINCT p.id) as total FROM profiles p $join $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($queryParams);
    $total = $countStmt->fetch()['total'];
    
    // Основной запрос
    $fields = "p.*, c.name as city_name, pl.name as plan_name, 
               (SELECT filename FROM profile_photos WHERE profile_id = p.id AND is_main = 1 LIMIT 1) as main_photo";
    
    $sql = "SELECT $fields FROM profiles p 
            LEFT JOIN cities c ON p.city_id = c.id 
            LEFT JOIN plans pl ON p.plan_id = pl.id
            $join
            $whereSQL 
            GROUP BY p.id 
            ORDER BY $orderBy 
            LIMIT $offset, $limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $profiles = $stmt->fetchAll();
    
    // Загружаем все фото для каждой анкеты
    foreach ($profiles as &$profile) {
        $photoStmt = $pdo->prepare("SELECT filename, is_main FROM profile_photos WHERE profile_id = ? ORDER BY sort_order, id");
        $photoStmt->execute([$profile['id']]);
        $profile['photos'] = $photoStmt->fetchAll();
        $profile['photo_urls'] = array_column($profile['photos'], 'filename');
    }
    
    echo json_encode([
        'data' => $profiles,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * GET /api/profiles/cities
 * Получение списка городов
 */
function handleGetCities($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order, name");
    echo json_encode($stmt->fetchAll());
}

/**
 * GET /api/profiles/plans
 * Получение списка тарифов
 */
function handleGetPlans($pdo) {
    $stmt = $pdo->query("SELECT id, name, price_usdt, price_btc, price_eth, duration_days FROM plans WHERE is_active = 1 ORDER BY sort_order");
    echo json_encode($stmt->fetchAll());
}

/**
 * GET /api/profiles/{id}
 * Получение одной анкеты
 */
function handleGetOne($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as city_name, pl.name as plan_name 
        FROM profiles p 
        LEFT JOIN cities c ON p.city_id = c.id 
        LEFT JOIN plans pl ON p.plan_id = pl.id
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found']);
        return;
    }
    
    // Загружаем фото
    $photoStmt = $pdo->prepare("SELECT filename, is_main FROM profile_photos WHERE profile_id = ? ORDER BY sort_order, id");
    $photoStmt->execute([$id]);
    $profile['photos'] = $photoStmt->fetchAll();
    $profile['photo_urls'] = array_column($profile['photos'], 'filename');
    
    // Увеличиваем счетчик просмотров
    $pdo->prepare("UPDATE profiles SET views_count = views_count + 1 WHERE id = ?")->execute([$id]);
    
    // Логируем просмотр
    $stmt = $pdo->prepare("INSERT INTO views (profile_id, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    
    echo json_encode($profile);
}

/**
 * POST /api/profiles/
 * Создание новой анкеты (публичная форма)
 * 
 * Body: multipart/form-data
 * - name, age, city_id, description, telegram, whatsapp, price, plan_id
 * - photos[]: файлы (до 5 шт)
 */
function handleCreate($pdo) {
    // Проверяем обязательные поля
    $name = trim($_POST['name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $telegram = trim($_POST['telegram'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    
    // Валидация
    if (empty($name) || strlen($name) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Имя должно быть минимум 2 символа']);
        return;
    }
    
    if ($age < 18 || $age > 120) {
        http_response_code(400);
        echo json_encode(['error' => 'Возраст должен быть от 18 до 120 лет']);
        return;
    }
    
    if ($city_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Выберите город']);
        return;
    }
    
    if (empty($description) || strlen($description) < 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Описание должно быть минимум 10 символов']);
        return;
    }
    
    if (empty($telegram) || strpos($telegram, '@') !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите корректный Telegram (@username)']);
        return;
    }
    
    if ($plan_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Выберите тариф']);
        return;
    }
    
    // Проверяем город
    $stmt = $pdo->prepare("SELECT id FROM cities WHERE id = ? AND is_active = 1");
    $stmt->execute([$city_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Город не найден или отключен']);
        return;
    }
    
    // Проверяем тариф
    $stmt = $pdo->prepare("SELECT id, duration_days FROM plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    if (!$plan) {
        http_response_code(400);
        echo json_encode(['error' => 'Тариф не найден или отключен']);
        return;
    }
    
    // Проверяем фото
    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        http_response_code(400);
        echo json_encode(['error' => 'Загрузите хотя бы одно фото']);
        return;
    }
    
    $photoCount = count($_FILES['photos']['name']);
    if ($photoCount > MAX_PHOTOS) {
        http_response_code(400);
        echo json_encode(['error' => 'Максимум ' . MAX_PHOTOS . ' фотографий']);
        return;
    }
    
    // Создаем анкету
    $slug = generateSlug($name);
    $pdo->beginTransaction();
    
    try {
        // Вставляем анкету
        $stmt = $pdo->prepare("
            INSERT INTO profiles (slug, city_id, plan_id, name, age, description, telegram, whatsapp, price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$slug, $city_id, $plan_id, $name, $age, $description, $telegram, $whatsapp, $price]);
        $profileId = $pdo->lastInsertId();
        
        // Создаем папку images/ если нет
        createDirectories();
        
        // Сохраняем фотографии
        $uploadedFiles = [];
        $files = $_FILES['photos'];
        
        for ($i = 0; $i < $photoCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Проверяем расширение
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                continue;
            }
            
            // Проверяем размер
            if ($files['size'][$i] > MAX_FILE_SIZE) {
                continue;
            }
            
            // Генерируем имя файла
            $filename = 'profile_' . $profileId . '_' . time() . '_' . $i . '.' . $ext;
            $path = IMAGES_DIR . $filename;
            
            if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                $is_main = ($i === 0) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO profile_photos (profile_id, filename, sort_order, is_main) VALUES (?, ?, ?, ?)");
                $stmt->execute([$profileId, $filename, $i, $is_main]);
                $uploadedFiles[] = $filename;
            }
        }
        
        if (empty($uploadedFiles)) {
            throw new Exception('Не удалось загрузить фотографии');
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'id' => $profileId,
            'message' => 'Анкета создана и отправлена на модерацию'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * PUT /api/profiles/{id}
 * Обновление анкеты (только для админа)
 */
function handleUpdate($pdo, $id) {
    $payload = requireAdmin($pdo);
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    // Проверяем существование анкеты
    $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found']);
        return;
    }
    
    // Собираем поля для обновления
    $fields = [];
    $params = [];
    
    $allowedFields = ['name', 'age', 'city_id', 'description', 'telegram', 'whatsapp', 'price', 'status'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $params[] = $id;
    $sql = "UPDATE profiles SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated'
    ]);
}

/**
 * DELETE /api/profiles/{id}
 * Удаление анкеты (только для админа)
 */
function handleDelete($pdo, $id) {
    $payload = requireAdmin($pdo);
    
    // Проверяем, есть ли платежи (защита от удаления с платежами)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE profile_id = ?");
    $stmt->execute([$id]);
    $payments = $stmt->fetch()['count'];
    
    if ($payments > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete profile with existing payments. Delete payments first.']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile deleted'
    ]);
}