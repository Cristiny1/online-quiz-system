<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Authentication — accessible to both teachers and admins
if (!isset($_SESSION['user_id'])) {
    header('Location: /online_quiz_system/login.php');
    exit();
}

// Session regeneration
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../../config/database.php';

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';

$success  = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Pagination
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $params = ['creator_id' => $userId];
    $where  = 'WHERE q.creator_id = :creator_id';

    if (!empty($search)) {
        $where           .= ' AND q.title LIKE :search';
        $params['search'] = "%$search%";
    }

    // Total count
    $stmt        = $pdo->prepare("SELECT COUNT(*) FROM quizzes q $where");
    $stmt->execute($params);
    $totalQuizzes = (int) $stmt->fetchColumn();
    $totalPages   = (int) ceil($totalQuizzes / $perPage);

    // Fetch quizzes with question + attempt counts
    $stmt = $pdo->prepare("
        SELECT q.*,
               COUNT(DISTINCT qst.id) AS question_count,
               COUNT(DISTINCT a.id)   AS attempt_count
        FROM quizzes q
        LEFT JOIN questions qst ON q.id = qst.quiz_id
        LEFT JOIN attempts  a   ON q.id = a.quiz_id
        $where
        GROUP BY q.id
        ORDER BY q.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('My-quizzes fetch error: ' . $e->getMessage());
    $quizzes      = [];
    $totalQuizzes = 0;
    $totalPages   = 0;
    $errorMsg     = 'Failed to load quizzes. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes — QuizMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --secondary: #3f37c9; }
        body { background: #f8f9fa; font-family: 'Inter', system-ui, sans-serif; padding-bottom: 40px; }
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; padding: 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,.1);
        }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,.05); overflow: hidden; }
        .table th { background: #343a40; color: white; white-space: nowrap; }
        .badge-published { background: #10b981; color: white; padding: 6px 12px; border-radius: 20px; }
        .badge-draft     { background: #f8961e; color: white; padding: 6px 12px; border-radius: 20px; }
        .badge-archived  { background: #6c757d; color: white; padding: 6px 12px; border-radius: 20px; }
    </style>
</head>
<body>
<div class="container mt-4" style="max-width:1200px">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">My Quizzes</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="fw-bold mb-1"><i class="fas fa-puzzle-piece me-2"></i>My Quizzes</h1>
            <p class="mb-0 opacity-75">You have <?= $totalQuizzes ?> quiz<?= $totalQuizzes !== 1 ? 'zes' : '' ?> in total</p>
        </div>
        <a href="create-quiz.php" class="btn btn-light">
            <i class="fas fa-plus-circle me-2"></i>Create New Quiz
        </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width:400px">
            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control" name="search" placeholder="Search your quizzes…"
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if (!empty($search)): ?>
                <a href="my-quiz.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Questions</th>
                        <th>Attempts</th>
                        <th>Status</th>
                        <th>Difficulty</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quizzes)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-puzzle-piece fa-3x text-muted d-block mb-3"></i>
                                <h5 class="text-muted">No quizzes yet</h5>
                                <a href="create-quiz.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-plus-circle me-2"></i>Create Your First Quiz
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quizzes as $quiz): ?>
                            <?php
                            $st  = $quiz['status'] ?? 'draft';
                            $cls = match ($st) { 'published' => 'badge-published', 'archived' => 'badge-archived', default => 'badge-draft' };
                            $lvl = $quiz['difficulty_level'] ?? 'medium';
                            $lvlCls = match ($lvl) { 'easy' => 'bg-success', 'hard' => 'bg-danger', default => 'bg-warning text-dark' };
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= (int) $quiz['id'] ?></span></td>
                                <td class="fw-semibold"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-info"><?= (int) $quiz['question_count'] ?></span></td>
                                <td><span class="badge bg-primary"><?= (int) $quiz['attempt_count'] ?></span></td>
                                <td><span class="<?= $cls ?>"><?= ucfirst($st) ?></span></td>
                                <td><span class="badge <?= $lvlCls ?>"><?= ucfirst($lvl) ?></span></td>
                                <td><?= date('M d, Y', strtotime($quiz['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view-quiz.php?id=<?= (int) $quiz['id'] ?>" class="btn btn-info text-white" title="View"><i class="fas fa-eye"></i></a>
                                        <a href="edit-quiz.php?id=<?= (int) $quiz['id'] ?>" class="btn btn-warning"          title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="quiz-statistics.php?id=<?= (int) $quiz['id'] ?>" class="btn btn-success"    title="Stats"><i class="fas fa-chart-bar"></i></a>
                                        <button type="button" class="btn btn-danger"
                                                onclick="confirmDelete(<?= (int) $quiz['id'] ?>, '<?= htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES, 'UTF-8') ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="p-3">
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete: <strong id="deleteQuizTitle"></strong>?</p>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    This cannot be undone. All questions and attempt data will be deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, title) {
    document.getElementById('deleteQuizTitle').textContent = title;
    document.getElementById('confirmDeleteBtn').href =
        'delete-quiz.php?id=' + id + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html>