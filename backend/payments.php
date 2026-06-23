<?php
// payments.php - Обработчик платежей

require_once 'config.php';

// Получение действия
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($action === 'get' && isset($_GET['id'])) {
            requireAdmin();
            getPayment($_GET['id']);
        } else {
            requireAdmin();
            getPayments();
        }
        break;
    
    case 'POST':
        // Создание платежа (публичное)
        createPayment();
        break;
    
    case 'PUT':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Payment ID required'], 400);
        }
        updatePayment($_GET['id']);
        break;
    
    case 'DELETE':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Payment ID required'], 400);
        }
        deletePayment($_GET['id']);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// Получение списка платежей (только админ)
function getPayments() {
    try {
        $pdo = getDB();
        
        // Параметры фильтрации
        $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
        $profileId = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        // Базовый запрос
        $sql = "SELECT 
                    p.*,
                    pr.name as profile_name
                FROM payments p
                LEFT JOIN profiles pr ON p.profile_id = pr.id
                WHERE 1=1";
        
        $params = [];
        
        // Фильтр по статусу
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        // Фильтр по профилю
        if ($profileId) {
            $sql .= " AND p.profile_id = ?";
            $params[] = $profileId;
        }
        
        // Сортировка
        $sql .= " ORDER BY p.created_at DESC";
        
        // Пагинация (используем только анонимные параметры)
        $sql .= " LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        
        // Привязка параметров фильтров (анонимные)
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }
        
        // Привязка LIMIT и OFFSET (анонимные, как целые числа)
        $offsetIndex = count($params) + 1;
        $limitIndex = count($params) + 2;
        
        $stmt->bindValue($offsetIndex, $offset, PDO::PARAM_INT);
        $stmt->bindValue($limitIndex, $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $payments = $stmt->fetchAll();
        
        // Получение общего количества
        $countSql = "SELECT COUNT(*) as total FROM payments p WHERE 1=1";
        $countParams = [];
        
        if ($status) {
            $countSql .= " AND p.status = ?";
            $countParams[] = $status;
        }
        if ($profileId) {
            $countSql .= " AND p.profile_id = ?";
            $countParams[] = $profileId;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse([
            'success' => true,
            'data' => $payments,
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
            jsonResponse(['error' => 'Failed to fetch payments'], 500);
        }
    }
}

// Получение одного платежа (только админ)
function getPayment($id) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                pr.name as profile_name
            FROM payments p
            LEFT JOIN profiles pr ON p.profile_id = pr.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            jsonResponse(['error' => 'Payment not found'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'data' => $payment
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to fetch payment'], 500);
        }
    }
}

// Создание платежа (публичное)
function createPayment() {
    try {
        $pdo = getDB();
        
        // Проверка наличия файла
        if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Screenshot is required'], 400);
        }
        
        // Получение расширения файла
        $fileName = $_FILES['screenshot']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Проверка расширения файла
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            jsonResponse(['error' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp'], 400);
        }
        
        // Проверка размера файла
        if ($_FILES['screenshot']['size'] > MAX_FILE_SIZE) {
            jsonResponse(['error' => 'File too large. Max size: 5MB'], 400);
        }
        
        // Получение данных
        $profileId = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : null;
        $currency = isset($_POST['currency']) ? sanitize($_POST['currency']) : null;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
        $walletAddress = isset($_POST['wallet_address']) ? sanitize($_POST['wallet_address']) : null;
        $txid = isset($_POST['txid']) && !empty($_POST['txid']) ? sanitize($_POST['txid']) : null;
        
        // Валидация обязательных полей
        if (!$profileId || !$currency || !$amount || !$walletAddress) {
            jsonResponse(['error' => 'profile_id, currency, amount and wallet_address are required'], 400);
        }
        
        // Проверка валюты
        $allowedCurrencies = ['BTC', 'ETH', 'USDT'];
        if (!in_array($currency, $allowedCurrencies)) {
            jsonResponse(['error' => 'Invalid currency. Allowed: BTC, ETH, USDT'], 400);
        }
        
        // Проверка существования профиля
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
        $stmt->execute([$profileId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Profile not found'], 400);
        }
        
        // Сохранение скриншота
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadPath = UPLOAD_DIR . $safeFilename;
        
        if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadPath)) {
            jsonResponse(['error' => 'Failed to upload screenshot'], 500);
        }
        
        // Создание платежа
        $stmt = $pdo->prepare("
            INSERT INTO payments (profile_id, currency, amount, wallet_address, txid, screenshot, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$profileId, $currency, $amount, $walletAddress, $txid, $safeFilename]);
        $paymentId = $pdo->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Payment created successfully',
            'payment_id' => $paymentId
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to create payment'], 500);
        }
    }
}

// Обновление статуса платежа (только админ)
function updatePayment($id) {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(['error' => 'Invalid input data'], 400);
        }
        
        // Проверка существования платежа
        $stmt = $pdo->prepare("SELECT id, screenshot FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            jsonResponse(['error' => 'Payment not found'], 404);
        }
        
        // Подготовка данных для обновления
        $fields = [];
        $params = [];
        
        // Обновление статуса
        if (isset($input['status'])) {
            $status = sanitize($input['status']);
            $allowedStatuses = ['pending', 'confirmed', 'rejected'];
            if (!in_array($status, $allowedStatuses)) {
                jsonResponse(['error' => 'Invalid status. Allowed: pending, confirmed, rejected'], 400);
            }
            $fields[] = "status = ?";
            $params[] = $status;
            
            // Если статус confirmed, обновляем confirmed_at
            if ($status === 'confirmed') {
                $fields[] = "confirmed_at = NOW()";
            }
        }
        
        // Обновление комментария администратора
        if (isset($input['admin_comment'])) {
            $fields[] = "admin_comment = ?";
            $params[] = sanitize($input['admin_comment']);
        }
        
        // Обновление TXID
        if (isset($input['txid'])) {
            $fields[] = "txid = ?";
            $params[] = sanitize($input['txid']);
        }
        
        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true,
            'message' => 'Payment updated successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to update payment'], 500);
        }
    }
}

// Удаление платежа (только админ)
function deletePayment($id) {
    try {
        $pdo = getDB();
        
        // Проверка существования платежа и получение имени файла
        $stmt = $pdo->prepare("SELECT id, screenshot FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            jsonResponse(['error' => 'Payment not found'], 404);
        }
        
        // Удаление файла скриншота
        if ($payment['screenshot']) {
            $filePath = UPLOAD_DIR . $payment['screenshot'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Удаление записи
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to delete payment'], 500);
        }
    }
}