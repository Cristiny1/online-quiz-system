<?php
declare(strict_types=1);

// Error handling based on environment
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Secure session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /online_quiz_system/login.php');
    exit();
}

// Session regeneration for security
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Database connection
require_once __DIR__ . '/../../../config/database.php';

try {
    $pdo = require_once __DIR__ . '/../../../config/database.php';
    //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting for quiz creation
$rateLimitKey = 'quiz_create_' . $_SESSION['user_id'];
$rateLimitPeriod = 3600; // 1 hour
$rateLimitMax = 10; // Max 10 quizzes per hour

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset_time' => time() + $rateLimitPeriod];
}

if ($_SESSION[$rateLimitKey]['reset_time'] < time()) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset_time' => time() + $rateLimitPeriod];
}

// Initialize variables
$error = '';
$success = '';
$title = $description = $category = $level = '';
$timeLimit = 30;
$passingScore = 60;

// Predefined valid options
$validCategories = [
    'mathematics' => 'Mathematics',
    'science' => 'Science',
    'history' => 'History',
    'literature' => 'Literature',
    'geography' => 'Geography',
    'computer_science' => 'Computer Science',
    'general_knowledge' => 'General Knowledge'
];

$validDifficulties = ['easy', 'medium', 'hard'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Rate limit check
    if ($_SESSION[$rateLimitKey]['count'] >= $rateLimitMax) {
        $waitTime = ceil(($_SESSION[$rateLimitKey]['reset_time'] - time()) / 60);
        $error = "You've reached the maximum number of quizzes per hour. Please wait {$waitTime} minutes.";
    }
    // CSRF validation
    elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
        error_log("CSRF token mismatch for user {$_SESSION['user_id']}");
    }
    // Honeypot check for bots
    elseif (!empty($_POST['website'])) {
        // Bot detected, silently redirect
        header('Location: all-quiz.php');
        exit();
    }
    else {
        // Input validation
        $title = trim($_POST['quiz_title'] ?? '');
        $description = trim($_POST['quiz_description'] ?? '');
        $category = $_POST['quiz_category'] ?? '';
        $level = $_POST['quiz_level'] ?? '';
        $timeLimit = filter_input(INPUT_POST, 'quiz_time', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 180]]) ?: 30;
        $passingScore = filter_input(INPUT_POST, 'passing_score', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]) ?: 60;

        $errors = [];

        if (empty($title)) {
            $errors[] = 'Quiz title is required.';
        } elseif (strlen($title) < 3) {
            $errors[] = 'Quiz title must be at least 3 characters.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Quiz title must not exceed 255 characters.';
        }

        if (strlen($description) > 1000) {
            $errors[] = 'Description must not exceed 1000 characters.';
        }

        if (!array_key_exists($category, $validCategories)) {
            $errors[] = 'Please select a valid category.';
        }

        if (!in_array($level, $validDifficulties, true)) {
            $errors[] = 'Please select a valid difficulty level.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Check for duplicate title by same creator
                $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE title = :title AND creator_id = :creator_id");
                $stmt->execute([
                    'title' => $title,
                    'creator_id' => $_SESSION['user_id']
                ]);
                
                if ($stmt->fetch()) {
                    throw new Exception('You already have a quiz with this title.');
                }

                // Insert quiz
                $stmt = $pdo->prepare("
                    INSERT INTO quizzes (
                        title, description, category, difficulty_level, 
                        time_limit, passing_score, creator_id, status, created_at, updated_at
                    ) VALUES (
                        :title, :description, :category, :difficulty_level,
                        :time_limit, :passing_score, :creator_id, 'draft', NOW(), NOW()
                    )
                ");
                
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'difficulty_level' => $level,
                    'time_limit' => $timeLimit,
                    'passing_score' => $passingScore,
                    'creator_id' => $_SESSION['user_id']
                ]);

                $quizId = $pdo->lastInsertId();
                
                // Update rate limit
                $_SESSION[$rateLimitKey]['count']++;
                
                // Log successful creation
                error_log("Quiz created - ID: $quizId, Title: $title, Creator: {$_SESSION['user_id']}");
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Quiz created successfully! Now add some questions.';
                header('Location: edit-quiz.php?id=' . $quizId);
                exit();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Error creating quiz: " . $e->getMessage());
                $error = $e->getMessage() ?: 'Failed to create quiz. Please try again.';
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

// Get categories for dropdown
$categories = $validCategories;

$pageTitle = "Create New Quiz";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - QuizMaster</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            padding: 40px 0;
        }

        .create-quiz-section {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .create-quiz-section h2 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 32px;
            text-align: center;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
            background: white;
            width: 100%;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .form-group input.is-invalid,
        .form-group textarea.is-invalid,
        .form-group select.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 12px;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 35px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.6);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: var(--danger-color);
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-cancel:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .breadcrumb {
            background: rgba(255,255,255,0.2);
            padding: 15px 25px;
            border-radius: 50px;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb .active {
            color: rgba(255,255,255,0.8);
        }

        .breadcrumb-item + .breadcrumb-item::before {
            color: white;
        }

        .tips-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--warning-color);
        }

        .character-counter {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .character-counter.warning {
            color: var(--warning-color);
        }

        .character-counter.danger {
            color: var(--danger-color);
        }

        .honeypot {
            display: none;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .quiz-container {
                padding: 25px;
                margin: 0 15px;
            }
            
            .btn {
                padding: 12px 25px;
                font-size: 14px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                <li class="breadcrumb-item"><a href="all-quiz.php">All Quizzes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create New Quiz</li>
            </ol>
        </nav>

        <section class="create-quiz-section">
            <div class="quiz-container">
                <h2>
                    <i class="fas fa-plus-circle me-2"></i>
                    Create New Quiz
                </h2>
                
                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <!-- Rate Limit Info -->
                <?php if (isset($_SESSION[$rateLimitKey])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You've created <?= $_SESSION[$rateLimitKey]['count'] ?> out of <?= $rateLimitMax ?> quizzes this hour.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="quiz-form" id="quizForm" novalidate>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <!-- Honeypot for bots -->
                    <div class="honeypot">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <!-- Quiz Title -->
                    <div class="form-group">
                        <label for="quiz-title">
                            <i class="fas fa-heading me-2"></i>Quiz Title
                        </label>
                        <input type="text" 
                               id="quiz-title" 
                               name="quiz_title" 
                               required 
                               maxlength="255"
                               placeholder="Enter quiz title" 
                               value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                               class="<?= !empty($error) && empty($title) ? 'is-invalid' : '' ?>">
                        <div class="character-counter" id="titleCounter">0/255</div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="quiz-description">
                            <i class="fas fa-align-left me-2"></i>Description
                        </label>
                        <textarea id="quiz-description" 
                                  name="quiz_description" 
                                  rows="4" 
                                  maxlength="1000"
                                  placeholder="Enter quiz description (optional)"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="text-muted">Brief description of what this quiz covers</small>
                        <div class="character-counter" id="descriptionCounter">0/1000</div>
                    </div>

                    <!-- Category and Difficulty -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quiz-category">
                                <i class="fas fa-tag me-2"></i>Category
                            </label>
                            <select id="quiz-category" name="quiz_category" required>
                                <option value="" disabled <?= empty($category) ? 'selected' : '' ?>>Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" 
                                        <?= $category === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quiz-level">
                                <i class="fas fa-signal me-2"></i>Difficulty Level
                            </label>
                            <select id="quiz-level" name="quiz_level" required>
                                <option value="" disabled <?= empty($level) ? 'selected' : '' ?>>Select Level</option>
                                <?php foreach ($validDifficulties as $difficulty): ?>
                                    <option value="<?= htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8') ?>" 
                                        <?= $level === $difficulty ? 'selected' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($difficulty, ENT_QUOTES, 'UTF-8')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Time and Score -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quiz-time">
                                <i class="fas fa-clock me-2"></i>Time Limit (minutes)
                            </label>
                            <input type="number" 
                                   id="quiz-time" 
                                   name="quiz_time" 
                                   min="1" 
                                   max="180" 
                                   value="<?= (int)$timeLimit ?>" 
                                   required>
                            <small class="text-muted">Between 1 and 180 minutes</small>
                        </div>

                        <div class="form-group">
                            <label for="passing-score">
                                <i class="fas fa-percent me-2"></i>Passing Score (%)
                            </label>
                            <input type="number" 
                                   id="passing-score" 
                                   name="passing_score" 
                                   min="0" 
                                   max="100" 
                                   value="<?= (int)$passingScore ?>" 
                                   required>
                            <small class="text-muted">Minimum score required to pass</small>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-2"></i>Create Quiz
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo me-2"></i>Clear
                        </button>
                        <a href="all-quiz.php" class="btn btn-cancel">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>

                <!-- Tips Section -->
                <div class="tips-section">
                    <h6 class="mb-2">
                        <i class="fas fa-lightbulb text-warning me-2"></i>
                        Quick Tips:
                    </h6>
                    <ul class="small text-muted mb-0">
                        <li>Choose a clear, descriptive title for your quiz</li>
                        <li>Set appropriate time limits based on quiz difficulty</li>
                        <li>You can add questions after creating the quiz</li>
                        <li>Preview your quiz before publishing</li>
                        <li>Use categories to help students find your quiz</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // DOM Elements
        const form = document.getElementById('quizForm');
        const titleInput = document.getElementById('quiz-title');
        const descInput = document.getElementById('quiz-description');
        const timeInput = document.getElementById('quiz-time');
        const scoreInput = document.getElementById('passing-score');
        const submitBtn = document.getElementById('submitBtn');
        const titleCounter = document.getElementById('titleCounter');
        const descCounter = document.getElementById('descriptionCounter');

        // Character counters
        function updateCounter(input, counter, maxLength) {
            const length = input.value.length;
            counter.textContent = `${length}/${maxLength}`;
            
            counter.classList.remove('warning', 'danger');
            if (length > maxLength * 0.9) {
                counter.classList.add('danger');
            } else if (length > maxLength * 0.75) {
                counter.classList.add('warning');
            }
        }

        titleInput.addEventListener('input', () => updateCounter(titleInput, titleCounter, 255));
        descInput.addEventListener('input', () => updateCounter(descInput, descCounter, 1000));

        // Initial counts
        updateCounter(titleInput, titleCounter, 255);
        updateCounter(descInput, descCounter, 1000);

        // Real-time validation
        function validateField(input, validationFn) {
            const isValid = validationFn(input.value);
            input.classList.toggle('is-invalid', !isValid);
            return isValid;
        }

        titleInput.addEventListener('blur', () => {
            validateField(titleInput, value => value.trim().length >= 3);
        });

        timeInput.addEventListener('input', () => {
            const value = parseInt(timeInput.value);
            timeInput.classList.toggle('is-invalid', value < 1 || value > 180);
        });

        scoreInput.addEventListener('input', () => {
            const value = parseInt(scoreInput.value);
            scoreInput.classList.toggle('is-invalid', value < 0 || value > 100);
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const errors = [];

            // Title validation
            if (titleInput.value.trim().length < 3) {
                isValid = false;
                errors.push('Title must be at least 3 characters');
                titleInput.classList.add('is-invalid');
            }

            // Time validation
            const time = parseInt(timeInput.value);
            if (isNaN(time) || time < 1 || time > 180) {
                isValid = false;
                errors.push('Time must be between 1 and 180 minutes');
                timeInput.classList.add('is-invalid');
            }

            // Score validation
            const score = parseInt(scoreInput.value);
            if (isNaN(score) || score < 0 || score > 100) {
                isValid = false;
                errors.push('Score must be between 0 and 100');
                scoreInput.classList.add('is-invalid');
            }

            // Category validation
            const category = document.getElementById('quiz-category');
            if (!category.value) {
                isValid = false;
                errors.push('Please select a category');
                category.classList.add('is-invalid');
            }

            // Difficulty validation
            const level = document.getElementById('quiz-level');
            if (!level.value) {
                isValid = false;
                errors.push('Please select a difficulty level');
                level.classList.add('is-invalid');
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n- ' + errors.join('\n- '));
            } else {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            }
        });

        // Remove validation on input
        [titleInput, timeInput, scoreInput].forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });

        // Auto-resize textarea
        descInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Warn before leaving if form is dirty
        let formChanged = false;
        const formElements = form.querySelectorAll('input, select, textarea');
        
        formElements.forEach(element => {
            element.addEventListener('change', () => formChanged = true);
            element.addEventListener('input', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Clear form and reset dirty flag
        form.addEventListener('reset', function() {
            setTimeout(() => {
                formChanged = false;
                titleInput.classList.remove('is-invalid');
                timeInput.classList.remove('is-invalid');
                scoreInput.classList.remove('is-invalid');
                document.getElementById('quiz-category').classList.remove('is-invalid');
                document.getElementById('quiz-level').classList.remove('is-invalid');
            }, 0);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                form.requestSubmit();
            }
            // Escape to reset
            if (e.key === 'Escape' && document.activeElement === titleInput) {
                titleInput.value = '';
            }
        });

        console.log('Create quiz page loaded');
    </script>
</body>
</html>