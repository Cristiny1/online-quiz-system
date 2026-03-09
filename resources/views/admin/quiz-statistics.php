<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /online_quiz_system/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';

// FIX: added quiz_id filter mode (single quiz) and list mode (all quizzes)
$quizId = isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)
    ? (int) $_GET['id']
    : null;

$quizDetail = null;
$quizzes    = [];
$error      = '';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($quizId !== null) {
        // --- Single quiz statistics ---
        // Verify access
        if ($userRole === 'admin') {
            $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = :id');
            $stmt->execute(['id' => $quizId]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = :id AND creator_id = :creator_id');
            $stmt->execute(['id' => $quizId, 'creator_id' => $userId]);
        }
        $quizDetail = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quizDetail) {
            $error = 'Quiz not found or you do not have permission to view its statistics.';
        } else {
            // Per-attempt detail
            $stmt = $pdo->prepare("
                SELECT a.*, u.username AS student_name, ROUND(a.score, 1) AS rounded_score
                FROM attempts a
                JOIN users u ON a.user_id = u.id
                WHERE a.quiz_id = :quiz_id
                ORDER BY a.created_at DESC
                LIMIT 50
            ");
            $stmt->execute(['quiz_id' => $quizId]);
            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregate stats
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)            AS total_attempts,
                    COUNT(DISTINCT user_id) AS unique_students,
                    ROUND(AVG(score),1) AS avg_score,
                    MAX(score)          AS max_score,
                    MIN(score)          AS min_score,
                    SUM(CASE WHEN score >= :passing THEN 1 ELSE 0 END) AS passed_count
                FROM attempts
                WHERE quiz_id = :quiz_id AND status = 'completed'
            ");
            $stmt->execute(['quiz_id' => $quizId, 'passing' => (float) ($quizDetail['passing_score'] ?? 60)]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // --- All quizzes statistics (list view) ---
        if ($userRole === 'admin') {
            $quizzes = $pdo->query("
                SELECT q.id, q.title,
                       COUNT(a.id)            AS attempts,
                       ROUND(AVG(a.score), 2) AS avg_score
                FROM quizzes q
                LEFT JOIN attempts a ON q.id = a.quiz_id
                GROUP BY q.id
                ORDER BY attempts DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("
                SELECT q.id, q.title,
                       COUNT(a.id)            AS attempts,
                       ROUND(AVG(a.score), 2) AS avg_score
                FROM quizzes q
                LEFT JOIN attempts a ON q.id = a.quiz_id
                WHERE q.creator_id = :creator_id
                GROUP BY q.id
                ORDER BY attempts DESC
            ");
            $stmt->execute(['creator_id' => $userId]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('Quiz statistics error: ' . $e->getMessage());
    $error = 'Failed to load statistics. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Statistics — QuizMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --secondary: #3f37c9; }
        body { background: #f8f9fa; font-family: 'Inter', system-ui, sans-serif; padding-bottom: 40px; }
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.06); text-align: center;
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .card { border: none; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,.06); }
        .table th { background: #343a40; color: white; }
    </style>
</head>
<body>
<div class="container mt-4" style="max-width: 1200px">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="all-quiz.php">All Quizzes</a></li>
            <li class="breadcrumb-item active">Statistics</li>
        </ol>
    </nav>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($quizId && $quizDetail): ?>
        <!-- Single Quiz Statistics -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h1 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>Quiz Statistics</h1>
                    <p class="mb-0 opacity-75"><?= htmlspecialchars($quizDetail['title'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a href="view-quiz.php?id=<?= $quizId ?>" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Quiz
                </a>
            </div>
        </div>

        <?php if (!empty($stats) && (int) $stats['total_attempts'] > 0): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= (int) $stats['total_attempts'] ?></div><div class="text-muted small">Total Attempts</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= (int) $stats['unique_students'] ?></div><div class="text-muted small">Unique Students</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= $stats['avg_score'] ?? '—' ?>%</div><div class="text-muted small">Avg Score</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= round((float)($stats['max_score'] ?? 0), 1) ?>%</div><div class="text-muted small">Highest</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= round((float)($stats['min_score'] ?? 0), 1) ?>%</div><div class="text-muted small">Lowest</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card"><div class="stat-value"><?= (int) ($stats['passed_count'] ?? 0) ?></div><div class="text-muted small">Passed</div></div></div>
        </div>

        <div class="card">
            <div class="card-header p-3 fw-semibold"><i class="fas fa-list me-2 text-primary"></i>Recent Attempts</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td><?= htmlspecialchars($attempt['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="fw-bold <?= (float)$attempt['rounded_score'] >= (float)($quizDetail['passing_score'] ?? 60) ? 'text-success' : 'text-danger' ?>">
                                    <?= $attempt['rounded_score'] ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $attempt['status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= ucfirst(htmlspecialchars($attempt['status'], ENT_QUOTES, 'UTF-8')) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($attempt['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No attempts recorded for this quiz yet.</div>
        <?php endif; ?>

    <?php else: ?>
        <!-- All Quizzes Statistics -->
        <div class="page-header">
            <h1 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>Quiz Statistics</h1>
            <p class="mb-0 opacity-75">Overview of all quiz performance</p>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Total Attempts</th>
                            <th>Average Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quizzes)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No data available</td></tr>
                        <?php else: ?>
                            <?php foreach ($quizzes as $quiz): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-primary"><?= (int) $quiz['attempts'] ?></span></td>
                                <td>
                                    <?php if ($quiz['attempts'] > 0): ?>
                                        <span class="fw-bold <?= (float)$quiz['avg_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                            <?= round((float) $quiz['avg_score'], 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="quiz-statistics.php?id=<?= (int) $quiz['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-chart-bar me-1"></i>Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>