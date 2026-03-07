<?php
require_once '/config/database.php'; // Adjust path if needed

try {
    // Create sample users
    $users = [
        ['admin', 'admin@quizsystem.com', 'admin123', 'admin'],
        ['teacher', 'teacher@quizsystem.com', 'teacher123', 'teacher'],
        ['student', 'student@quizsystem.com', 'student123', 'student']
    ];
    
    foreach ($users as $user) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$user[0]]);
        if ($stmt->fetchColumn() == 0) {
            $hashedPassword = password_hash($user[2], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user[0], $user[1], $hashedPassword, $user[3]]);
            echo "Created user: {$user[0]}\n";
        }
    }
    
    echo "Sample users created successfully!\n";
    
} catch (PDOException $e) {
    die("Error seeding users: " . $e->getMessage());
}
?>