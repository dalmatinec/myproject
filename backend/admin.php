<?php
// =============================================
// WeToo - Admin API (универсальный) - ЧАСТЬ 1
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
$path = str_replace('/api/admin', '', $path);
if (empty($path)) {
    $path = '/';
}

// =============================================
// Роутер
// =============================================
switch ($path) {
    // Статистика
    case '/stats':
        if ($method === 'GET') {
            handleStats($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    // Города
    case '/cities':
        if ($method === 'GET') {
            handleGetCities($pdo);
        } elseif ($method === 'POST') {
            handleCreateCity($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    // Тарифы
    case '/plans':
        if ($method === 'GET') {
            handleGetPlans($pdo);
        } elseif ($method === 'POST') {
            handleCreatePlan($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    // Настройки
    case '/settings':
        if ($method === 'GET') {
            handleGetSettings($pdo);
        } elseif ($method === 'PUT') {
            handleUpdateSettings($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    // Администраторы
    case '/admins':
        if ($method === 'GET') {
            handleGetAdmins($pdo);
        } elseif ($method === 'POST') {
            handleCreateAdmin($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    // Жалобы
    case '/complaints':
        if ($method === 'GET') {
            handleGetComplaints($pdo);
        } elseif ($method === 'POST') {
            handleCreateComplaint($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    default:
        // /cities/{id}, /plans/{id}, /admins/{id}, /complaints/{id}
        if (preg_match('/^\/(cities|plans|admins|complaints)\/(\d+)$/', $path, $matches)) {
            $entity = $matches[1];
            $id = (int)$matches[2];
            
            if ($method === 'PUT') {
                if ($entity === 'cities') handleUpdateCity($pdo, $id);
                elseif ($entity === 'plans') handleUpdatePlan($pdo, $id);
                elseif ($entity === 'admins') handleUpdateAdmin($pdo, $id);
                elseif ($entity === 'complaints') handleUpdateComplaint($pdo, $id);
            } elseif ($method === 'DELETE') {
                if ($entity === 'cities') handleDeleteCity($pdo, $id);
                elseif ($entity === 'plans') handleDeletePlan($pdo, $id);
                elseif ($entity === 'admins') handleDeleteAdmin($pdo, $id);
                elseif ($entity === 'complaints') handleDeleteComplaint($pdo, $id);
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
// СТАТИСТИКА
// =============================================

function handleStats($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles");
    $stats['total_profiles'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'active'");
    $stats['active_profiles'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'pending'");
    $stats['pending_profiles'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM profiles p 
        LEFT JOIN plans pl ON p.plan_id = pl.id 
        WHERE p.status = 'active' AND pl.name IN ('VIP', 'ТОП') AND p.expires_at > NOW()
    ");
    $stats['vip_profiles'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT SUM(views_count) as total FROM profiles");
    $stats['total_views'] = (int)$stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments");
    $stats['total_payments'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'waiting'");
    $stats['pending_payments'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints");
    $stats['total_complaints'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'new'");
    $stats['new_complaints'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cities WHERE is_active = 1");
    $stats['active_cities'] = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins WHERE is_active = 1");
    $stats['active_admins'] = (int)$stmt->fetch()['count'];
    
    echo json_encode($stats);
}

// =============================================
// ГОРОДА (CRUD)
// =============================================

function handleGetCities($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->query("SELECT id, name, sort_order, is_active, created_at FROM cities ORDER BY sort_order, name");
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCreateCity($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'City name required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cities (name, sort_order, is_active) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateCity($pdo, $id) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $fields = [];
    $params = [];
    
    $allowed = ['name', 'sort_order', 'is_active'];
    foreach ($allowed as $field) {
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
    
    try {
        $params[] = $id;
        $sql = "UPDATE cities SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteCity($pdo, $id) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profiles WHERE city_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete city with profiles']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM cities WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// =============================================
// ТАРИФЫ (CRUD)
// =============================================

function handleGetPlans($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->query("SELECT id, name, price_btc, price_eth, price_usdt, duration_days, sort_order, is_active, created_at FROM plans ORDER BY sort_order");
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCreatePlan($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Plan name required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO plans (name, price_btc, price_eth, price_usdt, duration_days, sort_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['price_btc'] ?? 0,
            $data['price_eth'] ?? 0,
            $data['price_usdt'] ?? 0,
            $data['duration_days'] ?? 30,
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdatePlan($pdo, $id) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $fields = [];
    $params = [];
    
    $allowed = ['name', 'price_btc', 'price_eth', 'price_usdt', 'duration_days', 'sort_order', 'is_active'];
    foreach ($allowed as $field) {
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
    
    try {
        $params[] = $id;
        $sql = "UPDATE plans SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeletePlan($pdo, $id) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profiles WHERE plan_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete plan with active profiles']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// =============================================
// НАСТРОЙКИ
// =============================================

function handleGetSettings($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    try {
        $settings = getSettings($pdo);
        echo json_encode($settings);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateSettings($pdo) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $allowedKeys = [
            'site_title', 'site_description', 'site_url',
            'telegram_bot_token', 'telegram_channel_username',
            'telegram_support_username', 'telegram_exchange_username',
            'btc_wallet', 'eth_wallet', 'usdt_trc20_wallet'
        ];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $stmt->execute([$key, $value]);
            }
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// =============================================
// АДМИНИСТРАТОРЫ (CRUD)
// =============================================

function handleGetAdmins($pdo) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->query("SELECT id, username, role, last_ip, last_login, is_active, created_at FROM admins ORDER BY id");
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCreateAdmin($pdo) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    if (strlen($data['password']) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        return;
    }
    
    try {
        // Проверяем уникальность username
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
            return;
        }
        
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['username'],
            $passwordHash,
            $data['role'] ?? 'moderator',
            $data['is_active'] ?? 1
        ]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateAdmin($pdo, $id) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    if ($id == $payload['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot edit your own account here']);
        return;
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    $fields = [];
    $params = [];
    
    if (isset($data['username'])) {
        // Проверяем уникальность нового username
        try {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->execute([$data['username'], $id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Username already exists']);
                return;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            return;
        }
        $fields[] = "username = ?";
        $params[] = $data['username'];
    }
    
    if (isset($data['role'])) {
        $fields[] = "role = ?";
        $params[] = $data['role'];
    }
    if (isset($data['is_active'])) {
        $fields[] = "is_active = ?";
        $params[] = $data['is_active'];
    }
    if (!empty($data['password'])) {
        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters']);
            return;
        }
        $fields[] = "password_hash = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    try {
        $params[] = $id;
        $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteAdmin($pdo, $id) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    if ($id == $payload['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete your own account']);
        return;
    }
    
    try {
        // Проверяем, что это не последний super_admin
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE role = 'superadmin' AND is_active = 1");
        $stmt->execute();
        $superAdminCount = (int)$stmt->fetch()['count'];
        
        if ($superAdminCount <= 1) {
            // Проверяем, удаляем ли мы последнего суперадмина
            $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ? AND role = 'superadmin' AND is_active = 1");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete the last active super administrator']);
                return;
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// =============================================
// ЖАЛОБЫ (CRUD)
// =============================================

function handleGetComplaints($pdo) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $params = $_GET;
    $status = $params['status'] ?? '';
    $where = [];
    $queryParams = [];
    
    if (!empty($status) && in_array($status, ['new', 'reviewed', 'resolved'])) {
        $where[] = "c.status = ?";
        $queryParams[] = $status;
    }
    
    $whereSQL = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    
    try {
        $sql = "SELECT c.*, p.name as profile_name, p.slug as profile_slug, a.username as resolved_by_name
                FROM complaints c
                LEFT JOIN profiles p ON c.profile_id = p.id
                LEFT JOIN admins a ON c.resolved_by = a.id
                $whereSQL
                ORDER BY c.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
        $complaints = $stmt->fetchAll();
        
        echo json_encode($complaints);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCreateComplaint($pdo) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (empty($data['profile_id']) || empty($data['complaint_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Profile ID and complaint text required']);
        return;
    }
    
    if (strlen($data['complaint_text']) > 5000) {
        http_response_code(400);
        echo json_encode(['error' => 'Complaint text must not exceed 5000 characters']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$data['profile_id']]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Profile not found']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO complaints (profile_id, complaint_text, contact) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['profile_id'],
            $data['complaint_text'],
            $data['contact'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateComplaint($pdo, $id) {
    $payload = requireAdmin($pdo);
    if (!$payload) return;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!isset($data['status']) || !in_array($data['status'], ['new', 'reviewed', 'resolved'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    try {
        $fields = ["status = ?"];
        $params = [$data['status']];
        
        if (isset($data['moderator_comment'])) {
            $fields[] = "moderator_comment = ?";
            $params[] = $data['moderator_comment'];
        }
        
        // resolved_at и resolved_by заполняем только если status = 'resolved'
        if ($data['status'] === 'resolved') {
            $fields[] = "resolved_by = ?";
            $params[] = $payload['user_id'];
            $fields[] = "resolved_at = NOW()";
        } else {
            // Если статус не resolved — обнуляем поля
            $fields[] = "resolved_by = NULL";
            $fields[] = "resolved_at = NULL";
        }
        
        $params[] = $id;
        $sql = "UPDATE complaints SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteComplaint($pdo, $id) {
    $payload = requireSuperAdmin($pdo);
    if (!$payload) return;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}