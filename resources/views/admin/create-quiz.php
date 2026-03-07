<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For testing - set dummy session if not logged in (REMOVE THIS IN PRODUCTION)
if (!isset($_SESSION['user_id'])) {
    // Temporary: Auto-login for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = 'testuser';
}

// Check if user is authenticated and has teacher or admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} elseif (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin'])) {
    $_SESSION['error'] = "Unauthorized access!";
    header('Location: login.php');
    exit();
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_title = trim($_POST['quiz_title'] ?? '');
    $quiz_description = trim($_POST['quiz_description'] ?? '');
    $quiz_duration = (int)($_POST['quiz_duration'] ?? 0);
    $passing_score = (int)($_POST['passing_score'] ?? 60);
    
    // Get checkbox values
    $randomize_questions = isset($_POST['randomize_questions']) ? 1 : 0;
    $show_results = isset($_POST['show_results']) ? 1 : 0;
    $allow_retake = isset($_POST['allow_retake']) ? 1 : 0;
    
    if (empty($quiz_title)) {
        $error = 'Please enter a quiz title.';
    } elseif (empty($quiz_description)) {
        $error = 'Please enter a quiz description.';
    } elseif ($quiz_duration <= 0) {
        $error = 'Please enter a valid duration.';
    } else {
        // Success! In real application, save to database here
        $message = 'Quiz created successfully!';
        
        // You can store the data to show it was received
        $_SESSION['last_quiz'] = [
            'title' => $quiz_title,
            'description' => $quiz_description,
            'duration' => $quiz_duration,
            'passing_score' => $passing_score
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Quiz System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .card {
            border-radius: 10px;
            border: none;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Quiz System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create-quiz.php">
                            <i class="fas fa-plus-circle me-1"></i>Create Quiz
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">
                            <i class="fas fa-list me-1"></i>My Quizzes
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="quizzes.php" class="text-decoration-none">Quizzes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create Quiz</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-plus-circle text-primary me-2"></i>
                Create New Quiz
            </h2>
            <div>
                <a href="quizzes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Show last created quiz info (for demo) -->
        <?php if (isset($_SESSION['last_quiz']) && !$message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Last created quiz:</strong> "<?= htmlspecialchars($_SESSION['last_quiz']['title']) ?>" 
                (<?= $_SESSION['last_quiz']['duration'] ?> minutes)
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Main Form Card -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2 text-primary"></i>
                            Quiz Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="create-quiz.php" class="needs-validation" novalidate>
                            <!-- Quiz Title -->
                            <div class="mb-4">
                                <label for="quiz_title" class="form-label fw-bold required-field">Quiz Title</label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="quiz_title" 
                                       name="quiz_title" 
                                       placeholder="e.g., JavaScript Fundamentals Quiz"
                                       value="<?= htmlspecialchars($_POST['quiz_title'] ?? '') ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Please enter a quiz title.
                                </div>
                                <div class="form-text">
                                    Choose a descriptive title that reflects the quiz content.
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <label for="quiz_description" class="form-label fw-bold required-field">Description</label>
                                <textarea class="form-control" 
                                          id="quiz_description" 
                                          name="quiz_description" 
                                          rows="4" 
                                          placeholder="Describe the quiz topics, difficulty level, and any important instructions..."
                                          required><?= htmlspecialchars($_POST['quiz_description'] ?? '') ?></textarea>
                                <div class="invalid-feedback">
                                    Please enter a quiz description.
                                </div>
                                <div class="form-text">
                                    Provide clear instructions and information about what the quiz covers.
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Duration -->
                                <div class="col-md-6 mb-4">
                                    <label for="quiz_duration" class="form-label fw-bold required-field">Duration (minutes)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="far fa-clock"></i></span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="quiz_duration" 
                                               name="quiz_duration" 
                                               min="1" 
                                               max="480" 
                                               value="<?= htmlspecialchars($_POST['quiz_duration'] ?? '30') ?>"
                                               required>
                                    </div>
                                    <div class="invalid-feedback">
                                        Please enter a valid duration (1-480 minutes).
                                    </div>
                                    <div class="form-text">
                                        Maximum 8 hours (480 minutes).
                                    </div>
                                </div>
                                
                                <!-- Passing Score -->
                                <div class="col-md-6 mb-4">
                                    <label for="passing_score" class="form-label fw-bold">Passing Score (%)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="passing_score" 
                                               name="passing_score" 
                                               min="0" 
                                               max="100" 
                                               value="<?= htmlspecialchars($_POST['passing_score'] ?? '60') ?>">
                                    </div>
                                    <div class="form-text">
                                        Minimum score required to pass (default: 60%).
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Settings -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Additional Settings</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="randomize_questions" name="randomize_questions"
                                               <?= isset($_POST['randomize_questions']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="randomize_questions">
                                            <i class="fas fa-random me-2 text-secondary"></i>
                                            Randomize question order
                                        </label>
                                        <div class="form-text ms-4">
                                            Questions will appear in random order for each student.
                                        </div>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="show_results" name="show_results" 
                                               <?= !isset($_POST['show_results']) || isset($_POST['show_results']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="show_results">
                                            <i class="fas fa-eye me-2 text-secondary"></i>
                                            Show results immediately after submission
                                        </label>
                                        <div class="form-text ms-4">
                                            Students will see their score right after completing the quiz.
                                        </div>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="allow_retake" name="allow_retake"
                                               <?= isset($_POST['allow_retake']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="allow_retake">
                                            <i class="fas fa-redo me-2 text-secondary"></i>
                                            Allow students to retake the quiz
                                        </label>
                                        <div class="form-text ms-4">
                                            Students can take the quiz multiple times.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="fas fa-save me-2"></i>Create Quiz
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Help Card -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Quick Tips
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Clear Title:</strong> Make it descriptive and easy to understand
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Detailed Description:</strong> Include topics covered and instructions
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Appropriate Duration:</strong> Consider number of questions and difficulty
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Passing Score:</strong> Set realistic expectations (usually 60-70%)
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Add Questions:</strong> You can add questions after creating the quiz
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Stats Card -->
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar text-info me-2"></i>
                            Your Quiz Stats
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <h3 class="mb-0">0</h3>
                            <small class="text-muted">Total Quizzes Created</small>
                        </div>
                        <div class="text-center">
                            <h3 class="mb-0">0</h3>
                            <small class="text-muted">Active Quizzes</small>
                        </div>
                        <hr>
                        <a href="quizzes.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-list me-2"></i>View All Quizzes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form validation script -->
    <script>
        // Enable Bootstrap form validation
        (function() {
            'use strict';
            
            // Fetch all forms that need validation
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        })();
        
        // Log when page loads
        console.log('Create Quiz page loaded successfully!');
    </script>
    
    <!-- Display form submission status -->
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <script>
        console.log('Form submitted successfully!');
        <?php if ($message): ?>
        console.log('Quiz created: <?= htmlspecialchars(addslashes($quiz_title)) ?>');
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>