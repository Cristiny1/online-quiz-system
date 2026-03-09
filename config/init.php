<?php
declare(strict_types=1);

// Error reporting based on environment
if (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Secure session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Session regeneration for security
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once __DIR__ . '/database.php';

// Check if user is logged in (optional - call this function where needed)
function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /online_quiz_system/login.php');
        exit();
    }
}

// Check if user has required role
function requireRole(string $role): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: dashboard.php');
        exit();
    }
}

// Get current user info
function getCurrentUser(): array {
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'Guest',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'guest'
    ];
}