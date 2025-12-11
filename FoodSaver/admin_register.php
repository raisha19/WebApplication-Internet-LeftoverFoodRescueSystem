<?php
// admin_register.php - Dedicated form for creating the initial Admin user.
session_start();
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']); 
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General styling for the form container */
        .admin-register-container {
            max-width: 450px;
            margin: 80px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .admin-register-container h2 {
            text-align: center;
            color: var(--color-danger, #d32f2f);
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        /* Styling for session messages */
        .message-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message-error {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }
        .message-success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #388e3c;
        }
        .admin-register-container .btn-primary {
            background-color: var(--color-primary);
            width: 100%;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.html" class="logo">Food<span>Saver</span></a>
                <ul class="nav-links">
                    <li><a href="login.php" class="btn btn-secondary">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="admin-register-container">
        <h2><i class="fas fa-user-shield"></i> Admin Account Setup</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">Use this page once to create the core system administrator account.</p>

        <?php if (!empty($error_message)): ?>
            <div class="message-box message-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="message-box message-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form action="auth.php?action=admin_register" method="POST">
            <div class="form-group">
                <label for="name">Admin Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Admin Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <!-- Hidden field hardcoded to 'admin' -->
            <input type="hidden" name="role" value="admin"> 

            <button type="submit" class="btn btn-primary">Create Admin Account</button>
        </form>
        <p style="margin-top: 25px; text-align: center;">Account ready? <a href="login.php">Log in now</a></p>
    </div>
</body>
</html>