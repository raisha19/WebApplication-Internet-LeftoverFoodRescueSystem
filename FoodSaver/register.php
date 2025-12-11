<?php
// register.php
include('auth.php'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body style="background-color: var(--color-background);">

    <div class="form-container" style="max-width: 550px;">
        <h2><i class="fas fa-user-plus"></i> Join FoodSaver</h2>
        
        <?php if (!empty($error)): ?>
            <div style="background-color: #ffebee; color: #d32f2f; border: 1px solid #d32f2f; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="auth.php" method="POST">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Your name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="role">I want to register as...</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Select a role</option>
                    <option value="donor">Donor (Businesses/Individuals with surplus food)</option>
                    <option value="receiver">Receiver (NGOs/Shelters/Community Kitchens)</option>
                    <option value="volunteer">Volunteer (For picking up and delivering food)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Create Account</button>
        </form>
        <p style="text-align: center; margin-top: 15px;"><a href="login.php">Already have an account? Login here.</a></p>
    </div>

</body>
</html>