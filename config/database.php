<?php
// config/database.php
$host = '127.0.0.1';       // PostgreSQL host
$port = '5432';             // PostgreSQL port
$db   = 'online_quiz_system'; // Database name
$user = 'postgres';         // Username
$pass = 'rorn';             // Password

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // echo "✅ Connection successful!"; // Optional test
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}

return $pdo;