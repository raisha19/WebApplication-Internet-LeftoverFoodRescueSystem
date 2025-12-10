<?php
// auth.php - Handles Registration, Login, and Logout

// --- FIX APPLIED: Set session parameters BEFORE session_start() ---

// Set the session lifetime to 4 hours (4 * 60 * 60 = 14400 seconds)
$session_lifetime = 14400; 

// 1. Set the server-side garbage collection maximum lifetime
ini_set('session.gc_maxlifetime', $session_lifetime);

// 2. Set the browser-side session cookie lifetime
session_set_cookie_params($session_lifetime); 

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- END FIX ---

// Include database connection
// NOTE: Make sure db_connect.php is in the same directory or adjust the path.
include_once('db_connect.php');

// Initialize the error variable (used locally, but now also stored in session)
$error = '';

// Check if an action is requested via POST or GET
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Sanitize inputs
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            // Basic validation
            if (empty($name) || empty($email) || empty($password) || empty($role)) {
                $error = "All fields are required for registration.";
                $_SESSION['error_message'] = $error; 
                header("Location: register.php"); 
                exit();
            }

            // Password hashing
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                // Check if user already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "This email is already registered.";
                    $_SESSION['error_message'] = $error; 
                    header("Location: register.php"); 
                    exit();
                }

                // FIX APPLIED: Removed 'account_status'
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role]);
                
                $_SESSION['success_message'] = "Registration successful! Please log in.";
                header("Location: login.php");
                exit();

            } catch (PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
                $_SESSION['error_message'] = $error; 
                header("Location: register.php"); 
                exit();
            }
        }
        break;

    case 'admin_register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            if (!isset($pdo) || !$pdo) {
                $error = "Database connection not available. Check db_connect.php.";
                $_SESSION['error_message'] = $error;
                header("Location: admin_register.php"); 
                exit();
            }
            
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = 'admin'; 

            if (empty($name) || empty($email) || empty($password)) {
                $error = "Name, email, and password are required.";
                $_SESSION['error_message'] = $error;
                header("Location: admin_register.php"); 
                exit();
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "This email is already registered. If you forgot your password, please contact support.";
                    $_SESSION['error_message'] = $error;
                    header("Location: admin_register.php");
                    exit();
                }

                // FIX APPLIED: Removed 'account_status'
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role]);
                
                $_SESSION['success_message'] = "Admin account successfully created! Please log in.";
                header("Location: login.php");
                exit();

            } catch (PDOException $e) {
                $error = "Admin registration failed (Database Error): " . $e->getMessage();
                $_SESSION['error_message'] = $error;
                header("Location: admin_register.php"); 
                exit();
            }
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (empty($email) || empty($password) || empty($role)) {
                $error = "Please enter email, password, and select your role.";
                $_SESSION['error_message'] = $error; 
                header("Location: login.php"); 
                exit();
            }

            try {
                // FIX APPLIED: Only SELECT columns that exist in the database
                $stmt = $pdo->prepare("SELECT user_id, name, password_hash, role FROM users WHERE email = ? AND role = ?");
                $stmt->execute([$email, $role]);
                $user = $stmt->fetch();
                
                if ($user) {
                    if (password_verify($password, $user['password_hash'])) {
                        
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Redirect to appropriate dashboard
                        switch ($user['role']) {
                            case 'donor':
                                header("Location: donor_dashboard.php");
                                break;
                            case 'receiver':
                                header("Location: receiver_dashboard.php");
                                break;
                            case 'volunteer':
                                header("Location: volunteer_dashboard3.php");
                                break;
                            case 'admin':
                                header("Location: admin_dashboard3.php");
                                break;
                            default:
                                $error = "Invalid role specified.";
                                $_SESSION['error_message'] = $error;
                                session_destroy();
                                header("Location: login.php");
                                break;
                        }
                        exit();
                        
                    } else {
                        $_SESSION['error_message'] = "Invalid credentials or role mismatch.";
                        header("Location: login.php");
                        exit();
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid credentials or role mismatch.";
                    header("Location: login.php");
                    exit();
                }

            } catch (PDOException $e) {
                error_log("Login DB Error: " . $e->getMessage());
                $_SESSION['error_message'] = "A critical database error occurred during login.";
                header("Location: login.php");
                exit();
            }
        }
        break;

    case 'logout':
        // Destroy session and redirect to home
        session_unset();
        session_destroy();
        header("Location: index.html");
        exit();
        break;

    default:
        // This default case is mostly for handling leftover success messages
        if (isset($_SESSION['success_message'])) {
            $error = $_SESSION['success_message']; 
        }
        break;
}

// If any error occurred and was not handled by a redirect, ensure it's in the session.
if (!empty($error) && !isset($_SESSION['error_message'])) {
    $_SESSION['error_message'] = $error;
}
?>