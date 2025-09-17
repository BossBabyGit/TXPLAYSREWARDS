<?php
// Database configuration
$db_host = '127.0.0.1:3306';
$db_name = 'u382006905_tx';
$db_user = 'u382006905_tx';
$db_pass = '3i>e#5ZrV';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}