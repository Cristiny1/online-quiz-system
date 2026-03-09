<?php
declare(strict_types=1);

// Error handling
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Secure session
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /online_quiz_system/login.php');
    exit();
}

// CSRF validation
if (
    !isset($_GET['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])
) {
    $_SESSION['error'] = 'Invalid security token.';
    header('Location: all-quiz.php');
    exit();
}

// FIX: Single database include — was called twice; second require_once returns (int)1, not $pdo
require_once __DIR__ . '/../../../config/database.php';

// Validate quiz ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = 'Invalid quiz ID.';
    header('Location: all-quiz.php');
    exit();
}

$quizId   = (int) $_GET['id'];
$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Check quiz exists and verify ownership
    if ($userRole === 'admin') {
        $stmt = $pdo->prepare('SELECT id, title, creator_id FROM quizzes WHERE id = :id');
        $stmt->execute(['id' => $quizId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, title FROM quizzes WHERE id = :id AND creator_id = :creator_id');
        $stmt->execute(['id' => $quizId, 'creator_id' => $userId]);
    }

    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Quiz not found or you do not have permission to delete it.';
        header('Location: all-quiz.php');
        exit();
    }

    // Log before deletion
    error_log("Quiz deletion initiated — ID: $quizId, Title: {$quiz['title']}, User: $userId, Role: $userRole");

    // Count existing attempts for logging
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE quiz_id = :quiz_id');
    $stmt->execute(['quiz_id' => $quizId]);
    $attemptCount = (int) $stmt->fetchColumn();

    if ($attemptCount > 0) {
        error_log("Deleting quiz with $attemptCount existing attempts");
    }

    // Delete in correct FK order — options → questions → attempts → quiz
    // FIX: Each step binds only the params it actually uses (no stray :id in first 3)
    $steps = [
        'DELETE FROM options WHERE question_id IN (SELECT id FROM questions WHERE quiz_id = :quiz_id)',
        'DELETE FROM questions WHERE quiz_id = :quiz_id',
        'DELETE FROM attempts  WHERE quiz_id = :quiz_id',
        'DELETE FROM quizzes   WHERE id = :id',
    ];

    foreach ($steps as $sql) {
        $stmt = $pdo->prepare($sql);
        // Bind only the params present in this query
        if (str_contains($sql, ':quiz_id')) {
            $stmt->bindValue(':quiz_id', $quizId, PDO::PARAM_INT);
        }
        if (str_contains($sql, ':id')) {
            $stmt->bindValue(':id', $quizId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    $pdo->commit();

    error_log("Quiz deleted successfully — ID: $quizId, Title: {$quiz['title']}, Attempts deleted: $attemptCount");
    $_SESSION['success'] = 'Quiz "' . htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') . '" deleted successfully.';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error deleting quiz: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    $_SESSION['error'] = 'Failed to delete quiz. Please try again.';
}

header('Location: all-quiz.php');
exit();