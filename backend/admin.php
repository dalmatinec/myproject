<?php
// admin.php - Административный управляющий файл

require_once 'config.php';

// Получение действия
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($action === 'dashboard') {
            requireAdmin();
            getDashboard();
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// Получение данных для дашборда
function getDashboard() {
    try {
        $pdo = getDB();
        
        // ===== БЛОК СЧЕТЧИКОВ =====
        
        // Всего активных анкет
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'active'");
        $activeProfiles = $stmt->fetch()['count'];
        
        // Анкет на модерации
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE status = 'pending'");
        $pendingProfiles = $stmt->fetch()['count'];
        
        // Общее количество просмотров всех анкет
        $stmt = $pdo->query("SELECT SUM(views) as total FROM profiles");
        $totalViews = $stmt->fetch()['total'] ?? 0;
        
        // Всего новостей
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM news");
        $totalNews = $stmt->fetch()['count'];
        
        $totals = [
            'active_profiles' => (int)$activeProfiles,
            'pending_profiles' => (int)$pendingProfiles,
            'total_views' => (int)$totalViews,
            'total_news' => (int)$totalNews
        ];
        
        // ===== БЛОК ФИНАНСОВ =====
        
        // Сумма подтвержденных платежей по валютам
        $stmt = $pdo->query("
            SELECT currency, SUM(amount) as total 
            FROM payments 
            WHERE status = 'confirmed' 
            GROUP BY currency
        ");
        $financeByCurrency = [];
        while ($row = $stmt->fetch()) {
            $financeByCurrency[$row['currency']] = (float)$row['total'];
        }
        
        // Количество платежей, ожидающих проверки
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
        $pendingPayments = $stmt->fetch()['count'];
        
        $finance = [
            'confirmed_by_currency' => $financeByCurrency,
            'pending_payments' => (int)$pendingPayments
        ];
        
        // ===== БЛОК ПОСЛЕДНИХ СОБЫТИЙ =====
        
        // Последние 5 анкет
        $stmt = $pdo->query("
            SELECT id, name, status, created_at 
            FROM profiles 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recentProfiles = $stmt->fetchAll();
        
        // Последние 5 платежей
        $stmt = $pdo->query("
            SELECT id, profile_id, currency, amount, status, created_at 
            FROM payments 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recentPayments = $stmt->fetchAll();
        
        $recentActivity = [
            'recent_profiles' => $recentProfiles,
            'recent_payments' => $recentPayments
        ];
        
        // ===== ФОРМИРОВАНИЕ ОТВЕТА =====
        jsonResponse([
            'success' => true,
            'data' => [
                'totals' => $totals,
                'finance' => $finance,
                'recent_activity' => $recentActivity
            ]
        ]);
        
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } else {
            jsonResponse(['error' => 'Failed to fetch dashboard data'], 500);
        }
    }
}