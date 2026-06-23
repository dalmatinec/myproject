<?php
// admin.php - Административный управляющий файл

require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Публичные методы (не требуют авторизации)
        if ($action === 'cities' || $action === 'tariffs') {
            if ($action === 'cities') {
                getCities();
            } elseif ($action === 'tariffs') {
                getTariffs();
            }
        } else {
            // Все остальные GET-запросы требуют авторизации
            requireAdmin();
            if ($action === 'dashboard') {
                getDashboard();
            } else {
                jsonResponse(['error' => 'Invalid action'], 400);
            }
        }
        break;

    case 'POST':
        requireAdmin();
        if ($action === 'cities') {
            addCity();
        } elseif ($action === 'tariffs') {
            addTariff();
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;

    case 'DELETE':
        requireAdmin();
        if ($action === 'city' && isset($_GET['id'])) {
            deleteCity($_GET['id']);
        } elseif ($action === 'tariff' && isset($_GET['id'])) {
            deleteTariff($_GET['id']);
        } else {
            jsonResponse(['error' => 'Invalid action or missing ID'], 400);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// ===== ДАШБОРД =====
function getDashboard() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'active'");
        $activeProfiles = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'pending'");
        $pendingProfiles = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT SUM(views) as total FROM profiles");
        $totalViews = $stmt->fetch()['total'] ?? 0;
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM news");
        $totalNews = $stmt->fetch()['count'];

        $stmt = $pdo->query("SELECT currency, SUM(amount) as total FROM payments WHERE status = 'confirmed' GROUP BY currency");
        $financeByCurrency = [];
        while ($row = $stmt->fetch()) {
            $financeByCurrency[$row['currency']] = (float)$row['total'];
        }
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
        $pendingPayments = $stmt->fetch()['count'];

        $stmt = $pdo->query("SELECT id, name, status, created_at FROM profiles ORDER BY created_at DESC LIMIT 5");
        $recentProfiles = $stmt->fetchAll();
        $stmt = $pdo->query("SELECT id, profile_id, currency, amount, status, created_at FROM payments ORDER BY created_at DESC LIMIT 5");
        $recentPayments = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'data' => [
                'totals' => [
                    'active_profiles' => (int)$activeProfiles,
                    'pending_profiles' => (int)$pendingProfiles,
                    'total_views' => (int)$totalViews,
                    'total_news' => (int)$totalNews
                ],
                'finance' => [
                    'confirmed_by_currency' => $financeByCurrency,
                    'pending_payments' => (int)$pendingPayments
                ],
                'recent_activity' => [
                    'recent_profiles' => $recentProfiles,
                    'recent_payments' => $recentPayments
                ]
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// ===== ГОРОДА =====
function getCities() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT id, name FROM cities ORDER BY name ASC");
        $cities = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $cities]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to load cities'], 500);
    }
}

function addCity() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name']) || empty(trim($input['name']))) {
        jsonResponse(['error' => 'City name is required'], 400);
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO cities (name) VALUES (?)");
        $stmt->execute([trim($input['name'])]);
        jsonResponse(['success' => true, 'message' => 'City added']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to add city'], 500);
    }
}

function deleteCity($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM cities WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'City deleted']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to delete city'], 500);
    }
}

// ===== ТАРИФЫ =====
function getTariffs() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT id, name, price FROM tariffs ORDER BY price ASC");
        $tariffs = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $tariffs]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to load tariffs'], 500);
    }
}

function addTariff() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name']) || empty(trim($input['name']))) {
        jsonResponse(['error' => 'Tariff name is required'], 400);
    }
    if (!isset($input['price']) || !is_numeric($input['price']) || $input['price'] < 0) {
        jsonResponse(['error' => 'Valid price is required'], 400);
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO tariffs (name, price) VALUES (?, ?)");
        $stmt->execute([trim($input['name']), (float)$input['price']]);
        jsonResponse(['success' => true, 'message' => 'Tariff added']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to add tariff'], 500);
    }
}

function deleteTariff($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM tariffs WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Tariff deleted']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to delete tariff'], 500);
    }
}