<?php
// config/database.php

// Define your database credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'online_quiz_system');
define('DB_USER', 'postgres');
define('DB_PASS', 'rorn');

// Build DSN for PostgreSQL
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    DB_HOST, DB_PORT, DB_NAME
);

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,                 // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // echo "Connected successfully!";
} catch (PDOException $e) {
    die('[DB ERROR] ' . $e->getMessage());
}

// Optional: return PDO if you want to include() this file
return $pdo;