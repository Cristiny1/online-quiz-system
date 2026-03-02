<?php
session_start();
require_once __DIR__ . '../../../../config/database.php'; // Make sure path is correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fixed: Don't assign boolean values directly
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, role_id 
            FROM users 
            WHERE username = :username
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];

            // Redirect based on role_id
            switch ($user['role_id']) {
                case 1:
                    header("Location: ../admin/dashboard.php");
                    break;
                case 2:
                    header("Location: ../teacher/dashboard.php");
                    break;
                case 3:
                    header("Location: ../student/dashboard.php");
                    break;
                default:
                    $_SESSION['error'] = "Role not recognized!";
                    header("Location: login.php");
                    break;
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
<body class="body">
<div class="login-container">
    <img src="../image/1.png" alt="Quiz System Logo" style="max-width: 150px; margin-bottom: 20px;">
    <h2 style="color: blue; text-align:center; font-weight:bold">Online Quiz System</h2>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="username" id="username" name="username" class="form-control" required placeholder="Enter your username">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
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