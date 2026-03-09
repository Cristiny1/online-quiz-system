<?php
declare(strict_types=1);

// FIX: Original file had stray AI chatbot responses embedded as plain text — fully rewritten

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /online_quiz_system/login.php');
    exit();
}

// Admin-only page
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

$error = '';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $totalUsers    = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalQuizzes  = (int) $pdo->query('SELECT COUNT(*) FROM quizzes')->fetchColumn();
    $totalAttempts = (int) $pdo->query('SELECT COUNT(*) FROM attempts')->fetchColumn();

    $avgScoreRaw = $pdo->query('SELECT AVG(score) FROM attempts WHERE status = \'completed\'')->fetchColumn();
    $avgScore    = $avgScoreRaw !== null ? round((float) $avgScoreRaw, 1) : null;

    // Recent results with pagination
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $totalRows  = (int) $pdo->query('SELECT COUNT(*) FROM attempts a JOIN users u ON a.user_id = u.id JOIN quizzes q ON a.quiz_id = q.id')->fetchColumn();
    $totalPages = (int) ceil($totalRows / $perPage);

    $stmt = $pdo->prepare("
        SELECT u.username, q.title AS quiz_title,
               ROUND(a.score, 1) AS score,
               a.status, a.created_at
        FROM attempts a
        JOIN users   u ON a.user_id  = u.id
        JOIN quizzes q ON a.quiz_id  = q.id
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top quizzes by attempts
    $topQuizzes = $pdo->query("
        SELECT q.title, COUNT(a.id) AS attempts, ROUND(AVG(a.score), 1) AS avg_score
        FROM quizzes q
        LEFT JOIN attempts a ON q.id = a.quiz_id
        GROUP BY q.id
        ORDER BY attempts DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Report fetch error: ' . $e->getMessage());
    $error         = 'Failed to load report data. Please try again.';
    $totalUsers    = $totalQuizzes = $totalAttempts = 0;
    $avgScore      = null;
    $results       = [];
    $topQuizzes    = [];
    $totalPages    = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — QuizMaster Admin</title>
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
        .stat-number { font-size: 2.4rem; font-weight: 700; color: var(--primary); }
        .stat-label  { color: #6c757d; font-size: 0.9rem; margin-top: 4px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,.06); }
        .table th { background: #343a40; color: white; }
    </style>
</head>
<body>
<div class="container mt-4" style="max-width: 1300px">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Reports</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>System Reports</h1>
        <p class="mb-0 opacity-75">Platform-wide statistics and user quiz results</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalUsers) ?></div>
                <div class="stat-label"><i class="fas fa-users me-1"></i>Total Users</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalQuizzes) ?></div>
                <div class="stat-label"><i class="fas fa-puzzle-piece me-1"></i>Total Quizzes</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalAttempts) ?></div>
                <div class="stat-label"><i class="fas fa-pen me-1"></i>Total Attempts</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= $avgScore !== null ? $avgScore . '%' : '—' ?></div>
                <div class="stat-label"><i class="fas fa-star me-1"></i>Avg Score</div>
            </div>
        </div>
    </div>

    <!-- Top Quizzes -->
    <?php if (!empty($topQuizzes)): ?>
    <div class="card mb-4">
        <div class="card-header p-3 fw-semibold bg-white"><i class="fas fa-trophy me-2 text-warning"></i>Top Quizzes by Attempts</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Quiz Title</th><th>Attempts</th><th>Avg Score</th></tr></thead>
                <tbody>
                    <?php foreach ($topQuizzes as $q): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($q['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge bg-primary"><?= (int) $q['attempts'] ?></span></td>
                        <td>
                            <?php if ((int) $q['attempts'] > 0): ?>
                                <span class="fw-bold <?= (float)$q['avg_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                    <?= $q['avg_score'] ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- User Quiz Results -->
    <div class="card">
        <div class="card-header p-3 fw-semibold bg-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2 text-primary"></i>User Quiz Results</span>
            <small class="text-muted"><?= number_format($totalRows) ?> total records</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Quiz</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['quiz_title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="fw-bold <?= (float)$row['score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                    <?= $row['score'] ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $row['status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= ucfirst(htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="p-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>