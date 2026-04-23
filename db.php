<?php
// ============================================================
// db.php — Database Connection File
// Include this file in every PHP page that needs database access
// Usage: require_once 'db.php';
// ============================================================

// --- Configuration ---
define('DB_HOST', 'localhost');   // XAMPP default host
define('DB_USER', 'root');        // XAMPP default MySQL username
define('DB_PASS', '');            // XAMPP default MySQL password (empty)
define('DB_NAME', 'vocalid');     // Database name we created in SQL

// --- Create Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Check for Connection Errors ---
if ($conn->connect_error) {
    // Stop execution and show error if connection fails
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// --- Set character encoding to UTF-8 ---
$conn->set_charset('utf8mb4');

// Connection is now available as $conn in any file that includes this
?>
