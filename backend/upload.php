// =============================================
// 1. ЗАГРУЗКА ФОТО АНКЕТЫ (С INSERT В БД)
// =============================================
if (isset($_POST['type']) && $_POST['type'] === 'profile_photo') {
    $profileId = (int)($_POST['profile_id'] ?? 0);
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isMain = (int)($_POST['is_main'] ?? 0);
    
    // Проверяем существование анкеты
    if ($profileId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Profile ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found']);
        exit;
    }
    
    // Проверяем лимит фотографий
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profile_photos WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $count = $stmt->fetch()['count'];
    
    if ($count >= MAX_PHOTOS) {
        http_response_code(400);
        echo json_encode(['error' => 'Maximum ' . MAX_PHOTOS . ' photos per profile']);
        exit;
    }
    
    // Проверяем файл
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload failed']);
        exit;
    }
    
    $file = $_FILES['photo'];
    $validation = validateImage($file);
    if (isset($validation['error'])) {
        http_response_code(400);
        echo json_encode(['error' => $validation['error']]);
        exit;
    }
    
    createDirectories();
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = generateSafeFilename('profile_' . $profileId, $ext);
    $path = IMAGES_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $path)) {
        // ✅ ДОБАВЛЯЕМ INSERT В БД
        $stmt = $pdo->prepare("
            INSERT INTO profile_photos (profile_id, filename, sort_order, is_main) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$profileId, $filename, $sortOrder, $isMain]);
        
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'url' => '/images/' . $filename,
            'id' => $pdo->lastInsertId(),
            'width' => $validation['width'],
            'height' => $validation['height']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
    }
    exit;
}