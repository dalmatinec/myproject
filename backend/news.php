<?php
// news.php - Обработчик новостей

require_once 'config.php';

// Получение действия
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($action === 'get' && isset($_GET['id'])) {
            getNews($_GET['id']);
        } else {
            getNewsList();
        }
        break;
    
    case 'POST':
        requireAdmin();
        createNews();
        break;
    
    case 'PUT':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'News ID required'], 400);
        }
        updateNews($_GET['id']);
        break;
    
    case 'DELETE':
        requireAdmin();
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'News ID required'], 400);
        }
        deleteNews($_GET['id']);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// Получение списка новостей (публичный)
function getNewsList() {
    try {
        $pdo = getDB();
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $status = isset($_GET['status']) ? sanitize($_GET['status']) : 'active';
        
        // Базовый запрос
        $sql = "SELECT id, title, content, image, views, created_at 
                FROM news 
                WHERE 1=1";
        
        $params = [];
        
        // Фильтр по статусу (для админов показываем все)
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        // Сортировка
        $sql .= " ORDER BY created_at DESC";
        
        // Пагинация
        $sql .= " LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        
        // Привязка параметров фильтров
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }
        
        // Привязка LIMIT и OFFSET
        $offsetIndex = count($params) + 1;
        $limitIndex = count($params) + 2;
        
        $stmt->bindValue($offsetIndex, $offset, PDO::PARAM_INT);
        $stmt->bindValue($limitIndex, $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $news = $stmt->fetchAll();
        
        // Получение общего количества
        $countSql = "SELECT COUNT(*) as total FROM news WHERE 1=1";
        $countParams = [];
        
        if ($status) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse([
            'success' => true,
            'data' => $news,
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
            jsonResponse(['error' => 'Failed to fetch news'], 500);
        }
    }
}

// Получение одной новости (публичный)
function getNews($id) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT id, title, content, image, views, created_at, status FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news) {
            jsonResponse(['error' => 'News not found'], 404);
        }
        
        // Проверка накрутки просмотров через сессию
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['viewed_news'])) {
            $_SESSION['viewed_news'] = [];
        }
        
        // Если новость еще не просматривалась в этой сессии
        if (!in_array($id, $_SESSION['viewed_news'])) {
            // Увеличение счетчика просмотров
            $stmt = $pdo->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Запись ID в сессию
            $_SESSION['viewed_news'][] = $id;
            
            // Запись статистики
            logStat('news_view', $id);
        }
        
        jsonResponse([
            'success' => true,
            'data' => $news
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to fetch news'], 500);
        }
    }
}

// Создание новости (только админ)
function createNews() {
    try {
        $pdo = getDB();
        
        // Получение данных
        $title = isset($_POST['title']) ? sanitize($_POST['title']) : null;
        $content = isset($_POST['content']) ? sanitize($_POST['content']) : null;
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'active';
        
        // Валидация обязательных полей
        if (!$title || !$content) {
            jsonResponse(['error' => 'Title and content are required'], 400);
        }
        
        // Проверка статуса
        $allowedStatuses = ['active', 'draft'];
        if (!in_array($status, $allowedStatuses)) {
            jsonResponse(['error' => 'Invalid status. Allowed: active, draft'], 400);
        }
        
        // Обработка изображения
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileName = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Проверка расширения
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                jsonResponse(['error' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp'], 400);
            }
            
            // Проверка размера
            if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
                jsonResponse(['error' => 'File too large. Max size: 5MB'], 400);
            }
            
            // Сохранение файла
            $image = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadPath = UPLOAD_DIR . $image;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                jsonResponse(['error' => 'Failed to upload image'], 500);
            }
        }
        
        // Создание новости
        $stmt = $pdo->prepare("
            INSERT INTO news (title, content, image, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$title, $content, $image, $status]);
        $newsId = $pdo->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'News created successfully',
            'news_id' => $newsId
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to create news'], 500);
        }
    }
}

// Обновление новости (только админ)
function updateNews($id) {
    try {
        $pdo = getDB();
        
        // Проверка существования новости
        $stmt = $pdo->prepare("SELECT id, image FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news) {
            jsonResponse(['error' => 'News not found'], 404);
        }
        
        // Получение данных (JSON или FormData)
        $isFormData = isset($_FILES['image']) || isset($_POST['title']);
        
        if ($isFormData) {
            // Обработка FormData
            $title = isset($_POST['title']) ? sanitize($_POST['title']) : null;
            $content = isset($_POST['content']) ? sanitize($_POST['content']) : null;
            $status = isset($_POST['status']) ? sanitize($_POST['status']) : null;
            
            $fields = [];
            $params = [];
            
            if ($title) {
                $fields[] = "title = ?";
                $params[] = $title;
            }
            
            if ($content) {
                $fields[] = "content = ?";
                $params[] = $content;
            }
            
            if ($status) {
                $allowedStatuses = ['active', 'draft'];
                if (!in_array($status, $allowedStatuses)) {
                    jsonResponse(['error' => 'Invalid status. Allowed: active, draft'], 400);
                }
                $fields[] = "status = ?";
                $params[] = $status;
            }
            
            // Обработка изображения
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Проверка расширения
                if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                    jsonResponse(['error' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp'], 400);
                }
                
                // Проверка размера
                if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
                    jsonResponse(['error' => 'File too large. Max size: 5MB'], 400);
                }
                
                // Удаление старого изображения
                if ($news['image']) {
                    $oldPath = UPLOAD_DIR . $news['image'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                
                // Сохранение нового изображения
                $image = bin2hex(random_bytes(16)) . '.' . $ext;
                $uploadPath = UPLOAD_DIR . $image;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    jsonResponse(['error' => 'Failed to upload image'], 500);
                }
                
                $fields[] = "image = ?";
                $params[] = $image;
            }
            
            if (empty($fields)) {
                jsonResponse(['error' => 'No fields to update'], 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE news SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
        } else {
            // Обработка JSON
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                jsonResponse(['error' => 'Invalid input data'], 400);
            }
            
            $fields = [];
            $params = [];
            
            $allowedFields = ['title', 'content', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'status') {
                        $allowedStatuses = ['active', 'draft'];
                        if (!in_array($input[$field], $allowedStatuses)) {
                            jsonResponse(['error' => 'Invalid status. Allowed: active, draft'], 400);
                        }
                    }
                    $fields[] = "$field = ?";
                    $params[] = sanitize($input[$field]);
                }
            }
            
            if (empty($fields)) {
                jsonResponse(['error' => 'No fields to update'], 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE news SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'News updated successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to update news'], 500);
        }
    }
}

// Удаление новости (только админ)
function deleteNews($id) {
    try {
        $pdo = getDB();
        
        // Проверка существования новости
        $stmt = $pdo->prepare("SELECT id, image FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news) {
            jsonResponse(['error' => 'News not found'], 404);
        }
        
        // Удаление изображения
        if ($news['image']) {
            $filePath = UPLOAD_DIR . $news['image'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Удаление новости
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'News deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to delete news'], 500);
        }
    }
}