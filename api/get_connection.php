<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

try {
    if ($userRole === 'admin') {
        $count = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT 1 FROM quizzes WHERE status = 'draft'
                UNION ALL
                SELECT 1 FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) as notifications
        ")->fetchColumn();
    } elseif ($userRole === 'teacher') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT a.id FROM attempts a
                JOIN quizzes q ON a.quiz_id = q.id
                WHERE q.creator_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                UNION ALL
                SELECT id FROM quizzes 
                WHERE creator_id = ? AND status = 'draft'
            ) as notifications
        ");
        $stmt->execute([$userId, $userId]);
        $count = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT a.id FROM attempts a
                WHERE a.user_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                UNION ALL
                SELECT q.id FROM quizzes q
                WHERE q.status = 'published' AND q.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) as notifications
        ");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();
    }
    
    echo json_encode(['count' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}
?>