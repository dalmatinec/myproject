<?php
// profiles.php - CRUD обработчик анкет

require_once 'config.php';

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

function getProfiles() {
    try {
        $pdo = getDB();
        $cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : null;
        $ageFrom = isset($_GET['age_from']) ? (int)$_GET['age_from'] : null;
        $ageTo = isset($_GET['age_to']) ? (int)$_GET['age_to'] : null;
        $isVip = isset($_GET['vip']) ? filter_var($_GET['vip'], FILTER_VALIDATE_BOOLEAN) : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $sql = "SELECT p.*, c.name as city_name, t.name as tariff_name,
                (SELECT file_name FROM profile_photos WHERE profile_id = p.id AND is_main = 1 LIMIT 1) as main_photo
                FROM profiles p
                LEFT JOIN cities c ON p.city_id = c.id
                LEFT JOIN tariffs t ON p.tariff_id = t.id
                WHERE p.status = 'active'";
        $params = [];

        if ($cityId) { $sql .= " AND p.city_id = ?"; $params[] = $cityId; }
        if ($ageFrom) { $sql .= " AND p.age >= ?"; $params[] = $ageFrom; }
        if ($ageTo) { $sql .= " AND p.age <= ?"; $params[] = $ageTo; }
        if ($isVip !== null) { $sql .= " AND p.is_vip = ?"; $params[] = $isVip ? 1 : 0; }
        if (!empty($search)) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm; $params[] = $searchTerm;
        }

        switch ($sort) {
            case 'price_asc': $sql .= " ORDER BY t.price ASC"; break;
            case 'price_desc': $sql .= " ORDER BY t.price DESC"; break;
            case 'name_asc': $sql .= " ORDER BY p.name ASC"; break;
            case 'name_desc': $sql .= " ORDER BY p.name DESC"; break;
            case 'created_at_asc': $sql .= " ORDER BY p.created_at ASC"; break;
            default: $sql .= " ORDER BY p.created_at DESC"; break;
        }

        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key + 1, $value); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $profiles = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) as total FROM profiles p WHERE p.status = 'active'";
        $countParams = [];
        if ($cityId) { $countSql .= " AND p.city_id = ?"; $countParams[] = $cityId; }
        if ($ageFrom) { $countSql .= " AND p.age >= ?"; $countParams[] = $ageFrom; }
        if ($ageTo) { $countSql .= " AND p.age <= ?"; $countParams[] = $ageTo; }
        if ($isVip !== null) { $countSql .= " AND p.is_vip = ?"; $countParams[] = $isVip ? 1 : 0; }
        if (!empty($search)) {
            $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $countParams[] = $searchTerm; $countParams[] = $searchTerm;
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];

        jsonResponse(['success' => true, 'data' => $profiles,
            'pagination' => ['total' => (int)$total, 'limit' => $limit, 'offset' => $offset]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to fetch profiles'], 500);
    }
}

function getProfile($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT p.*, c.name as city_name, t.name as tariff_name, t.price as tariff_price
                FROM profiles p LEFT JOIN cities c ON p.city_id = c.id LEFT JOIN tariffs t ON p.tariff_id = t.id WHERE p.id = ?");
        $stmt->execute([$id]);
        $profile = $stmt->fetch();
        if (!$profile) { jsonResponse(['error' => 'Profile not found'], 404); }

        $stmt = $pdo->prepare("SELECT id, file_name, is_main, sort_order FROM profile_photos WHERE profile_id = ? ORDER BY sort_order ASC, is_main DESC");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['viewed_profiles'])) $_SESSION['viewed_profiles'] = [];
        if (!in_array($id, $_SESSION['viewed_profiles'])) {
            $stmt = $pdo->prepare("UPDATE profiles SET views = views + 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['viewed_profiles'][] = $id;
            logStat('profile_view', $id);
        }
        jsonResponse(['success' => true, 'data' => ['profile' => $profile, 'photos' => $photos]]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to fetch profile'], 500);
    }
}

function createProfile() {
    try {
        $pdo = getDB();
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) jsonResponse(['error' => 'Invalid input data'], 400);
        } else {
            $input = [
                'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
                'age' => isset($_POST['age']) ? (int)$_POST['age'] : '',
                'city_id' => isset($_POST['city_id']) ? (int)$_POST['city_id'] : '',
                'tariff_id' => isset($_POST['tariff_id']) && !empty($_POST['tariff_id']) ? (int)$_POST['tariff_id'] : null,
                'telegram' => isset($_POST['telegram']) ? trim($_POST['telegram']) : '',
                'whatsapp' => isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '',
                'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
                'status' => isset($_POST['status']) ? trim($_POST['status']) : 'pending',
                'is_vip' => isset($_POST['is_vip']) ? (int)$_POST['is_vip'] : 0
            ];
        }

        $required = ['name', 'age', 'city_id'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                jsonResponse(['error' => "Field '$field' is required"], 400);
            }
        }

        $name = sanitize($input['name']);
        $age = (int)$input['age'];
        $cityId = (int)$input['city_id'];
        $tariffId = isset($input['tariff_id']) && !empty($input['tariff_id']) ? (int)$input['tariff_id'] : null;
        $telegram = isset($input['telegram']) ? sanitize($input['telegram']) : null;
        $whatsapp = isset($input['whatsapp']) ? sanitize($input['whatsapp']) : null;
        $description = isset($input['description']) ? sanitize($input['description']) : null;
        $status = isset($input['status']) ? sanitize($input['status']) : 'pending';
        $isVip = isset($input['is_vip']) ? (int)$input['is_vip'] : 0;

        $stmt = $pdo->prepare("SELECT id FROM cities WHERE id = ?");
        $stmt->execute([$cityId]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'City not found'], 400);

        if ($tariffId) {
            $stmt = $pdo->prepare("SELECT id FROM tariffs WHERE id = ?");
            $stmt->execute([$tariffId]);
            if (!$stmt->fetch()) jsonResponse(['error' => 'Tariff not found'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO profiles (name, age, city_id, tariff_id, telegram, whatsapp, description, status, is_vip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $age, $cityId, $tariffId, $telegram, $whatsapp, $description, $status, $isVip]);
        $profileId = $pdo->lastInsertId();

        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            $uploadDir = UPLOAD_DIR;
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['photos']['error'][$index] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photos']['name'][$index], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;
                    $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $filePath = $uploadDir . $fileName;
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $isMain = ($index === 0) ? 1 : 0;
                        $stmt = $pdo->prepare("INSERT INTO profile_photos (profile_id, file_name, is_main, sort_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$profileId, $fileName, $isMain, $index]);
                    }
                }
            }
        }
        jsonResponse(['success' => true, 'message' => 'Profile created successfully', 'profile_id' => $profileId]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to create profile'], 500);
    }
}

function updateProfile($id) {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) jsonResponse(['error' => 'Invalid input data'], 400);

        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'Profile not found'], 404);

        $fields = []; $params = [];
        $allowedFields = ['name', 'age', 'city_id', 'tariff_id', 'telegram', 'whatsapp', 'description', 'status', 'is_vip', 'main_photo'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if (in_array($field, ['age', 'city_id', 'tariff_id', 'is_vip'])) {
                    $fields[] = "$field = ?"; $params[] = (int)$input[$field];
                } else {
                    $fields[] = "$field = ?"; $params[] = sanitize($input[$field]);
                }
            }
        }
        if (empty($fields)) jsonResponse(['error' => 'No fields to update'], 400);
        $params[] = $id;
        $sql = "UPDATE profiles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (isset($input['photos']) && is_array($input['photos'])) {
            $stmt = $pdo->prepare("SELECT file_name FROM profile_photos WHERE profile_id = ?");
            $stmt->execute([$id]);
            $oldPhotos = $stmt->fetchAll();
            foreach ($oldPhotos as $photo) {
                $filePath = UPLOAD_DIR . $photo['file_name'];
                if (file_exists($filePath)) @unlink($filePath);
            }
            $stmt = $pdo->prepare("DELETE FROM profile_photos WHERE profile_id = ?");
            $stmt->execute([$id]);
            foreach ($input['photos'] as $index => $photo) {
                $fileName = sanitize($photo['file_name']);
                $isMain = isset($photo['is_main']) ? (int)$photo['is_main'] : ($index === 0 ? 1 : 0);
                $sortOrder = isset($photo['sort_order']) ? (int)$photo['sort_order'] : $index;
                $stmt = $pdo->prepare("INSERT INTO profile_photos (profile_id, file_name, is_main, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id, $fileName, $isMain, $sortOrder]);
            }
        }
        jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to update profile'], 500);
    }
}

function deleteProfile($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) jsonResponse(['error' => 'Profile not found'], 404);

        $stmt = $pdo->prepare("SELECT file_name FROM profile_photos WHERE profile_id = ?");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();
        foreach ($photos as $photo) {
            $filePath = UPLOAD_DIR . $photo['file_name'];
            if (file_exists($filePath)) @unlink($filePath);
        }
        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Profile deleted successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to delete profile'], 500);
    }
}