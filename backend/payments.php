// Сохраняем файл
$filename = 'payment_' . $profile_id . '_' . time() . '.' . $ext;
$path = IMAGES_DIR . $filename;

if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save screenshot']);
    return;
}

// Создаем платеж
try {
    $stmt = $pdo->prepare("
        INSERT INTO payments (profile_id, plan_id, currency, amount, wallet_address, screenshot, txid, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting')
    ");
    $stmt->execute([...]);
    $paymentId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $paymentId,
        'message' => 'Payment created, waiting for confirmation'
    ]);
    
} catch (Exception $e) {
    // Удаляем файл при ошибке
    if (file_exists($path)) {
        unlink($path);
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}