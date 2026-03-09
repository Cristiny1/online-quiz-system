<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /online_quiz_system/login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../../config/database.php';

// Get database connection
$pdo = require_once __DIR__ . '/../../../config/database.php';

// Check if quiz ID is provided
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid quiz ID.';
    header('Location: all-quiz.php');
    exit();
}

$quizId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';

// Initialize variables
$quiz = [];
$message = '';
$error = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch quiz details
try {
    if ($userRole === 'admin') {
        // Admin can edit any quiz
        $stmt = $pdo->prepare("
            SELECT q.*, u.username as creator_name 
            FROM quizzes q
            JOIN users u ON q.creator_id = u.id
            WHERE q.id = :id
        ");
        $stmt->execute(['id' => $quizId]);
    } else {
        // Teacher can only edit their own quizzes
        $stmt = $pdo->prepare("
            SELECT * FROM quizzes 
            WHERE id = :id AND creator_id = :creator_id
        ");
        $stmt->execute([
            'id' => $quizId,
            'creator_id' => $userId
        ]);
    }
    
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error'] = 'Quiz not found or you do not have permission to edit it.';
        header('Location: all-quiz.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Error fetching quiz for edit: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to load quiz. Please try again.';
    header('Location: all-quiz.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token.';
    } else {
        // Get and validate form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = intval($_POST['duration'] ?? 30);
        $status = $_POST['status'] ?? 'draft';
        $category = $_POST['category'] ?? $quiz['category'];
        $difficulty = $_POST['difficulty_level'] ?? $quiz['difficulty_level'];
        $passingScore = intval($_POST['passing_score'] ?? $quiz['passing_score']);

        // Validate inputs
        if (empty($title)) {
            $error = 'Quiz title is required.';
        } elseif ($duration < 1 || $duration > 180) {
            $error = 'Duration must be between 1 and 180 minutes.';
        } elseif (!in_array($status, ['draft', 'published', 'archived'])) {
            $error = 'Invalid status.';
        } else {
            try {
                // Update quiz
                $stmt = $pdo->prepare("
                    UPDATE quizzes 
                    SET title = :title, 
                        description = :description, 
                        time_limit = :duration,
                        category = :category,
                        difficulty_level = :difficulty,
                        passing_score = :passing_score,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'duration' => $duration,
                    'category' => $category,
                    'difficulty' => $difficulty,
                    'passing_score' => $passingScore,
                    'status' => $status,
                    'id' => $quizId
                ]);

                $message = 'Quiz updated successfully!';
                
                // Refresh quiz data
                $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = :id");
                $stmt->execute(['id' => $quizId]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                error_log("Error updating quiz: " . $e->getMessage());
                $error = 'Failed to update quiz. Please try again.';
            }
        }
    }
}

// Get question count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quizId]);
    $questionCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $questionCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px 0;
        }
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .edit-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .edit-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .edit-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            border: none;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e0e0e0;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        @media (max-width: 768px) {
            .edit-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container edit-container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="all-quiz.php">All Quizzes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Quiz</li>
            </ol>
        </nav>

        <div class="edit-card">
            <div class="edit-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Edit Quiz
                    </h2>
                    <span class="badge bg-light text-dark">
                        ID: #<?= $quizId ?>
                    </span>
                </div>
            </div>

            <div class="edit-body">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <i class="fas fa-question-circle fa-2x text-primary mb-2"></i>
                            <div class="stats-number"><?= $questionCount ?></div>
                            <div class="text-muted">Total Questions</div>
                            <a href="view-quiz.php?id=<?= $quizId ?>" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fas fa-eye me-1"></i>View Questions
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                            <div class="stats-number"><?= date('M d, Y', strtotime($quiz['created_at'])) ?></div>
                            <div class="text-muted">Created Date</div>
                            <small class="text-muted">by <?= htmlspecialchars($quiz['creator_name'] ?? 'You') ?></small>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                        <label for="title" class="form-label">
                            <i class="fas fa-heading me-2"></i>Quiz Title
                        </label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= htmlspecialchars($quiz['title']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left me-2"></i>Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($quiz['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category" class="form-label">
                                    <i class="fas fa-tag me-2"></i>Category
                                </label>
                                <select class="form-select" id="category" name="category">
                                    <option value="mathematics" <?= $quiz['category'] == 'mathematics' ? 'selected' : '' ?>>Mathematics</option>
                                    <option value="science" <?= $quiz['category'] == 'science' ? 'selected' : '' ?>>Science</option>
                                    <option value="history" <?= $quiz['category'] == 'history' ? 'selected' : '' ?>>History</option>
                                    <option value="literature" <?= $quiz['category'] == 'literature' ? 'selected' : '' ?>>Literature</option>
                                    <option value="geography" <?= $quiz['category'] == 'geography' ? 'selected' : '' ?>>Geography</option>
                                    <option value="computer_science" <?= $quiz['category'] == 'computer_science' ? 'selected' : '' ?>>Computer Science</option>
                                    <option value="general_knowledge" <?= $quiz['category'] == 'general_knowledge' ? 'selected' : '' ?>>General Knowledge</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="difficulty_level" class="form-label">
                                    <i class="fas fa-signal me-2"></i>Difficulty
                                </label>
                                <select class="form-select" id="difficulty_level" name="difficulty_level">
                                    <option value="easy" <?= $quiz['difficulty_level'] == 'easy' ? 'selected' : '' ?>>Easy</option>
                                    <option value="medium" <?= $quiz['difficulty_level'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="hard" <?= $quiz['difficulty_level'] == 'hard' ? 'selected' : '' ?>>Hard</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="duration" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Duration (minutes)
                                </label>
                                <input type="number" class="form-control" id="duration" name="duration" 
                                       value="<?= (int)$quiz['time_limit'] ?>" min="1" max="180" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="passing_score" class="form-label">
                                    <i class="fas fa-percent me-2"></i>Passing Score (%)
                                </label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                       value="<?= (int)$quiz['passing_score'] ?>" min="0" max="100" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">
                            <i class="fas fa-toggle-on me-2"></i>Status
                        </label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?= $quiz['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $quiz['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= $quiz['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                        <small class="text-muted">
                            <?php if ($quiz['status'] === 'published'): ?>
                                <i class="fas fa-info-circle me-1"></i>Published quizzes are visible to students
                            <?php elseif ($quiz['status'] === 'draft'): ?>
                                <i class="fas fa-info-circle me-1"></i>Draft quizzes are only visible to you
                            <?php else: ?>
                                <i class="fas fa-info-circle me-1"></i>Archived quizzes are hidden
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Quiz
                        </button>
                        <a href="view-quiz.php?id=<?= $quizId ?>" class="btn btn-info text-white">
                            <i class="fas fa-eye me-2"></i>View Quiz
                        </a>
                        <a href="all-quiz.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const duration = document.getElementById('duration');
        const passingScore = document.getElementById('passing_score');
        
        if (duration.value < 1 || duration.value > 180) {
            e.preventDefault();
            alert('Duration must be between 1 and 180 minutes.');
            duration.focus();
        }
        
        if (passingScore.value < 0 || passingScore.value > 100) {
            e.preventDefault();
            alert('Passing score must be between 0 and 100.');
            passingScore.focus();
        }
    });

    // Warn before leaving if form is dirty
    let formChanged = false;
    document.querySelectorAll('form input, form select, form textarea').forEach(element => {
        element.addEventListener('change', () => formChanged = true);
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    </script>
</body>
</html>