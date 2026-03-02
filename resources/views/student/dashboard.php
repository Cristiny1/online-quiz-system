<?php

session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: /OnlineQuizSystem/login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Online Quiz System</title>
    <link rel="stylesheet" href="/OnlineQuizSystem/public/css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Online Quiz System</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="/OnlineQuizSystem/logout.php">Logout</a>
            </div>
        </nav>

        <main class="dashboard">
            <h2>Dashboard</h2>
            
            <section class="quiz-section">
                <h3>Available Quizzes</h3>
                <div class="quiz-list">
                    <!-- Quiz items will be loaded here -->
                </div>
            </section>

            <section class="results-section">
                <h3>Your Results</h3>
                <div class="results-list">
                    <!-- Results will be loaded here -->
                </div>
            </section>
        </main>
    </div>
</body>
</html>