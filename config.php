
<?php
// config.php - Database Configuration

// Start session
session_start();

// Database configuration - CHANGE THESE FOR YOUR SERVER
define('DB_HOST', 'localhost');
define('DB_NAME', 'library_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_NAME', 'Library Management System');
define('SITE_URL', 'http://localhost/library_system/');  // CHANGE THIS
define('MAX_BORROW_DAYS', 14);

// Set timezone
date_default_timezone_set('Africa/Kigali');

// Create database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>