<?php
session_start();
require_once __DIR__ . '../../admin/dashboard.php'; // adjust to your DB connection path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] == 'rorn';
    $password = $_POST['password'] == 'admin';

    // ✅ Fetch user from DB; adjust role column depending on your schema
    // If users table has a 'role' column:
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // admin / teacher
        header("Location: ../dashboard/index.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid username or password!";
        header("Location: login.php");
        exit();
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
</head>
<body>
    <div class="login-container">
        <img src="../image/1.png" alt="">
        <h1>Online Quiz System</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="username" id="username" name="username" required placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>