<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// FIX: Original had no PHP session/auth check at all
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /online_quiz_system/login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../../config/database.php';

$message = '';
$error   = '';

// Helper: get a setting value with a default fallback
function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['value'] : $default;
    } catch (PDOException) {
        return $default;
    }
}

// Helper: upsert a setting
function saveSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, value) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE value = :value2
    ");
    $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
}

// FIX: Handle POST with CSRF validation and actual DB persistence
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($action === 'system') {
                $siteName    = trim($_POST['site_name']    ?? '');
                $siteEmail   = trim($_POST['site_email']   ?? '');
                $maxQ        = filter_var($_POST['max_questions'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
                $timeLimit   = filter_var($_POST['time_limit']    ?? 30, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 300]]);
                $passingScore = filter_var($_POST['passing_score'] ?? 60, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
                $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';

                if (empty($siteName)) {
                    $error = 'Site name is required.';
                } elseif (!filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'A valid email address is required.';
                } elseif ($maxQ === false || $timeLimit === false || $passingScore === false) {
                    $error = 'Please enter valid numeric values within the allowed ranges.';
                } else {
                    saveSetting($pdo, 'site_name',        $siteName);
                    saveSetting($pdo, 'site_email',       $siteEmail);
                    saveSetting($pdo, 'max_questions',    (string) $maxQ);
                    saveSetting($pdo, 'time_limit',       (string) $timeLimit);
                    saveSetting($pdo, 'passing_score',    (string) $passingScore);
                    saveSetting($pdo, 'maintenance_mode', $maintenance);
                    $message = 'System settings saved successfully.';
                }

            } elseif ($action === 'users') {
                $allowReg    = isset($_POST['allow_registration']) ? '1' : '0';
                $emailVerify = isset($_POST['email_verification']) ? '1' : '0';
                saveSetting($pdo, 'allow_registration', $allowReg);
                saveSetting($pdo, 'email_verification', $emailVerify);
                $message = 'User settings saved successfully.';
            }
        } catch (PDOException $e) {
            error_log('Settings save error: ' . $e->getMessage());
            $error = 'Failed to save settings. Please try again.';
        }

        // Regenerate CSRF after POST
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Load current settings
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $currentSettings = [
        'site_name'          => getSetting($pdo, 'site_name',          'Online Quiz System'),
        'site_email'         => getSetting($pdo, 'site_email',         'admin@onlinequiz.com'),
        'max_questions'      => getSetting($pdo, 'max_questions',      '50'),
        'time_limit'         => getSetting($pdo, 'time_limit',         '30'),
        'passing_score'      => getSetting($pdo, 'passing_score',      '60'),
        'maintenance_mode'   => getSetting($pdo, 'maintenance_mode',   '0'),
        'allow_registration' => getSetting($pdo, 'allow_registration', '1'),
        'email_verification' => getSetting($pdo, 'email_verification', '1'),
    ];
} catch (PDOException $e) {
    // Fallback defaults if settings table does not exist yet
    $currentSettings = [
        'site_name'          => 'Online Quiz System',
        'site_email'         => 'admin@onlinequiz.com',
        'max_questions'      => '50',
        'time_limit'         => '30',
        'passing_score'      => '60',
        'maintenance_mode'   => '0',
        'allow_registration' => '1',
        'email_verification' => '1',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings — QuizMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --secondary: #3f37c9; }
        body { background: #f8f9fa; font-family: 'Inter', system-ui, sans-serif; padding-bottom: 40px; }
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;
        }
        .settings-card {
            background: white; border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,.06); margin-bottom: 24px;
        }
        .settings-card .card-header { background: white; border-bottom: 2px solid #f0f2f5; border-radius: 12px 12px 0 0; font-weight: 600; padding: 16px 24px; }
        .settings-card .card-body  { padding: 24px; }
        .form-label { font-weight: 600; }
        .form-control, .form-select { border: 2px solid #e0e0e0; border-radius: 8px; padding: 10px 14px; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,.15); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container mt-4" style="max-width: 860px">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Settings</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1 class="fw-bold mb-1"><i class="fas fa-cog me-2"></i>Admin Settings</h1>
        <p class="mb-0 opacity-75">Configure system-wide options</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- System Settings -->
    <div class="settings-card">
        <div class="card-header"><i class="fas fa-server me-2 text-primary"></i>System Configuration</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="system">

                <div class="mb-3">
                    <label class="form-label">Site Name</label>
                    <input type="text" class="form-control" name="site_name"
                           value="<?= htmlspecialchars($currentSettings['site_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Site Email</label>
                    <input type="email" class="form-control" name="site_email"
                           value="<?= htmlspecialchars($currentSettings['site_email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Max Questions Per Quiz</label>
                        <input type="number" class="form-control" name="max_questions" min="1" max="500"
                               value="<?= (int) $currentSettings['max_questions'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Default Time Limit (min)</label>
                        <input type="number" class="form-control" name="time_limit" min="1" max="300"
                               value="<?= (int) $currentSettings['time_limit'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Passing Score (%)</label>
                        <input type="number" class="form-control" name="passing_score" min="0" max="100"
                               value="<?= (int) $currentSettings['passing_score'] ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Maintenance Mode</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode"
                               <?= $currentSettings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="maintenanceMode">Enable Maintenance Mode</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save System Settings
                </button>
            </form>
        </div>
    </div>

    <!-- User Settings -->
    <div class="settings-card">
        <div class="card-header"><i class="fas fa-users me-2 text-primary"></i>User Management</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="users">

                <div class="mb-3">
                    <label class="form-label d-block">Allow User Registration</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_registration" id="userRegistration"
                               <?= $currentSettings['allow_registration'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="userRegistration">Enabled</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Email Verification Required</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="email_verification" id="emailVerification"
                               <?= $currentSettings['email_verification'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="emailVerification">Enabled</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save User Settings
                </button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>