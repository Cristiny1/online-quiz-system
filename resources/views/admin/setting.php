<?php
// resources/views/admin/setting.php
session_start();

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Define role BEFORE using it
$userRole = $_SESSION['role'] ?? '';

// Only admins may access this settings page
if ($userRole !== 'admin') {
    // Teachers get redirected to their own settings (not yet implemented)
    if ($userRole === 'teacher') {
        header('Location: /teacher_settings.php');
    } else {
        header('Location: /login.php');
    }
    exit();
}

$message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: validate and persist settings to DB / config file
    $message = "Settings updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .settings-container { max-width: 800px; margin: 0 auto; }
        .settings-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 30px;
        }
        .form-label { font-weight: 600; }
    </style>
</head>
<body>
    <div class="settings-container">
        <h1 class="mb-4"><i class="fas fa-cog"></i> Admin Settings</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <h5 class="mb-4">System Configuration</h5>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="siteName" class="form-label">Site Name</label>
                    <input type="text" class="form-control" id="siteName" name="site_name" value="Online Quiz Project" required>
                </div>
                <div class="mb-3">
                    <label for="siteEmail" class="form-label">Site Email</label>
                    <input type="email" class="form-control" id="siteEmail" name="site_email" value="admin@onlinequiz.com" required>
                </div>
                <div class="mb-3">
                    <label for="maxQuestions" class="form-label">Maximum Questions Per Quiz</label>
                    <input type="number" class="form-control" id="maxQuestions" name="max_questions" value="50" min="1" required>
                </div>
                <div class="mb-3">
                    <label for="timeLimit" class="form-label">Default Time Limit (minutes)</label>
                    <input type="number" class="form-control" id="timeLimit" name="time_limit" value="30" min="1" required>
                </div>
                <div class="mb-3">
                    <label for="passingScore" class="form-label">Passing Score (%)</label>
                    <input type="number" class="form-control" id="passingScore" name="passing_score" value="60" min="0" max="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Maintenance Mode</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="maintenanceMode" name="maintenance_mode">
                        <label class="form-check-label" for="maintenanceMode">Enable Maintenance Mode</label>
                    </div>
                </div>
                <button type="submit" name="action" value="system" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>

        <div class="settings-card">
            <h5 class="mb-4">User Management</h5>
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Allow User Registration</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="userRegistration" name="allow_registration" checked>
                        <label class="form-check-label" for="userRegistration">Enabled</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Verification Required</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="emailVerification" name="email_verification" checked>
                        <label class="form-check-label" for="emailVerification">Enabled</label>
                    </div>
                </div>
                <button type="submit" name="action" value="users" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save User Settings
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>