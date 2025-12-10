<?php
// db_connect.php

// Database Credentials (REPLACE with your actual credentials)
// IMPORTANT: If you are using XAMPP/WAMP/MAMP, these defaults are common:
// DB_HOST: 'localhost'
// DB_USER: 'root'
// DB_PASS: '' (Empty string for no password on root)
// DB_NAME: 'foodsaver_db' (Ensure this is the correct name!)
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); 
define('DB_PASS', ''); 
define('DB_NAME', 'foodsaver_db');

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    
    // Set PDO attributes for better security and results formatting
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- TEMPORARY SUCCESS CHECK ---
    // Remove this line once you confirm the connection works.
    // echo "✅ Database Connection Successful! You can proceed to login."; 
    // ---------------------------------

} catch (PDOException $e) {
    // If connection fails, display a clear error message and stop execution
    echo "<h1>❌ FATAL ERROR: DATABASE CONNECTION FAILED</h1>";
    echo "<p>Please check your credentials in <code>db_connect.php</code>.</p>";
    echo "<p><strong>Details:</strong> " . $e->getMessage() . "</p>";
    exit(); // Stop the script immediately
}

/**
 * Global function to check and redirect unauthenticated users
 * (This function is not strictly needed in the db_connect file, but we will keep it for now)
 */
function check_auth($required_role) {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $required_role) {
        header("Location: login.php");
        exit();
    }
}
?>