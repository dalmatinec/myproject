
<?php
// auth.php - Авторизация администратора

require_once 'config.php';

// Получение действия
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    case 'check':
        handleCheck();
        break;
    
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

// Обработчик входа
function handleLogin() {
    // Получение данных
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        jsonResponse(['error' => 'Username and password required'], 400);
    }
    
    $username = sanitize($input['username']);
    $password = $input['password'];
    
    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username and password cannot be empty'], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Поиск администратора
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Проверка пароля
        if (!password_verify($password, $admin['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Создание сессии
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Возврат успешного ответа
        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'role' => $admin['role']
            ]
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Login failed. Please try again later.'], 500);
        }
    }
}

// Обработчик выхода
function handleLogout() {
    // Уничтожение сессии
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

// Обработчик проверки сессии
function handleCheck() {
    if (isAdmin()) {
        jsonResponse([
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'role' => $_SESSION['admin_role']
            ]
        ]);
    } else {
        jsonResponse([
            'authenticated' => false
        ], 401);
    }
}