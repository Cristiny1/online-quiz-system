<?php
declare(strict_types=1);

// Enable error reporting for development only
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Start session with secure settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /online_quiz_system/login.php');
    exit();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Include database connection
require_once __DIR__ . '/../../../config/database.php';

// Get database connection
$pdo = require_once __DIR__ . '/../../../config/database.php';

// Get user role and ID
$userRole = $_SESSION['role'] ?? 'teacher';
$userId = (int)$_SESSION['user_id'];

// Initialize variables
$quizzes = [];
$error = '';
$success = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error'] ?? '';

// Clear session messages
unset($_SESSION['success'], $_SESSION['error']);

// Pagination settings
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';
$category = $_GET['category'] ?? '';

try {
    //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Base query
    $baseQuery = "
        FROM quizzes q
        LEFT JOIN users u ON q.creator_id = u.id
        LEFT JOIN questions qst ON q.id = qst.quiz_id
    ";
    
    $whereConditions = [];
    $params = [];
    
    if ($userRole !== 'admin') {
        $whereConditions[] = "q.creator_id = :creator_id";
        $params['creator_id'] = $userId;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(q.title LIKE :search OR q.description LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    if (!empty($status)) {
        $whereConditions[] = "q.status = :status";
        $params['status'] = $status;
    }
    
    if (!empty($difficulty)) {
        $whereConditions[] = "q.difficulty_level = :difficulty";
        $params['difficulty'] = $difficulty;
    }
    
    if (!empty($category)) {
        $whereConditions[] = "q.category = :category";
        $params['category'] = $category;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Get total count
    $countQuery = "SELECT COUNT(DISTINCT q.id) as total $baseQuery $whereClause";
    //$stmt = $pdo->prepare($countQuery);
    //$stmt->execute($params);
    //$totalQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    //$totalPages = ceil($totalQuizzes / $perPage);
    
    // Get paginated quizzes
    $query = "
        SELECT q.*, 
               COUNT(DISTINCT qst.id) as question_count,
               u.username as creator_name,
               (SELECT COUNT(*) FROM attempts WHERE quiz_id = q.id) as attempt_count
        $baseQuery
        $whereClause
        GROUP BY q.id, u.username
        ORDER BY q.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    //$stmt = $pdo->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
       // $stmt->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    //$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    //$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    //$stmt->execute();
    //$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching quizzes: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $error = "Failed to load quizzes. Please try again later.";
}

// Get unique categories for filter
try {
    //$categories = $pdo->query("SELECT DISTINCT category FROM quizzes WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

$pageTitle = "All Quizzes - " . ucfirst($userRole) . " Panel";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            padding-bottom: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin-top: 30px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table th {
            background: #343a40;
            color: white;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .badge-status {
            padding: 8px 12px;
            font-weight: 500;
            border-radius: 20px;
        }
        
        .badge-published { background: #10b981; color: white; }
        .badge-draft { background: var(--warning-color); color: white; }
        .badge-archived { background: #6c757d; color: white; }
        
        .btn-action {
            margin: 0 2px;
            border-radius: 8px;
            padding: 6px 12px;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .quiz-title {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            cursor: pointer;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: var(--primary-color);
        }
        
        .pagination .active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .btn-group-responsive {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-group-responsive .btn {
                width: 100%;
            }
        }
        
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
        
        .loading-spinner.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">All Quizzes</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeIn">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold">
                        <i class="fas fa-puzzle-piece me-3"></i>
                        All Quizzes
                    </h1>
                    <p class="lead mb-0">Manage and monitor all quizzes in the system</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="create-quiz.php" class="btn btn-light btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>Create New Quiz
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error) || !empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error ?: $errorMsg, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-card">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label fw-bold">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by title or description..." 
                                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label fw-bold">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="difficulty" class="form-label fw-bold">Difficulty</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="">All Difficulties</option>
                            <option value="easy" <?= $difficulty === 'easy' ? 'selected' : '' ?>>Easy</option>
                            <option value="medium" <?= $difficulty === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="hard" <?= $difficulty === 'hard' ? 'selected' : '' ?>>Hard</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label fw-bold">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" 
                                    <?= $category === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($cat), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="all-quiz.php" class="btn btn-outline-secondary ms-2 w-100">
                            <i class="fas fa-undo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quizzes Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Status</th>
                            <?php if ($userRole === 'admin'): ?>
                                <th>Created By</th>
                            <?php endif; ?>
                            <th>Difficulty</th>
                            <th>Category</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quizzes)): ?>
                            <tr>
                                <td colspan="<?= $userRole === 'admin' ? 11 : 10 ?>" class="text-center py-5">
                                    <i class="fas fa-puzzle-piece fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No quizzes found</h5>
                                    <p class="text-muted">Try adjusting your filters or create a new quiz</p>
                                    <a href="create-quiz.php" class="btn btn-primary">
                                        <i class="fas fa-plus-circle me-2"></i>Create New Quiz
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= (int)$quiz['id'] ?></span>
                                    </td>
                                    <td>
                                        <span class="quiz-title"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span title="<?= htmlspecialchars($quiz['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(mb_substr($quiz['description'] ?? '', 0, 50), ENT_QUOTES, 'UTF-8') ?>
                                            <?= mb_strlen($quiz['description'] ?? '') > 50 ? '...' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="fas fa-question-circle me-1"></i>
                                            <?= (int)($quiz['question_count'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-users me-1"></i>
                                            <?= (int)($quiz['attempt_count'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $quiz['status'] ?? 'draft';
                                        $badgeClass = $status === 'published' ? 'badge-published' : 
                                                     ($status === 'draft' ? 'badge-draft' : 'badge-archived');
                                        ?>
                                        <span class="badge-status <?= $badgeClass ?>">
                                            <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <?php if ($userRole === 'admin'): ?>
                                        <td>
                                            <i class="fas fa-user me-1 text-muted"></i>
                                            <?= htmlspecialchars($quiz['creator_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php
                                        $level = $quiz['difficulty_level'] ?? 'medium';
                                        $levelClass = $level === 'easy' ? 'bg-success' : 
                                                     ($level === 'medium' ? 'bg-warning text-dark' : 'bg-danger');
                                        ?>
                                        <span class="badge <?= $levelClass ?>">
                                            <?= ucfirst(htmlspecialchars($level, ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($quiz['category'])): ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-tag me-1"></i>
                                                <?= htmlspecialchars(ucfirst($quiz['category']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="far fa-calendar-alt me-1 text-muted"></i>
                                        <?= date('M d, Y', strtotime($quiz['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="view-quiz.php?id=<?= (int)$quiz['id'] ?>" 
                                               class="btn btn-info text-white btn-action" 
                                               title="View Quiz"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-quiz.php?id=<?= (int)$quiz['id'] ?>" 
                                               class="btn btn-warning btn-action" 
                                               title="Edit Quiz"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="quiz-statistics.php?id=<?= (int)$quiz['id'] ?>" 
                                               class="btn btn-success btn-action" 
                                               title="Statistics"
                                               data-bs-toggle="tooltip">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                            <?php if ($userRole === 'admin' || (isset($quiz['creator_id']) && $quiz['creator_id'] == $userId)): ?>
                                                <button type="button" 
                                                       class="btn btn-danger btn-action" 
                                                       onclick="confirmDelete(<?= (int)$quiz['id'] ?>, '<?= htmlspecialchars(addslashes($quiz['title']), ENT_QUOTES, 'UTF-8') ?>')"
                                                       title="Delete Quiz"
                                                       data-bs-toggle="tooltip">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Cards -->
        <?php if (!empty($quizzes)): ?>
            <div class="row mt-4 g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Quizzes</h6>
                                <h2 class="mb-0 fw-bold"><?= $totalQuizzes ?></h2>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-puzzle-piece fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="mt-3 text-muted small">
                            <i class="fas fa-arrow-up text-success me-1"></i>
                            <?= count($quizzes) ?> shown
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Published</h6>
                                <h2 class="mb-0 fw-bold">
                                    <?= count(array_filter($quizzes, fn($q) => ($q['status'] ?? '') === 'published')) ?>
                                </h2>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Drafts</h6>
                                <h2 class="mb-0 fw-bold">
                                    <?= count(array_filter($quizzes, fn($q) => ($q['status'] ?? '') === 'draft')) ?>
                                </h2>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-pen fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Questions</h6>
                                <h2 class="mb-0 fw-bold">
                                    <?= array_sum(array_column($quizzes, 'question_count')) ?>
                                </h2>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-question-circle fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to delete the quiz: <strong id="deleteQuizTitle"></strong>?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All questions, attempts, and related data will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Quiz
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Delete confirmation
        function confirmDelete(quizId, quizTitle) {
            document.getElementById('deleteQuizTitle').textContent = quizTitle;
            document.getElementById('confirmDeleteBtn').href = 'delete-quiz.php?id=' + quizId + '&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Show loading spinner on form submission
        document.getElementById('filterForm')?.addEventListener('submit', function() {
            document.getElementById('loadingSpinner').classList.add('show');
        });

        // Show loading spinner on pagination clicks
        document.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled')) {
                    document.getElementById('loadingSpinner').classList.add('show');
                }
            });
        });

        // Auto-submit filter on select change (optional)
        const filterSelects = document.querySelectorAll('#status, #difficulty, #category');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + N for new quiz
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create-quiz.php';
            }
            // Ctrl + F for focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('search');
                if (searchInput === document.activeElement) {
                    searchInput.value = '';
                }
            }
        });

        // Remember last active tab (if using tabs)
        if (localStorage.getItem('activeTab')) {
            const activeTab = document.querySelector(`[data-bs-target="${localStorage.getItem('activeTab')}"]`);
            if (activeTab) {
                new bootstrap.Tab(activeTab).show();
            }
        }

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem('activeTab', e.target.getAttribute('data-bs-target'));
            });
        });
    </script>
</body>
</html>