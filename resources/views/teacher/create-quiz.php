<?php
session_start();

// Check if user is authenticated and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: /login.php');
    exit();
}elseif ($_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "Unauthorized access!";
    header('Location: /login.php');
    exit();
}else {
    header('Location: /login.php');
    exit();
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_title = trim($_POST['quiz_title'] ?? '');
    $quiz_description = trim($_POST['quiz_description'] ?? '');
    $quiz_duration = (int)($_POST['quiz_duration'] ?? 0);
    $passing_score = (int)($_POST['passing_score'] ?? 0);
    
    if (empty($quiz_title) || empty($quiz_description) || $quiz_duration <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // TODO: Save to database
        $message = 'Quiz created successfully!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; }
        button { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .message { padding: 10px; margin-bottom: 20px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Create New Quiz</h1>
    
    <?php if ($message): ?>
        <div class="message success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="quiz_title">Quiz Title *</label>
            <input type="text" id="quiz_title" name="quiz_title" required>
        </div>
        
        <div class="form-group">
            <label for="quiz_description">Description *</label>
            <textarea id="quiz_description" name="quiz_description" rows="4" required></textarea>
        </div>
        
        <div class="form-group">
            <label for="quiz_duration">Duration (minutes) *</label>
            <input type="number" id="quiz_duration" name="quiz_duration" min="1" required>
        </div>
        
        <div class="form-group">
            <label for="passing_score">Passing Score (%)</label>
            <input type="number" id="passing_score" name="passing_score" min="0" max="100" value="60">
        </div>
        
        <button type="submit">Create Quiz</button>
    </form>
</body>
</html>