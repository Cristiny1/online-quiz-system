<?php
session_start();
require_once __DIR__ . '../../../../config/database.php';

// -----------------------
// LOGOUT HANDLING
// -----------------------
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// -----------------------
// LOGIN CHECK
// -----------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] == 'admin' ? 'admin' : 'teacher';
$username = $_SESSION['username'] ?? ucfirst($userRole);

// -----------------------
// PAGE TITLE
// -----------------------
$dashboardTitle = $userRole === 'admin' ? 'Admin Panel' : 'Teacher Dashboard';

// -----------------------
// FETCH DASHBOARD STATISTICS
// -----------------------
try {
    if ($userRole === 'admin') {
        // Admin stats
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes");
        $totalQuizzes = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes WHERE status = 'draft'");
        $pendingReviews = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
        $totalDepartments = $stmt->fetch()['total'];
        
        // User growth (last 30 days)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $newUsers = $stmt->fetch()['total'];
        
        // Recent quizzes
        $stmt = $pdo->query("SELECT q.*, u.username as creator_name FROM quizzes q JOIN users u ON q.creator_id = u.id ORDER BY q.created_at DESC LIMIT 6");
        $recentQuizzes = $stmt->fetchAll();
        
        // Department distribution
        $stmt = $pdo->query("SELECT d.name, COUNT(u.id) as user_count FROM departments d LEFT JOIN users u ON d.id = u.department_id GROUP BY d.id");
        $departmentStats = $stmt->fetchAll();
        
    } else {
        // Teacher stats
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM quizzes WHERE creator_id = ?");
        $stmt->execute([$userId]);
        $myQuizzes = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attempts a JOIN quizzes q ON a.quiz_id = q.id WHERE q.creator_id = ?");
        $stmt->execute([$userId]);
        $totalAttempts = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COALESCE(AVG(score), 0) as avg FROM attempts a JOIN quizzes q ON a.quiz_id = q.id WHERE q.creator_id = ?");
        $stmt->execute([$userId]);
        $avgScore = round($stmt->fetch()['avg'], 1);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM quizzes WHERE creator_id = ? AND status = 'draft'");
        $stmt->execute([$userId]);
        $draftQuizzes = $stmt->fetch()['total'];
        
        // Recent quiz attempts
        $stmt = $pdo->prepare("
            SELECT a.*, q.title as quiz_title, u.username as student_name 
            FROM attempts a 
            JOIN quizzes q ON a.quiz_id = q.id 
            JOIN users u ON a.user_id = u.id 
            WHERE q.creator_id = ? 
            ORDER BY a.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $recentAttempts = $stmt->fetchAll();
        
        // Quiz performance stats
        $stmt = $pdo->prepare("
            SELECT 
                q.id,
                q.title,
                COUNT(DISTINCT a.id) as attempt_count,
                COALESCE(AVG(a.score), 0) as avg_score,
                COUNT(DISTINCT q2.id) as question_count
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id
            LEFT JOIN questions q2 ON q.id = q2.quiz_id
            WHERE q.creator_id = ?
            GROUP BY q.id
            ORDER BY attempt_count DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $topQuizzes = $stmt->fetchAll();
        
        // Weekly activity
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT q.id) as new_quizzes,
                COUNT(DISTINCT a.id) as new_attempts
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id
            WHERE q.creator_id = ? AND q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $weeklyActivity = $stmt->fetch();
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats fetch failed: " . $e->getMessage());
}

// -----------------------
// FETCH NOTIFICATIONS
// -----------------------
try {
    if ($userRole === 'admin') {
        $stmt = $pdo->query("
            (SELECT 'quiz_review' as type, CONCAT('New quiz \"', title, '\" needs review') as message, created_at 
             FROM quizzes WHERE status = 'draft' ORDER BY created_at DESC LIMIT 3)
            UNION
            (SELECT 'new_user' as type, CONCAT('New user registered: ', username) as message, created_at 
             FROM users ORDER BY created_at DESC LIMIT 3)
            ORDER BY created_at DESC LIMIT 5
        ");
    } else {
        $stmt = $pdo->prepare("
            (SELECT 'attempt' as type, CONCAT(u.username, ' completed \"', q.title, '\"') as message, a.created_at 
             FROM attempts a
             JOIN users u ON a.user_id = u.id
             JOIN quizzes q ON a.quiz_id = q.id
             WHERE q.creator_id = ?
             ORDER BY a.created_at DESC LIMIT 3)
            UNION
            (SELECT 'draft' as type, CONCAT('Quiz \"', title, '\" is still in draft') as message, updated_at 
             FROM quizzes 
             WHERE creator_id = ? AND status = 'draft'
             ORDER BY updated_at DESC LIMIT 2)
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$userId, $userId]);
    }
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $dashboardTitle ?> - Dashboard</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Dashboard CSS -->
    <link rel="stylesheet" href="../../css/dashboard.css">

    <!-- Chart.js for beautiful charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-graduation-cap me-2"></i>EduQuiz</h4>
        <small><?= $dashboardTitle ?></small>
    </div>

    <a href="dashboard.php" class="nav-link active">
        <i class="fas fa-home"></i> Dashboard
    </a>

    <a href="quizzes.php" class="nav-link">
        <i class="fas fa-question-circle"></i> All Quizzes
    </a>

    <?php if ($userRole === 'admin'): ?>
        <a href="users.php" class="nav-link">
            <i class="fas fa-users-cog"></i> User Management
        </a>
        <a href="departments.php" class="nav-link">
            <i class="fas fa-school"></i> Departments
        </a>
    <?php endif; ?>

    <?php if ($userRole === 'teacher'): ?>
        <a href="create_quiz.php" class="nav-link">
            <i class="fas fa-plus-circle"></i> Create Quiz
        </a>
        <a href="myquizzes.php" class="nav-link">
            <i class="fas fa-puzzle-piece"></i> My Quizzes
        </a>
    <?php endif; ?>
    
    <a href="reports.php" class="nav-link">
        <i class="fas fa-chart-bar"></i> Reports
    </a>
    
    <a href="settings.php" class="nav-link">
        <i class="fas fa-cog"></i> Settings
    </a>
    
    <a href="?logout=1" class="nav-link logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <span class="menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </span>
        <span class="fw-bold"><?= $dashboardTitle ?></span>
        <div class="d-flex align-items-center">
            <span class="badge badge-primary me-3">
                <i class="fas fa-user-circle me-1"></i>
                <?= ucfirst($userRole) ?>
            </span>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="welcome-card animate-slide-in">
        <div class="row align-items-center">
            <div class="col">
                <h1>
                    <i class="fas fa-hand-wave me-2"></i>
                    Welcome back, <?= htmlspecialchars($username) ?>!
                </h1>
                <p>Here's what's happening with your quizzes today.</p>
            </div>
            <div class="col-auto">
                <i class="fas fa-chart-line fa-4x opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <?php if ($userRole === 'admin'): ?>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <?= round(($newUsers / $totalUsers) * 100) ?>% this month
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-puzzle-piece"></i>
                </div>
                <div class="stat-value"><?= number_format($totalQuizzes) ?></div>
                <div class="stat-label">Total Quizzes</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    +12% vs last month
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $pendingReviews ?></div>
                <div class="stat-label">Pending Reviews</div>
                <div class="stat-trend down">
                    <i class="fas fa-exclamation-circle"></i>
                    Needs attention
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?= $totalDepartments ?></div>
                <div class="stat-label">Departments</div>
                <div class="stat-trend">
                    <i class="fas fa-circle"></i>
                    Active
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-puzzle-piece"></i>
                </div>
                <div class="stat-value"><?= $myQuizzes ?></div>
                <div class="stat-label">My Quizzes</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    +<?= $weeklyActivity['new_quizzes'] ?? 0 ?> this week
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= number_format($totalAttempts) ?></div>
                <div class="stat-label">Total Attempts</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    +<?= $weeklyActivity['new_attempts'] ?? 0 ?> this week
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?= $avgScore ?>%</div>
                <div class="stat-label">Avg. Score</div>
                <div class="stat-trend <?= $avgScore >= 70 ? 'up' : 'down' ?>">
                    <i class="fas fa-chart-line"></i>
                    <?= $avgScore >= 70 ? 'Above target' : 'Below target' ?>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $draftQuizzes ?></div>
                <div class="stat-label">Draft Quizzes</div>
                <div class="stat-trend">
                    <i class="fas fa-pen"></i>
                    Ready to publish
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Performance Chart -->
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>

            <!-- Recent Activity -->
            <?php if ($userRole === 'teacher' && !empty($recentAttempts)): ?>
            <div class="activity-card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent Quiz Attempts
                </div>
                <div class="card-body">
                    <?php foreach ($recentAttempts as $attempt): ?>
                    <div class="list-item">
                        <div class="list-item-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title"><?= htmlspecialchars($attempt['student_name']) ?></div>
                            <div class="list-item-subtitle">
                                Completed "<?= htmlspecialchars($attempt['quiz_title']) ?>" 
                                with score <?= round($attempt['score']) ?>%
                            </div>
                        </div>
                        <div class="list-item-time">
                            <?= date('M d, H:i', strtotime($attempt['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'admin' && !empty($recentQuizzes)): ?>
            <div class="activity-card mt-4">
                <div class="card-header">
                    <i class="fas fa-clock"></i> Recently Created Quizzes
                </div>
                <div class="card-body">
                    <?php foreach ($recentQuizzes as $quiz): ?>
                    <div class="list-item">
                        <div class="list-item-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title"><?= htmlspecialchars($quiz['title']) ?></div>
                            <div class="list-item-subtitle">
                                By <?= htmlspecialchars($quiz['creator_name']) ?> • 
                                <span class="badge badge-<?= $quiz['status'] ?>"><?= ucfirst($quiz['status']) ?></span>
                            </div>
                        </div>
                        <div class="list-item-time">
                            <?= date('M d', strtotime($quiz['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="activity-card mb-4">
                <div class="card-header">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <?php if ($userRole === 'teacher'): ?>
                        <a href="create_quiz.php" class="quick-action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>New Quiz</span>
                        </a>
                        <?php endif; ?>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                        <a href="quizzes.php" class="quick-action-btn">
                            <i class="fas fa-search"></i>
                            <span>Browse</span>
                        </a>
                        <a href="settings.php" class="quick-action-btn">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="activity-card">
                <div class="card-header">
                    <i class="fas fa-bell"></i> Notifications
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                        <div class="notification-item">
                            <div class="notification-icon <?= $note['type'] ?>">
                                <i class="fas fa-<?= 
                                    $note['type'] == 'quiz_review' ? 'file-alt' : 
                                    ($note['type'] == 'new_user' ? 'user-plus' : 
                                    ($note['type'] == 'attempt' ? 'check-circle' : 'clock'))
                                ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message"><?= htmlspecialchars($note['message']) ?></div>
                                <div class="notification-time">
                                    <?= date('M d, H:i', strtotime($note['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <p>No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Performing Quizzes (for teachers) -->
            <?php if ($userRole === 'teacher' && !empty($topQuizzes)): ?>
            <div class="activity-card mt-4">
                <div class="card-header">
                    <i class="fas fa-trophy"></i> Top Performing Quizzes
                </div>
                <div class="card-body">
                    <?php foreach ($topQuizzes as $quiz): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold"><?= htmlspecialchars($quiz['title']) ?></span>
                            <span class="text-muted"><?= $quiz['attempt_count'] ?> attempts</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-success" style="width: <?= $quiz['avg_score'] ?>%"></div>
                        </div>
                        <small class="text-muted">Avg. Score: <?= round($quiz['avg_score']) ?>%</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.querySelector('.menu-btn');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !menuBtn.contains(event.target) && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    }
});

// Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    <?php if ($userRole === 'teacher'): ?>
    // Teacher chart data
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Quiz Attempts',
                data: [12, 19, 15, 17],
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Average Score',
                data: [65, 72, 68, 75],
                borderColor: '#4cc9f0',
                backgroundColor: 'rgba(76, 201, 240, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Weekly Performance Trends'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php else: ?>
    // Admin chart data
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($departmentStats, 'name')) ?>,
            datasets: [{
                label: 'Users per Department',
                data: <?= json_encode(array_column($departmentStats, 'user_count')) ?>,
                backgroundColor: '#4361ee',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Department Distribution'
                }
            }
        }
    });
    <?php endif; ?>
});

// Add smooth hover effects
document.querySelectorAll('.stat-card, .activity-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>