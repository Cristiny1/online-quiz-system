<?php
session_start();

// Check if user is authenticated and has teacher or admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
} elseif (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin'])) {
    $_SESSION['error'] = "Unauthorized access!";
    header('Location: /login.php');
    exit();
}

// Get quiz ID from URL
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quiz_id <= 0) {
    header('Location: /dashboard.php');
    exit();
}

$message = '';
$error = '';

// TODO: Load quiz from database
// $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
// $stmt->execute([$quiz_id]);
// $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
// if (!$quiz) { header('Location: /dashboard.php'); exit(); }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration    = (int)($_POST['duration'] ?? 0);
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'inactive';

    if (empty($title) || $duration <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // TODO: Update quiz in database
        // $stmt = $pdo->prepare("UPDATE quizzes SET title=?, description=?, duration=?, status=? WHERE id=?");
        // $stmt->execute([$title, $description, $duration, $status, $quiz_id]);
        $message = 'Quiz updated successfully!';
    }
}

// Placeholder values (replace with DB data)
$quiz = $quiz ?? ['title' => '', 'description' => '', 'duration' => 30, 'status' => 'active'];
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Edit Quiz</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Quiz Title</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($quiz['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($quiz['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="duration">Duration (minutes)</label>
                <input type="number" id="duration" name="duration" value="<?= (int)$quiz['duration'] ?>" min="1" required>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active"  <?= $quiz['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"<?= $quiz['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Update Quiz</button>
            <a href="/dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>