<?php
// Enable error reporting (dev only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// -----------------------
// DATABASE CONNECTION
// -----------------------
require_once __DIR__ . '../../../../config/database.php'; // Adjust if dashboard is in dashboard/

// -----------------------
// LOGOUT HANDLING
// -----------------------
if (isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = [];

    // Destroy the session cookie if exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Redirect to login page
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
$userRole = $_SESSION['role'] ?? 'teacher';
$username = $_SESSION['username'] ?? 'User';

// -----------------------
// PAGE TITLE
// -----------------------
$dashboardTitle = $userRole === 'admin' ? 'Admin Dashboard' : 'Teacher Dashboard';

// -----------------------
// FETCH DASHBOARD STATS
// -----------------------
try {
    if ($userRole === 'admin') {
        // Admin Stats
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalQuizzes = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
        $pendingReviews = $pdo->query("SELECT COUNT(*) FROM quizzes WHERE status='draft'")->fetchColumn();
        $totalDepartments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

        $newUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '30 days'")->fetchColumn();

        $recentQuizzes = $pdo->query("
            SELECT q.*, u.username AS creator_name 
            FROM quizzes q 
            JOIN users u ON q.creator_id = u.id 
            ORDER BY q.created_at DESC 
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        $departmentStats = $pdo->query("
            SELECT d.name, COUNT(u.id) AS user_count 
            FROM departments d 
            LEFT JOIN users u ON d.id = u.department_id 
            GROUP BY d.id
        ")->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Teacher Stats
        $myQuizzes = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE creator_id = ?");
        $myQuizzes->execute([$userId]);
        $myQuizzes = $myQuizzes->fetchColumn();

        $totalAttempts = $pdo->prepare("
            SELECT COUNT(*) 
            FROM attempts a 
            JOIN quizzes q ON a.quiz_id = q.id 
            WHERE q.creator_id = ?
        ");
        $totalAttempts->execute([$userId]);
        $totalAttempts = $totalAttempts->fetchColumn();

        $avgScore = $pdo->prepare("
            SELECT COALESCE(AVG(score),0) FROM attempts a
            JOIN quizzes q ON a.quiz_id = q.id 
            WHERE q.creator_id = ?
        ");
        $avgScore->execute([$userId]);
        $avgScore = round($avgScore->fetchColumn(),1);

        $draftQuizzes = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE creator_id=? AND status='draft'");
        $draftQuizzes->execute([$userId]);
        $draftQuizzes = $draftQuizzes->fetchColumn();

        // Weekly Activity
        $weeklyActivity = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT q.id) AS new_quizzes,
                COUNT(DISTINCT a.id) AS new_attempts
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id
            WHERE q.creator_id=? AND q.created_at >= NOW() - INTERVAL '7 days'
        ");
        $weeklyActivity->execute([$userId]);
        $weeklyActivity = $weeklyActivity->fetch(PDO::FETCH_ASSOC);

        // Recent Attempts
        $recentAttempts = $pdo->prepare("
            SELECT a.*, q.title AS quiz_title, u.username AS student_name
            FROM attempts a
            JOIN quizzes q ON a.quiz_id = q.id
            JOIN users u ON a.user_id = u.id
            WHERE q.creator_id = ?
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $recentAttempts->execute([$userId]);
        $recentAttempts = $recentAttempts->fetchAll(PDO::FETCH_ASSOC);

        // Top Quizzes
        $topQuizzes = $pdo->prepare("
            SELECT q.id, q.title, COUNT(a.id) AS attempt_count, COALESCE(AVG(a.score),0) AS avg_score
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id
            WHERE q.creator_id=?
            GROUP BY q.id
            ORDER BY attempt_count DESC
            LIMIT 5
        ");
        $topQuizzes->execute([$userId]);
        $topQuizzes = $topQuizzes->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Dashboard stats fetch failed: " . $e->getMessage());
    if ($userRole==='admin'){
        $totalUsers=$totalQuizzes=$pendingReviews=$totalDepartments=$newUsers=0;
        $recentQuizzes=$departmentStats=[];
    } else {
        $myQuizzes=$totalAttempts=$avgScore=$draftQuizzes=0;
        $weeklyActivity=['new_quizzes'=>0,'new_attempts'=>0];
        $recentAttempts=$topQuizzes=[];
    }
}

// -----------------------
// FETCH NOTIFICATIONS
// -----------------------
try {
    if ($userRole==='admin') {
        $notifications = $pdo->query("
            (SELECT 'quiz_review' AS type, 'New quiz \"' || title || '\" needs review' AS message, created_at 
             FROM quizzes WHERE status='draft' ORDER BY created_at DESC LIMIT 3)
            UNION ALL
            (SELECT 'new_user' AS type, 'New user registered: ' || username AS message, created_at
             FROM users ORDER BY created_at DESC LIMIT 3)
            ORDER BY created_at DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            (SELECT 'attempt' AS type, u.username || ' completed \"' || q.title || '\"' AS message, a.created_at
             FROM attempts a
             JOIN users u ON a.user_id = u.id
             JOIN quizzes q ON a.quiz_id = q.id
             WHERE q.creator_id = ?
             ORDER BY a.created_at DESC LIMIT 3)
            UNION ALL
            (SELECT 'draft' AS type, 'Quiz \"' || title || '\" is still in draft' AS message, updated_at
             FROM quizzes WHERE creator_id=? AND status='draft'
             ORDER BY updated_at DESC LIMIT 2)
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$userId,$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dashboardTitle) ?> - Dashboard</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Dashboard CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            transition: 0.3s;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .sidebar-header small {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: 0.3s;
            border-left: 4px solid transparent;
        }

        .nav-link i {
            width: 25px;
            font-size: 1.2rem;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #fff;
        }

        .nav-link.logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .nav-link.logout:hover {
            background: rgba(255,0,0,0.2);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px 30px;
            transition: 0.3s;
        }

        /* Top Navbar */
        .top-navbar {
            display: none;
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-radius: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .menu-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #667eea;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .stat-icon.primary { background: #e8f0fe; color: #4361ee; }
        .stat-icon.success { background: #e3fcef; color: #2ecc71; }
        .stat-icon.warning { background: #fff3e0; color: #f39c12; }
        .stat-icon.info { background: #e1f5fe; color: #00acc1; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-trend {
            margin-top: 15px;
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .stat-trend.up { background: #e3fcef; color: #2ecc71; }
        .stat-trend.down { background: #fee; color: #e74c3c; }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 350px;
        }

        /* Activity Card */
        .activity-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: #667eea;
        }

        .card-body {
            padding: 0;
        }

        .list-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: 0.3s;
        }

        .list-item:hover {
            background: #f8f9fa;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }

        .list-item-content {
            flex: 1;
        }

        .list-item-title {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .list-item-subtitle {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .list-item-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
        }

        .quick-action-btn {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px 10px;
            text-align: center;
            color: #2c3e50;
            text-decoration: none;
            transition: 0.3s;
        }

        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }

        .quick-action-btn i {
            font-size: 1.8rem;
            margin-bottom: 8px;
            display: block;
        }

        .quick-action-btn span {
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Notifications */
        .notification-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: 0.3s;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .notification-icon.quiz_review { background: #f39c12; }
        .notification-icon.new_user { background: #2ecc71; }
        .notification-icon.attempt { background: #3498db; }
        .notification-icon.draft { background: #95a5a6; }

        .notification-content {
            flex: 1;
        }

        .notification-message {
            font-weight: 500;
            margin-bottom: 3px;
            font-size: 0.95rem;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .top-navbar {
                display: flex;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
        }

        /* Progress Bar */
        .progress {
            background: #f0f2f5;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.6s ease;
        }

        /* Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* Animations */
        .animate-slide-in {
            animation: slideIn 0.5s ease;
        }

        /* Opacity Utilities */
        .opacity-50 {
            opacity: 0.5;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-graduation-cap me-2"></i>EduQuiz</h4>
        <small><?= htmlspecialchars($dashboardTitle) ?></small>
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
    
    <a href="../auth/login.php" class="nav-link logout">
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
        <span class="fw-bold"><?= htmlspecialchars($dashboardTitle) ?></span>
        <div class="d-flex align-items-center">
            <span class="badge bg-primary me-3">
                <i class="fas fa-user-circle me-1"></i>
                <?= ucfirst(htmlspecialchars($userRole)) ?>
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
                    <?= $totalUsers > 0 ? round(($newUsers / $totalUsers) * 100) : 0 ?>% this month
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
            <div class="activity-card mt-4">
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
                                <span class="badge bg-<?= $quiz['status'] == 'published' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($quiz['status']) ?>
                                </span>
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
                            <div class="notification-icon <?= htmlspecialchars($note['type']) ?>">
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
                <div class="card-body p-3">
                    <?php foreach ($topQuizzes as $quiz): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold"><?= htmlspecialchars($quiz['title']) ?></span>
                            <span class="text-muted"><?= $quiz['attempt_count'] ?> attempts</span>
                        </div>
                        <div class="progress" style="height: 8px;">
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
            maintainAspectRatio: false,
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
            maintainAspectRatio: false,
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

</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html> 