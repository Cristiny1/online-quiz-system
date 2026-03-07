<?php
// Include your database connection
$pdo = require_once __DIR__ . '/config/database.php'; // Adjust path if needed

try {
    // Try a simple query
    $stmt = $pdo->query("SELECT NOW() as current_time");
    $result = $stmt->fetch();

    echo "✅ Connection successful! Database time: " . $result['current_time'];
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}