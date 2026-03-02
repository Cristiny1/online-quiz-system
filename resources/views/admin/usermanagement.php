<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /login.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'quizsystem');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Generate CSRF token for the session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$error   = '';

// Handle delete user (POST only, with CSRF check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $user_id = (int)$_POST['delete_id'];
        // Prevent deleting your own account
        if ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
            $error = 'You cannot delete your own account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $message = 'User deleted successfully.';
        }
    }
}

// Fetch all users (using prepared statement)
$result = $conn->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .delete-btn { color: red; background: none; border: none; cursor: pointer; font-size: 1rem; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-danger  { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>User Management</h1>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <form method="POST" action=""
                              onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
<?php $conn->close(); ?>