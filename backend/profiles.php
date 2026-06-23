<?php
// profiles.php - CRUD обработчик анкет

require_once 'config.php';

// Получение действия
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($action === 'get' && isset($_GET['id'])) {
            getProfile($_GET['id']);
        } else {
            getProfiles();
        }
        break;
    
    case 'POST':
        requireAdmin();
        createProfile();
        break;
    
    case 'PUT':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Profile ID required'], 400);
        }
        updateProfile($_GET['id']);
        break;
    
    case 'DELETE':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Profile ID required'], 400);
        }
        deleteProfile($_GET['id']);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// Получение списка анкет с фильтрами
function getProfiles() {
    try {
        $pdo = getDB();
        
        // Параметры фильтрации
        $cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : null;
        $ageFrom = isset($_GET['age_from']) ? (int)$_GET['age_from'] : null;
        $ageTo = isset($_GET['age_to']) ? (int)$_GET['age_to'] : null;
        $isVip = isset($_GET['vip']) ? filter_var($_GET['vip'], FILTER_VALIDATE_BOOLEAN) : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        // Базовый запрос
        $sql = "SELECT 
                    p.*,
                    c.name as city_name,
                    t.name as tariff_name,
                    (SELECT file_name FROM profile_photos WHERE profile_id = p.id AND is_main = 1 LIMIT 1) as main_photo
                FROM profiles p
                LEFT JOIN cities c ON p.city_id = c.id
                LEFT JOIN tariffs t ON p.tariff_id = t.id
                WHERE p.status = 'active'";
        
        $params = [];
        
        // Фильтр по городу
        if ($cityId) {
            $sql .= " AND p.city_id = ?";
            $params[] = $cityId;
        }
        
        // Фильтр по возрасту
        if ($ageFrom) {
            $sql .= " AND p.age >= ?";
            $params[] = $ageFrom;
        }
        if ($ageTo) {
            $sql .= " AND p.age <= ?";
            $params[] = $ageTo;
        }
        
        // Фильтр по VIP
        if ($isVip !== null) {
            $sql .= " AND p.is_vip = ?";
            $params[] = $isVip ? 1 : 0;
        }
        
        // Поиск
        if (!empty($search)) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Сортировка
        switch ($sort) {
            case 'price_asc':
                $sql .= " ORDER BY t.price ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY t.price DESC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY p.name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY p.name DESC";
                break;
            case 'created_at_asc':
                $sql .= " ORDER BY p.created_at ASC";
                break;
            case 'created_at_desc':
            default:
                $sql .= " ORDER BY p.created_at DESC";
                break;
        }
        
        // Пагинация с использованием именованных параметров
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Привязка параметров фильтров
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }
        
        // Привязка LIMIT и OFFSET как целых чисел
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $profiles = $stmt->fetchAll();
        
        // Получение общего количества
        $countSql = "SELECT COUNT(*) as total FROM profiles p WHERE p.status = 'active'";
        $countParams = [];
        
        // Добавляем те же фильтры для подсчета
        if ($cityId) {
            $countSql .= " AND p.city_id = ?";
            $countParams[] = $cityId;
        }
        if ($ageFrom) {
            $countSql .= " AND p.age >= ?";
            $countParams[] = $ageFrom;
        }
        if ($ageTo) {
            $countSql .= " AND p.age <= ?";
            $countParams[] = $ageTo;
        }
        if ($isVip !== null) {
            $countSql .= " AND p.is_vip = ?";
            $countParams[] = $isVip ? 1 : 0;
        }
        if (!empty($search)) {
            $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse([
            'success' => true,
            'data' => $profiles,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to fetch profiles'], 500);
        }
    }
}

// Получение одной анкеты по ID
function getProfile($id) {
    try {
        $pdo = getDB();
        
        // Получение профиля
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                c.name as city_name,
                t.name as tariff_name,
                t.price as tariff_price
            FROM profiles p
            LEFT JOIN cities c ON p.city_id = c.id
            LEFT JOIN tariffs t ON p.tariff_id = t.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            jsonResponse(['error' => 'Profile not found'], 404);
        }
        
        // Получение всех фото
        $stmt = $pdo->prepare("
            SELECT id, file_name, is_main, sort_order 
            FROM profile_photos 
            WHERE profile_id = ? 
            ORDER BY sort_order ASC, is_main DESC
        ");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();
        
        // Проверка накрутки просмотров через сессию
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['viewed_profiles'])) {
            $_SESSION['viewed_profiles'] = [];
        }
        
        // Если профиль еще не просматривался в этой сессии
        if (!in_array($id, $_SESSION['viewed_profiles'])) {
            // Увеличение счетчика просмотров
            $stmt = $pdo->prepare("UPDATE profiles SET views = views + 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Запись ID в сессию
            $_SESSION['viewed_profiles'][] = $id;
            
            // Запись статистики
            logStat('profile_view', $id);
        }
        
        jsonResponse([
            'success' => true,
            'data' => [
                'profile' => $profile,
                'photos' => $photos
            ]
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to fetch profile'], 500);
        }
    }
}

// Создание анкеты
function createProfile() {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(['error' => 'Invalid input data'], 400);
        }
        
        // Валидация обязательных полей
        $required = ['name', 'age', 'city_id'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                jsonResponse(['error' => "Field '$field' is required"], 400);
            }
        }
        
        // Подготовка данных
        $name = sanitize($input['name']);
        $age = (int)$input['age'];
        $cityId = (int)$input['city_id'];
        $tariffId = isset($input['tariff_id']) && !empty($input['tariff_id']) ? (int)$input['tariff_id'] : null;
        $telegram = isset($input['telegram']) ? sanitize($input['telegram']) : null;
        $whatsapp = isset($input['whatsapp']) ? sanitize($input['whatsapp']) : null;
        $description = isset($input['description']) ? sanitize($input['description']) : null;
        $status = isset($input['status']) ? sanitize($input['status']) : 'pending';
        $isVip = isset($input['is_vip']) ? (int)$input['is_vip'] : 0;
        $mainPhoto = isset($input['main_photo']) ? sanitize($input['main_photo']) : null;
        
        // Проверка существования города
        $stmt = $pdo->prepare("SELECT id FROM cities WHERE id = ?");
        $stmt->execute([$cityId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'City not found'], 400);
        }
        
        // Проверка существования тарифа (если указан)
        if ($tariffId) {
            $stmt = $pdo->prepare("SELECT id FROM tariffs WHERE id = ?");
            $stmt->execute([$tariffId]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Tariff not found'], 400);
            }
        }
        
        // Вставка профиля
        $stmt = $pdo->prepare("
            INSERT INTO profiles (name, age, city_id, tariff_id, telegram, whatsapp, description, status, is_vip, main_photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $age, $cityId, $tariffId, $telegram, $whatsapp, $description, $status, $isVip, $mainPhoto]);
        $profileId = $pdo->lastInsertId();
        
        // Сохранение фото (если переданы)
        if (isset($input['photos']) && is_array($input['photos'])) {
            foreach ($input['photos'] as $index => $photo) {
                $fileName = sanitize($photo['file_name']);
                $isMain = isset($photo['is_main']) ? (int)$photo['is_main'] : ($index === 0 ? 1 : 0);
                $sortOrder = isset($photo['sort_order']) ? (int)$photo['sort_order'] : $index;
                
                $stmt = $pdo->prepare("
                    INSERT INTO profile_photos (profile_id, file_name, is_main, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$profileId, $fileName, $isMain, $sortOrder]);
            }
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Profile created successfully',
            'profile_id' => $profileId
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to create profile'], 500);
        }
    }
}

// Обновление анкеты
function updateProfile($id) {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(['error' => 'Invalid input data'], 400);
        }
        
        // Проверка существования профиля
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Profile not found'], 404);
        }
        
        // Подготовка данных для обновления
        $fields = [];
        $params = [];
        
        $allowedFields = ['name', 'age', 'city_id', 'tariff_id', 'telegram', 'whatsapp', 'description', 'status', 'is_vip', 'main_photo'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'age' || $field === 'city_id' || $field === 'tariff_id' || $field === 'is_vip') {
                    $fields[] = "$field = ?";
                    $params[] = (int)$input[$field];
                } else {
                    $fields[] = "$field = ?";
                    $params[] = sanitize($input[$field]);
                }
            }
        }
        
        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE profiles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Обновление фото (если переданы)
        if (isset($input['photos']) && is_array($input['photos'])) {
            // Удаление старых фото
            $stmt = $pdo->prepare("SELECT file_name FROM profile_photos WHERE profile_id = ?");
            $stmt->execute([$id]);
            $oldPhotos = $stmt->fetchAll();
            
            // Физическое удаление файлов
            foreach ($oldPhotos as $photo) {
                $filePath = UPLOAD_DIR . $photo['file_name'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            // Удаление записей из БД
            $stmt = $pdo->prepare("DELETE FROM profile_photos WHERE profile_id = ?");
            $stmt->execute([$id]);
            
            // Добавление новых фото
            foreach ($input['photos'] as $index => $photo) {
                $fileName = sanitize($photo['file_name']);
                $isMain = isset($photo['is_main']) ? (int)$photo['is_main'] : ($index === 0 ? 1 : 0);
                $sortOrder = isset($photo['sort_order']) ? (int)$photo['sort_order'] : $index;
                
                $stmt = $pdo->prepare("
                    INSERT INTO profile_photos (profile_id, file_name, is_main, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id, $fileName, $isMain, $sortOrder]);
            }
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to update profile'], 500);
        }
    }
}

// Удаление анкеты
function deleteProfile($id) {
    try {
        $pdo = getDB();
        
        // Проверка существования профиля
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Profile not found'], 404);
        }
        
        // Получение всех фото перед удалением
        $stmt = $pdo->prepare("SELECT file_name FROM profile_photos WHERE profile_id = ?");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();
        
        // Физическое удаление файлов изображений
        foreach ($photos as $photo) {
            $filePath = UPLOAD_DIR . $photo['file_name'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Удаление профиля (каскадно удалит фото и платежи)
        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Profile deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to delete profile'], 500);
        }
    }
}