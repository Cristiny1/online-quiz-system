<?php
// Admin Quiz Statistics Page
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: /login');
    exit;
}

// Get quiz statistics from database
$quizzes = [];
$totalQuestions = 0;
$totalAttempts = 0;

try {
    // Example: Fetch quiz data (adjust based on your database structure)
    $stmt = $pdo->query("SELECT q.id, q.title, COUNT(a.id) as attempts, AVG(a.score) as avg_score 
                         FROM quizzes q 
                         LEFT JOIN attempts a ON q.id = a.quiz_id 
                         GROUP BY q.id");
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quiz Statistics</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Quiz Statistics</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Quiz Title</th>
                    <th>Total Attempts</th>
                    <th>Average Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><?= htmlspecialchars($quiz['title']) ?></td>
                        <td><?= $quiz['attempts'] ?></td>
                        <td><?= round($quiz['avg_score'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>