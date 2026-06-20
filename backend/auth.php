<?php
// =============================================
// WeToo - Authentication API
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
$path = str_replace('/api/auth', '', $path);
if (empty($path)) {
    $path = '/';
}

// =============================================
// Роутер
// =============================================
switch ($path) {
    case '/login':
        if ($method === 'POST') {
            handleLogin($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/verify':
        if ($method === 'GET') {
            handleVerify($pdo);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    case '/logout':
        if ($method === 'POST') {
            handleLogout();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}

// =============================================
// Обработчики
// =============================================

/**
 * Вход администратора
 * POST /api/auth/login
 * Body: { username, password }
 */
function handleLogin($pdo) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Защита от битого JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }
    
    if (!$data || !isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    // Проверяем администратора
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    if (!$admin['is_active']) {
        http_response_code(403);
        echo json_encode(['error' => 'Account is disabled']);
        return;
    }
    
    if (!password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    // Обновляем время последнего входа
    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW(), last_ip = ? WHERE id = ?");
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $admin['id']]);
    
    // Создаем JWT
    $token = createJWT($admin['id'], $admin['username'], $admin['role']);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);
}

/**
 * Проверка JWT токена
 * GET /api/auth/verify
 * Header: Authorization: Bearer <token>
 */
function handleVerify($pdo) {
    $payload = requireAdmin($pdo);
    
    if (!$payload) {
        return; // requireAdmin уже отправил 401
    }
    
    // Получаем свежие данные админа
    $stmt = $pdo->prepare("SELECT id, username, role, is_active FROM admins WHERE id = ?");
    $stmt->execute([$payload['user_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin || !$admin['is_active']) {
        http_response_code(401);
        echo json_encode(['error' => 'Account is disabled or not found']);
        return;
    }
    
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);
}

/**
 * Выход (клиент просто удаляет токен)
 * POST /api/auth/logout
 */
function handleLogout() {
    echo json_encode(['success' => true]);
}