<?php
session_start();
require_once __DIR__ . '../../../../config/database.php'; // Fixed path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fixed: Don't assign boolean values directly
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
    $stmt = $pdo->prepare("
        SELECT id, username, password_hash, role_id 
        FROM users 
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role_id'];   // ✅ FIXED

        // Redirect based on role_id
        if ($user['role_id'] == 1) {   // 1 = admin
            header("Location: ../admin/dashboard.php");
        } else {                       // 2 = teacher
            header("Location: ../teacher/dashboard.php");
        }
        exit();
    } else {
        $_SESSION['error'] = "Invalid username or password!";
        header("Location: login.php");
        exit();
    }
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Online Quiz System</title>
    <link rel="stylesheet" href="../../css/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <img src="../image/1.png" alt="Quiz System Logo" style="max-width: 150px; margin-bottom: 20px;">
        <h1>Online Quiz System</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group mb-3">
                <label for="username">Username</label>
                <input type="username" id="username" name="username" class="form-control" required placeholder="Enter your username">
            </div>
            <div class="form-group mb-3">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="mt-3 text-center">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html>