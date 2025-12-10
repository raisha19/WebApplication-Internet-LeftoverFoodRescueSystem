<?php
// login.php - Handles login form submission and error display

// 1. START SESSION (Will now happen when auth.php is included later)

// 2. RETRIEVE AND CLEAR FLASH MESSAGES FROM SESSION
$error_message = '';
$success_message = '';

// 3. INCLUDE auth.php (This file will now handle session_start() *after* configuration)
include('auth.php'); 

// The session is now guaranteed to be active here because auth.php started it.

// RETRIEVE AND CLEAR FLASH MESSAGES FROM SESSION
// (Note: Since auth.php handles redirection and this is included, 
// you can rely on the variables being set after the include, 
// but checking the SESSION variables needs to happen *after* auth.php is included.)

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); 
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Combine messages for display (favor error over success if both exist, though they shouldn't)
$display_message = $error_message ?: $success_message;
$is_success = !empty($success_message) && empty($error_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body style="background-color: var(--color-background);">

    <div class="form-container">
        <h2 style="display: flex; align-items: center; justify-content: center;"><i class="fas fa-lock" style="margin-right: 10px;"></i> Secure Login</h2>
        
        <?php if (!empty($display_message)): ?>
            <div style="background-color: <?php echo $is_success ? '#e8f5e9' : '#ffebee'; ?>; 
                        color: <?php echo $is_success ? '#388e3c' : '#d32f2f'; ?>; 
                        border: 1px solid <?php echo $is_success ? '#4CAF50' : '#d32f2f'; ?>; 
                        padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-<?php echo $is_success ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                <?php echo htmlspecialchars($display_message); ?>
            </div>
        <?php endif; ?>

        <form action="auth.php" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-key"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Select Role</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Select your role</option>
                    <option value="donor">Donor</option>
                    <option value="receiver">Receiver</option>
                    <option value="volunteer">Volunteer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Login to Dashboard</button>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            <a href="#">Forgot Password?</a> | <a href="register.php">Don't have an account? Register</a>
        </p>
    </div>
</body>
</html>