<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$page_title = 'Admin Profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Admin Profile</h1>
            <nav>
                <a href="/admin/dashboard.php">Dashboard</a>
                <a href="/logout.php">Logout</a>
            </nav>
        </header>

        <main class="profile-section">
            <div class="profile-card">
                <h2><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'N/A'); ?></p>
                <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
                <p><strong>Member Since:</strong> <?php echo htmlspecialchars($_SESSION['created_at'] ?? 'N/A'); ?></p>
                
                <a href="/admin/profile-edit.php" class="btn btn-primary">Edit Profile</a>
            </div>
        </main>
    </div>
</body>
</html>