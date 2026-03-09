
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Quiz System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .user-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .delete-btn:hover {
            transform: scale(1.1);
            color: #c82333;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-graduation-cap me-2"></i>Quiz System</h4>
            <small>Admin Dashboard</small>
        </div>
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="quizzes.php" class="nav-link">
            <i class="fas fa-question-circle"></i> All Quizzes
        </a>
        <a href="usermanagement.php" class="nav-link active">
            <i class="fas fa-users-cog"></i> User Management
        </a>
        <a href="departments.php" class="nav-link">
            <i class="fas fa-school"></i> Departments
        </a>
        <a href="create-quiz.php" class="nav-link">
            <i class="fas fa-plus-circle"></i> Create Quiz
        </a>
        <a href="reports.php" class="nav-link">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="setting.php" class="nav-link">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logout.php" class="nav-link logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <span class="menu-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </span>
            <span class="fw-bold">User Management</span>
            <div class="d-flex align-items-center">
                <span class="badge bg-primary me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    Admin
                </span>
            </div>
        </div>

        <div class="container-fluid py-4">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add User Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </button>
            </div>

            <!-- Users Table -->
            <div class="card shadow">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>
                        System Users
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover user-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $user['role'] === 'admin' ? 'danger' : 
                                            ($user['role'] === 'teacher' ? 'warning' : 'info') 
                                        ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="delete-btn btn-sm" 
                                                    <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2 text-primary"></i>
                        Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="add_user" value="1">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.menu-btn');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuBtn.contains(event.target) && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>