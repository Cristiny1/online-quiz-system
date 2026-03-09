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

// Check if quiz ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: all-quiz.php');
    exit();
}

$quizId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';

// Fetch quiz details
try {
    // Get quiz info
    $stmt = $pdo->prepare("
        SELECT q.*, u.username as creator_name 
        FROM quizzes q
        JOIN users u ON q.creator_id = u.id
        WHERE q.id = :id
    ");
    $stmt->execute(['id' => $quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        header('Location: all-quiz.php');
        exit();
    }

    // Check permission
    if ($userRole !== 'admin' && $quiz['creator_id'] != $userId) {
        $_SESSION['error'] = 'You do not have permission to view this quiz.';
        header('Location: all-quiz.php');
        exit();
    }

    // Get questions for this quiz
    $stmt = $pdo->prepare("
        SELECT q.*, 
               COUNT(o.id) as option_count
        FROM questions q
        LEFT JOIN options o ON q.id = o.question_id
        WHERE q.quiz_id = :quiz_id
        GROUP BY q.id
        ORDER BY q.order_number NULLS LAST, q.id
    ");
    $stmt->execute(['quiz_id' => $quizId]);
    $questions = $stmt->fetchAll();

    // Get options for each question
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("
            SELECT * FROM options 
            WHERE question_id = :question_id 
            ORDER BY order_number NULLS LAST, id
        ");
        $stmt->execute(['question_id' => $question['id']]);
        $question['options'] = $stmt->fetchAll();
    }

    // Get quiz statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT user_id) as total_students,
            COUNT(*) as total_attempts,
            AVG(score) as avg_score,
            MAX(score) as max_score,
            MIN(score) as min_score,
            COUNT(CASE WHEN score >= passing_score THEN 1 END) as passed_count
        FROM attempts a
        JOIN quizzes q ON a.quiz_id = q.id
        WHERE a.quiz_id = :quiz_id AND a.status = 'completed'
    ");
    $stmt->execute(['quiz_id' => $quizId]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error viewing quiz: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to load quiz. Please try again.';
    header('Location: all-quiz.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quiz: <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        .question-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .option-item {
            padding: 10px 15px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #6c757d;
        }
        .correct-option {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Quiz Header -->
    <div class="quiz-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold"><?= htmlspecialchars($quiz['title']) ?></h1>
                    <p class="lead mb-0"><?= htmlspecialchars($quiz['description']) ?></p>
                </div>
                <div>
                    <a href="all-quiz.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                    </a>
                    <a href="edit-quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Edit Quiz
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Quiz Info -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x text-primary mb-3"></i>
                    <div class="stat-value"><?= $quiz['time_limit'] ?></div>
                    <div class="text-muted">Minutes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-star fa-2x text-warning mb-3"></i>
                    <div class="stat-value"><?= $quiz['passing_score'] ?>%</div>
                    <div class="text-muted">Passing Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-question-circle fa-2x text-success mb-3"></i>
                    <div class="stat-value"><?= count($questions) ?></div>
                    <div class="text-muted">Questions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-tag fa-2x text-info mb-3"></i>
                    <div class="stat-value"><?= ucfirst($quiz['difficulty_level'] ?? 'medium') ?></div>
                    <div class="text-muted">Difficulty</div>
                </div>
            </div>
        </div>

        <!-- Statistics Section -->
        <?php if ($stats && $stats['total_attempts'] > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Quiz Statistics</h3>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_students'] ?></div>
                    <div class="text-muted">Students Attempted</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= round($stats['avg_score'] ?? 0, 1) ?>%</div>
                    <div class="text-muted">Average Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= round($stats['max_score'] ?? 0, 1) ?>%</div>
                    <div class="text-muted">Highest Score</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['passed_count'] ?? 0 ?></div>
                    <div class="text-muted">Passed</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Questions Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-question-circle me-2 text-primary"></i>Questions</h3>
            <a href="add-question.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i>Add Question
            </a>
        </div>

        <?php if (empty($questions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No questions yet. <a href="add-question.php?quiz_id=<?= $quiz['id'] ?>" class="alert-link">Add your first question</a>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">
                                <span class="badge bg-primary me-2">Q<?= $index + 1 ?></span>
                                <?= htmlspecialchars($question['question_text']) ?>
                            </h5>
                            <small class="text-muted">
                                <i class="fas fa-tag me-1"></i><?= ucfirst(str_replace('_', ' ', $question['question_type'])) ?>
                                | <i class="fas fa-star me-1"></i><?= $question['points'] ?> points
                            </small>
                        </div>
                        <div>
                            <a href="edit-question.php?id=<?= $question['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete-question.php?id=<?= $question['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this question?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($question['options'])): ?>
                        <div class="mt-3">
                            <small class="text-muted fw-bold">Options:</small>
                            <?php foreach ($question['options'] as $option): ?>
                                <div class="option-item <?= $option['is_correct'] ? 'correct-option' : '' ?>">
                                    <i class="fas <?= $option['is_correct'] ? 'fa-check-circle text-success' : 'fa-circle text-muted' ?> me-2"></i>
                                    <?= htmlspecialchars($option['option_text']) ?>
                                    <?php if ($option['is_correct']): ?>
                                        <span class="badge bg-success ms-2">Correct Answer</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>