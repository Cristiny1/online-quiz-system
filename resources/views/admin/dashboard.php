<?php
// Enable error reporting (dev only)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// -----------------------
// DATABASE CONNECTION
// -----------------------
require_once __DIR__ . '/../../../config/database.php';

// -----------------------
// LOGOUT HANDLING
// -----------------------
if (isset($_GET['logout'])) {
    session_regenerate_id(true);
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header('Location: login.php?message=logged_out');
    exit();
}

// -----------------------
// LOGIN CHECK & CSRF PROTECTION
// -----------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'teacher';
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';

// -----------------------
// PAGE TITLE
// -----------------------
$dashboardTitle = $userRole === 'admin' ? 'Admin' : 'Teacher Dashboard';

// -----------------------
// FETCH DASHBOARD STATS WITH ERROR HANDLING
// -----------------------
try {
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    if ($userRole === 'admin') {
        // Admin Stats
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
        $totalQuizzes = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn() ?: 0;
        $pendingReviews = $pdo->query("SELECT COUNT(*) FROM quizzes WHERE status='draft'")->fetchColumn() ?: 0;
        $totalDepartments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn() ?: 0;

        $dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $intervalSql = $dbType === 'sqlite' 
            ? "datetime('now', '-30 days')" 
            : "NOW() - INTERVAL 30 DAY";
        
        $newUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= $intervalSql")->fetchColumn() ?: 0;

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
            ORDER BY user_count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentActivities = $pdo->query("
            (SELECT 'quiz' as type, 'New quiz created' as action, created_at 
             FROM quizzes ORDER BY created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'user' as type, 'New user registered' as action, created_at 
             FROM users ORDER BY created_at DESC LIMIT 5)
            ORDER BY created_at DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Teacher Stats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE creator_id = ?");
        $stmt->execute([$userId]);
        $myQuizzes = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.id) 
            FROM attempts a 
            JOIN quizzes q ON a.quiz_id = q.id 
            WHERE q.creator_id = ?
        ");
        $stmt->execute([$userId]);
        $totalAttempts = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("
            SELECT COALESCE(AVG(a.score), 0) 
            FROM attempts a
            JOIN quizzes q ON a.quiz_id = q.id 
            WHERE q.creator_id = ? AND a.score IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $avgScore = round($stmt->fetchColumn(), 1);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE creator_id = ? AND status = 'draft'");
        $stmt->execute([$userId]);
        $draftQuizzes = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE creator_id = ? AND status = 'published'");
        $stmt->execute([$userId]);
        $publishedQuizzes = $stmt->fetchColumn() ?: 0;

        $dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $intervalSql = $dbType === 'sqlite' 
            ? "datetime('now', '-7 days')" 
            : "NOW() - INTERVAL 7 DAY";
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT q.id) AS new_quizzes,
                COUNT(DISTINCT a.id) AS new_attempts
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id AND a.created_at >= $intervalSql
            WHERE q.creator_id = ? AND q.created_at >= $intervalSql
        ");
        $stmt->execute([$userId]);
        $weeklyActivity = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['new_quizzes' => 0, 'new_attempts' => 0];

        $stmt = $pdo->prepare("
            SELECT a.*, q.title AS quiz_title, u.username AS student_name, u.email AS student_email,
                   ROUND(a.score, 1) as rounded_score
            FROM attempts a
            JOIN quizzes q ON a.quiz_id = q.id
            JOIN users u ON a.user_id = u.id
            WHERE q.creator_id = ?
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT q.id, q.title, q.status,
                   COUNT(a.id) AS attempt_count, 
                   COALESCE(AVG(a.score), 0) AS avg_score,
                   MAX(a.score) AS max_score,
                   MIN(a.score) AS min_score,
                   COUNT(DISTINCT a.user_id) AS unique_students
            FROM quizzes q
            LEFT JOIN attempts a ON q.id = a.quiz_id
            WHERE q.creator_id = ?
            GROUP BY q.id
            HAVING attempt_count > 0
            ORDER BY attempt_count DESC, avg_score DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $topQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT id, title, status, created_at
            FROM quizzes
            WHERE creator_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $recentQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Dashboard stats fetch failed: " . $e->getMessage());
    if ($userRole === 'admin') {
        $totalUsers = $totalQuizzes = $pendingReviews = $totalDepartments = $newUsers = 0;
        $recentQuizzes = $departmentStats = $recentActivities = [];
    } else {
        $myQuizzes = $totalAttempts = $avgScore = $draftQuizzes = $publishedQuizzes = 0;
        $weeklyActivity = ['new_quizzes' => 0, 'new_attempts' => 0];
        $recentAttempts = $topQuizzes = $recentQuizzes = [];
    }
}

// -----------------------
// FETCH NOTIFICATIONS
// -----------------------
try {
    if ($userRole === 'admin') {
        $dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $notifications = $pdo->query("
            (SELECT 
                'quiz_review' AS type, 
                " . ($dbType === 'sqlite' ? "'New quiz \"' || title || '\" needs review'" : "CONCAT('New quiz \"', title, '\" needs review')") . " AS message, 
                created_at 
             FROM quizzes 
             WHERE status = 'draft' 
             ORDER BY created_at DESC 
             LIMIT 3)
            UNION ALL
            (SELECT 
                'new_user' AS type, 
                " . ($dbType === 'sqlite' ? "'New user registered: ' || username" : "CONCAT('New user registered: ', username)") . " AS message, 
                created_at
             FROM users 
             ORDER BY created_at DESC 
             LIMIT 3)
            ORDER BY created_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            (SELECT 
                'attempt' AS type, 
                CONCAT(u.username, ' completed \"', q.title, '\"') AS message, 
                a.created_at
             FROM attempts a
             JOIN users u ON a.user_id = u.id
             JOIN quizzes q ON a.quiz_id = q.id
             WHERE q.creator_id = ?
             ORDER BY a.created_at DESC 
             LIMIT 3)
            UNION ALL
            (SELECT 
                'draft' AS type, 
                CONCAT('Quiz \"', title, '\" is still in draft') AS message, 
                updated_at
             FROM quizzes 
             WHERE creator_id = ? AND status = 'draft'
             ORDER BY updated_at DESC 
             LIMIT 2)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$userId, $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Notification fetch failed: " . $e->getMessage());
    $notifications = [];
}

// Calculate completion rate
$completionRate = 0;
if ($userRole === 'teacher' && $totalAttempts > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM attempts 
            WHERE quiz_id IN (SELECT id FROM quizzes WHERE creator_id = ?)
            AND status = 'completed'
        ");
        $stmt->execute([$userId]);
        $completedAttempts = $stmt->fetchColumn() ?: 0;
        $completionRate = round(($completedAttempts / $totalAttempts) * 100, 1);
    } catch (Exception $e) {
        $completionRate = 0;
    }
}

// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dashboardTitle) ?> - Dashboard | QuizMaster</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1e1e2f;
            --light-color: #f8f9fa;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fb;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--dark-color) 0%, #2a2a40 100%);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }

        .sidebar-header small {
            color: rgba(255,255,255,0.7);
            font-size: 0.875rem;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-weight: 500;
        }

        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--success-color);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: var(--success-color);
        }

        .sidebar .nav-link.logout {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .nav-link.logout:hover {
            background: rgba(245, 37, 133, 0.2);
            border-left-color: var(--danger-color);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s;
            min-height: 100vh;
        }

        /* Top Navbar */
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .menu-btn {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--dark-color);
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            margin-top: 2rem;
        }

        .section-header h3 {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .section-header h3 i {
            color: var(--primary-color);
            margin-right: 0.75rem;
        }

        .view-all-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .view-all-link:hover {
            color: var(--secondary-color);
            transform: translateX(5px);
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: rgba(67, 97, 238, 0.1); color: var(--primary-color); }
        .stat-icon.success { background: rgba(76, 201, 240, 0.1); color: var(--success-color); }
        .stat-icon.warning { background: rgba(248, 150, 30, 0.1); color: var(--warning-color); }
        .stat-icon.info { background: rgba(63, 55, 201, 0.1); color: var(--secondary-color); }
        .stat-icon.danger { background: rgba(247, 37, 133, 0.1); color: var(--danger-color); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-trend {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-trend.up { color: #10b981; }
        .stat-trend.down { color: #ef4444; }

        /* Activity Card */
        .activity-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f2f5;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header i {
            color: var(--primary-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* List Items */
        .list-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .list-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            margin-right: 1rem;
        }

        .list-item-content {
            flex: 1;
        }

        .list-item-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .list-item-subtitle {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .list-item-time {
            font-size: 0.75rem;
            color: #adb5bd;
        }

        /* Quick Actions Grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .quick-action-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .quick-action-card:hover i {
            color: white;
        }

        .quick-action-card span {
            font-size: 1rem;
            font-weight: 600;
        }

        .quick-action-card small {
            display: block;
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .quick-action-card:hover small {
            color: rgba(255,255,255,0.9);
        }

        /* Navigation Cards */
        .nav-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .nav-card {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .nav-card:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }

        .nav-card i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .nav-card:hover i {
            color: white;
        }

        .nav-card span {
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 350px;
        }

        /* Progress Bar */
        .progress {
            background-color: #f0f2f5;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            transition: width 0.6s ease;
        }

        /* Badges */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            border-radius: 20px;
        }

        .badge.bg-success { background: #10b981 !important; }
        .badge.bg-warning { background: var(--warning-color) !important; }
        .badge.bg-info { background: var(--success-color) !important; }
        .badge.bg-primary { background: var(--primary-color) !important; }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-overlay.show {
            display: flex;
        }

        /* Notifications */
        .notification-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
        }

        .notification-icon.quiz_review { background: rgba(248, 150, 30, 0.1); color: var(--warning-color); }
        .notification-icon.new_user { background: rgba(76, 201, 240, 0.1); color: var(--success-color); }
        .notification-icon.attempt { background: rgba(67, 97, 238, 0.1); color: var(--primary-color); }
        .notification-icon.draft { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
                max-width: 300px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .menu-btn {
                display: inline-block;
            }

            .top-navbar {
                padding: 1rem;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .nav-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .nav-cards {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }
        }

        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<!-- Loading Spinner -->
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-graduation-cap me-2"></i>QuizMaster</h4>
        <small><?= htmlspecialchars($dashboardTitle) ?></small>
    </div>

    <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>

    <a href="quizzes.php" class="nav-link <?= $currentPage == 'quizzes.php' ? 'active' : '' ?>">
        <i class="fas fa-question-circle"></i> All Quizzes
    </a>

    <?php if ($userRole === 'admin'): ?>
        <a href="users.php" class="nav-link <?= $currentPage == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> User Management
        </a>
        <a href="departments.php" class="nav-link <?= $currentPage == 'departments.php' ? 'active' : '' ?>">
            <i class="fas fa-school"></i> Departments
        </a>
        <a href="settings.php" class="nav-link <?= $currentPage == 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> System Settings
        </a>
    <?php endif; ?>

    <?php if ($userRole === 'teacher'): ?>
        <a href="create-quiz.php" class="nav-link <?= $currentPage == 'create-quiz.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i> Create Quiz
        </a>
        <a href="my-quizzes.php" class="nav-link <?= $currentPage == 'my-quizzes.php' ? 'active' : '' ?>">
            <i class="fas fa-puzzle-piece"></i> My Quizzes
            <?php if (isset($draftQuizzes) && $draftQuizzes > 0): ?>
                <span class="badge bg-warning ms-2"><?= $draftQuizzes ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
    
    <a href="reports.php" class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> Analytics & Reports
    </a>
    
    <a href="profile.php" class="nav-link <?= $currentPage == 'profile.php' ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i> My Profile
    </a>
    
    <a href="settings.php" class="nav-link <?= $currentPage == 'settings.php' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Settings
    </a>
    
    <a href="?logout=1" class="nav-link logout" onclick="return confirm('Are you sure you want to logout?');">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar animate-slide-up">
        <span class="menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </span>
        <span class="fw-bold"><?= htmlspecialchars($dashboardTitle) ?></span>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary">
                <i class="fas fa-user-circle me-1"></i>
                <?= ucfirst(htmlspecialchars($userRole)) ?>
            </span>
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell fs-5"></i>
                    <?php if (!empty($notifications)): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= count($notifications) ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach (array_slice($notifications, 0, 3) as $note): ?>
                            <li><a class="dropdown-item" href="#"><?= htmlspecialchars($note['message']) ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">View All</a></li>
                    <?php else: ?>
                        <li><span class="dropdown-item text-muted">No notifications</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="welcome-card animate-slide-in">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="display-6 fw-bold mb-3">
                    <i class="fas fa-hand-wave me-2"></i>
                    Welcome back, <?= htmlspecialchars(explode(' ', $username)[0]) ?>!
                </h1>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?= date('l, F j, Y') ?> • 
                    <i class="fas fa-clock ms-2 me-1"></i>
                    <?= date('g:i A') ?>
                </p>
            </div>
            <div class="col-auto">
                <i class="fas fa-chart-line fa-4x opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Cards -->
    <div class="nav-cards">
        <a href="create-quiz.php" class="nav-card">
            <i class="fas fa-plus-circle"></i>
            <span>Create Quiz</span>
        </a>
        <a href="quizzes.php" class="nav-card">
            <i class="fas fa-search"></i>
            <span>Browse Quizzes</span>
        </a>
        <a href="my-quizzes.php" class="nav-card">
            <i class="fas fa-list"></i>
            <span>My Quizzes</span>
        </a>
        <a href="reports.php" class="nav-card">
            <i class="fas fa-chart-bar"></i>
            <span>Analytics</span>
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <?php if ($userRole === 'admin'): ?>
        <div class="col-md-3 col-sm-6">
            <a href="users.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= number_format($totalUsers) ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <?= $totalUsers > 0 ? round(($newUsers / $totalUsers) * 100, 1) : 0 ?>% this month
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="quizzes.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s">
                    <div class="stat-icon success">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="stat-value"><?= number_format($totalQuizzes) ?></div>
                    <div class="stat-label">Total Quizzes</div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        System wide
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="reports.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= $pendingReviews ?></div>
                    <div class="stat-label">Pending Reviews</div>
                    <div class="stat-trend <?= $pendingReviews > 0 ? 'down' : 'up' ?>">
                        <i class="fas fa-<?= $pendingReviews > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i>
                        <?= $pendingReviews > 0 ? 'Needs attention' : 'All clear' ?>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="departments.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s">
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
            </a>
        </div>
        <?php else: ?>
        <div class="col-md-3 col-sm-6">
            <a href="my-quizzes.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in">
                    <div class="stat-icon primary">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="stat-value"><?= $myQuizzes ?></div>
                    <div class="stat-label">My Quizzes</div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <?= $publishedQuizzes ?> published, <?= $draftQuizzes ?> draft
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="reports.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s">
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
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="reports.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s">
                    <div class="stat-icon info">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?= $avgScore ?>%</div>
                    <div class="stat-label">Avg. Score</div>
                    <div class="stat-trend <?= $avgScore >= 70 ? 'up' : 'down' ?>">
                        <i class="fas fa-chart-line"></i>
                        <?= $avgScore >= 70 ? 'Above target' : 'Needs improvement' ?>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="my-quizzes.php" class="text-decoration-none">
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= $completionRate ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                    <div class="stat-trend <?= $completionRate >= 80 ? 'up' : 'down' ?>">
                        <i class="fas fa-check-circle"></i>
                        <?= $completionRate >= 80 ? 'Excellent' : 'Room for growth' ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions Section -->
    <div class="section-header">
        <h3>
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h3>
    </div>
    
    <div class="quick-actions-grid">
        <a href="create-quiz.php" class="quick-action-card">
            <i class="fas fa-plus-circle"></i>
            <span>Create Quiz</span>
            <small>Start a new quiz</small>
        </a>
        <a href="import-questions.php" class="quick-action-card">
            <i class="fas fa-file-import"></i>
            <span>Import Questions</span>
            <small>Bulk upload</small>
        </a>
        <a href="reports.php" class="quick-action-card">
            <i class="fas fa-chart-pie"></i>
            <span>View Reports</span>
            <small>Analytics</small>
        </a>
        <a href="my-quizzes.php" class="quick-action-card">
            <i class="fas fa-edit"></i>
            <span>Edit Quizzes</span>
            <small>Manage content</small>
        </a>
        <a href="students.php" class="quick-action-card">
            <i class="fas fa-user-graduate"></i>
            <span>Students</span>
            <small>View progress</small>
        </a>
        <a href="settings.php" class="quick-action-card">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
            <small>Configure</small>
        </a>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Performance Chart Section -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-chart-line"></i>
                    Performance Overview
                </h3>
                <a href="reports.php" class="view-all-link">
                    View Detailed Reports <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            
            <div class="chart-container mb-4 animate-slide-up">
                <canvas id="performanceChart"></canvas>
            </div>

            <!-- Recent Activity Section -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-history"></i>
                    Recent Activity
                </h3>
                <?php if ($userRole === 'teacher'): ?>
                    <a href="attempts.php" class="view-all-link">
                        View All Attempts <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                <?php else: ?>
                    <a href="recent-activity.php" class="view-all-link">
                        View All <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($userRole === 'teacher' && !empty($recentAttempts)): ?>
            <div class="activity-card animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent Quiz Attempts
                    <span class="ms-auto badge bg-primary"><?= count($recentAttempts) ?> recent</span>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($recentAttempts as $attempt): ?>
                    <div class="list-item">
                        <div class="list-item-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title">
                                <?= htmlspecialchars($attempt['student_name']) ?>
                                <small class="text-muted ms-2">(<?= htmlspecialchars($attempt['student_email']) ?>)</small>
                            </div>
                            <div class="list-item-subtitle">
                                Completed "<?= htmlspecialchars($attempt['quiz_title']) ?>" 
                                with score <strong class="<?= $attempt['rounded_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                    <?= $attempt['rounded_score'] ?>%
                                </strong>
                            </div>
                        </div>
                        <div class="list-item-time">
                            <?= date('M d, H:i', strtotime($attempt['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-transparent border-0 text-center py-3">
                    <a href="attempts.php" class="text-decoration-none">View All Attempts <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'admin' && !empty($recentQuizzes)): ?>
            <div class="activity-card animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-clock"></i> Recently Created Quizzes
                </div>
                <div class="card-body p-0">
                    <?php foreach ($recentQuizzes as $quiz): ?>
                    <div class="list-item">
                        <div class="list-item-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title">
                                <a href="view-quiz.php?id=<?= $quiz['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($quiz['title']) ?>
                                </a>
                            </div>
                            <div class="list-item-subtitle">
                                By <?= htmlspecialchars($quiz['creator_name']) ?> • 
                                <span class="badge bg-<?= $quiz['status'] == 'published' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($quiz['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="list-item-time">
                            <?= date('M d, Y', strtotime($quiz['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-transparent border-0 text-center py-3">
                    <a href="quizzes.php" class="text-decoration-none">View All Quizzes <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'teacher' && !empty($recentQuizzes)): ?>
            <div class="activity-card mt-4 animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-pen"></i> Your Recent Quizzes
                </div>
                <div class="card-body p-0">
                    <?php foreach ($recentQuizzes as $quiz): ?>
                    <div class="list-item">
                        <div class="list-item-icon">
                            <i class="fas fa-puzzle-piece"></i>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title">
                                <a href="view-quiz.php?id=<?= $quiz['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($quiz['title']) ?>
                                </a>
                            </div>
                            <div class="list-item-subtitle">
                                <span class="badge bg-<?= $quiz['status'] == 'published' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($quiz['status']) ?>
                                </span>
                                • Created <?= date('M d', strtotime($quiz['created_at'])) ?>
                            </div>
                        </div>
                        <div class="list-item-time">
                            <a href="edit-quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-transparent border-0 text-center py-3">
                    <a href="my-quizzes.php" class="text-decoration-none">View All My Quizzes <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Notifications Section -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-bell"></i>
                    Notifications
                </h3>
                <a href="notifications.php" class="view-all-link">
                    View All <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="activity-card mb-4 animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-bell"></i> Recent Notifications
                    <?php if (!empty($notifications)): ?>
                        <span class="ms-auto badge bg-danger"><?= count($notifications) ?> new</span>
                    <?php endif; ?>
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
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('M d, H:i', strtotime($note['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-bell-slash fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No new notifications</p>
                            <small>You're all caught up!</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Performing Quizzes Section -->
            <?php if ($userRole === 'teacher' && !empty($topQuizzes)): ?>
            <div class="section-header">
                <h3>
                    <i class="fas fa-trophy"></i>
                    Top Quizzes
                </h3>
                <a href="reports.php" class="view-all-link">
                    View Reports <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="activity-card animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-trophy text-warning"></i> Top Performing Quizzes
                </div>
                <div class="card-body p-3">
                    <?php foreach ($topQuizzes as $index => $quiz): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="fw-bold"><?= $index + 1 ?>. </span>
                                <span class="fw-bold">
                                    <a href="view-quiz.php?id=<?= $quiz['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($quiz['title']) ?>
                                    </a>
                                </span>
                                <?php if ($quiz['status'] == 'published'): ?>
                                    <i class="fas fa-check-circle text-success ms-1" title="Published"></i>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary"><?= $quiz['attempt_count'] ?> attempts</span>
                        </div>
                        <div class="progress mb-1" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= $quiz['avg_score'] ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">
                                <i class="fas fa-users me-1"></i><?= $quiz['unique_students'] ?> students
                            </span>
                            <span class="<?= $quiz['avg_score'] >= 60 ? 'text-success' : 'text-danger' ?>">
                                <i class="fas fa-star me-1"></i><?= round($quiz['avg_score'], 1) ?>% avg
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Department Stats Section for Admin -->
            <?php if ($userRole === 'admin' && !empty($departmentStats)): ?>
            <div class="section-header">
                <h3>
                    <i class="fas fa-building"></i>
                    Departments
                </h3>
                <a href="departments.php" class="view-all-link">
                    Manage <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="activity-card animate-slide-up">
                <div class="card-header">
                    <i class="fas fa-building"></i> Department Distribution
                </div>
                <div class="card-body">
                    <?php foreach ($departmentStats as $dept): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold"><?= htmlspecialchars($dept['name']) ?></span>
                            <span class="text-muted"><?= $dept['user_count'] ?> users</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <?php 
                            $percentage = $totalUsers > 0 ? round(($dept['user_count'] / $totalUsers) * 100) : 0;
                            ?>
                            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-transparent border-0 text-center py-3">
                    <a href="departments.php" class="text-decoration-none">Manage Departments <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help & Support Section -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-question-circle"></i>
                    Help & Support
                </h3>
            </div>

            <div class="activity-card animate-slide-up">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="help.php" class="btn btn-outline-primary">
                            <i class="fas fa-question-circle me-2"></i>Help Center
                        </a>
                        <a href="faq.php" class="btn btn-outline-primary">
                            <i class="fas fa-question me-2"></i>FAQ
                        </a>
                        <a href="contact.php" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i>Contact Support
                        </a>
                        <a href="tutorials.php" class="btn btn-outline-primary">
                            <i class="fas fa-video me-2"></i>Video Tutorials
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle sidebar
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

// Show loading spinner on page navigation
document.addEventListener('DOMContentLoaded', function() {
    const spinner = document.getElementById('loadingSpinner');
    
    // Hide spinner after page loads
    if (spinner) {
        setTimeout(() => {
            spinner.classList.remove('show');
        }, 500);
    }
    
    // Show spinner on link clicks
    document.querySelectorAll('a:not([href^="#"])').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.classList.contains('logout') && !this.target) {
                spinner.classList.add('show');
            }
        });
    });
});

// Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    <?php if ($userRole === 'teacher'): ?>
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Quiz Attempts',
                data: [12, 19, 15, <?= $weeklyActivity['new_attempts'] ?? 17 ?>],
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }, {
                label: 'Average Score',
                data: [65, 72, 68, <?= $avgScore ?? 75 ?>],
                borderColor: '#4cc9f0',
                backgroundColor: 'rgba(76, 201, 240, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#4cc9f0',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Weekly Performance Trends',
                    font: {
                        size: 16,
                        weight: '600'
                    },
                    padding: 20
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(30, 30, 47, 0.9)',
                    titleFont: { size: 14, weight: '600' },
                    bodyFont: { size: 13 },
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return value + (this.chart.data.datasets[0].label.includes('Score') ? '%' : '');
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    <?php else: ?>
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($departmentStats, 'name') ?: ['No Data']) ?>,
            datasets: [{
                label: 'Users per Department',
                data: <?= json_encode(array_column($departmentStats, 'user_count') ?: [0]) ?>,
                backgroundColor: [
                    '#4361ee',
                    '#4cc9f0',
                    '#f72585',
                    '#f8961e',
                    '#3f37c9',
                    '#4895ef'
                ],
                borderRadius: 8,
                barPercentage: 0.7,
                categoryPercentage: 0.8
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
                    text: 'Department Distribution',
                    font: {
                        size: 16,
                        weight: '600'
                    },
                    padding: 20
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 30, 47, 0.9)',
                    titleFont: { size: 14, weight: '600' },
                    bodyFont: { size: 13 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} users (${((context.raw / <?= $totalUsers ?: 1 ?>)*100).toFixed(1)}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    <?php endif; ?>
});

// Auto-refresh notifications (every 30 seconds)
setInterval(function() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                const badge = document.querySelector('.fa-bell + .badge');
                if (badge) {
                    badge.textContent = data.count;
                }
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'create-quiz.php';
    }
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        window.location.href = 'dashboard.php';
    }
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        window.location.href = 'reports.php';
    }
    if (e.ctrlKey && e.key === 'q') {
        e.preventDefault();
        window.location.href = 'quizzes.php';
    }
    if (e.ctrlKey && e.key === 'm') {
        e.preventDefault();
        window.location.href = 'my-quizzes.php';
    }
});

console.log('Dashboard loaded successfully for <?= htmlspecialchars($username) ?>');
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Toast Container for Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

</body>
</html>