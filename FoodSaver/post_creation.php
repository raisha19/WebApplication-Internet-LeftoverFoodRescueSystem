<?php
// post_creation.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

$donor_id = $_SESSION['user_id'];
$donor_name = $_SESSION['name'];

$success_message = '';
$error_message = '';

// --- Handle New Post Submission ---
if (isset($_POST['action']) && $_POST['action'] === 'add_post') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $video_url = '';

    // Video upload handling
    if (!empty($_FILES['video']['name'])) {
        $target_dir = "uploads/videos/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $video_file = $_FILES['video']['name'];
        $file_tmp = $_FILES['video']['tmp_name'];
        $file_size = $_FILES['video']['size'];
        $file_ext = strtolower(pathinfo($video_file, PATHINFO_EXTENSION));
        $allowed_types = ['mp4','mov','avi','mkv'];

        if (!in_array($file_ext, $allowed_types)) {
            $error_message = "Invalid video format. Only mp4, mov, avi, mkv allowed.";
        } elseif ($file_size > 15 * 1024 * 1024) {
            $error_message = "Video exceeds maximum size of 15MB.";
        } else {
            $video_name = time().'_'.basename($video_file);
            $target_file = $target_dir.$video_name;
            if (move_uploaded_file($file_tmp, $target_file)) {
                $video_url = $target_file;
            } else {
                $error_message = "Failed to upload video.";
            }
        }
    }

    // Validation
    if (empty($title) || empty($description) || empty($location) || $quantity <= 0) {
        $error_message = "Please fill all required fields and ensure quantity > 0.";
    }

    // Insert into database
    if (empty($error_message) && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO food_posts 
                (donor_id, title, description, category, quantity_kg, expiry_date, pickup_location, video_url, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Approval')");
            $stmt->execute([$donor_id, $title, $description, $category, $quantity, $expiry_date, $location, $video_url]);

            $_SESSION['success_message'] = "Donation post submitted successfully! It is now **Pending Approval** by the Admin.";
            header("Location: post_creation.php");
            exit;
        } catch(PDOException $e) {
            $error_message = "Database error: ".$e->getMessage();
        }
    }
}

// Retrieve flash messages
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Donation Post - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="index.html" class="logo">Food<span>Saver</span></a>
            <ul class="nav-links">
                <li><a class="btn btn-secondary" href="index.html">Home</a></li>
                <li><a class="btn btn-secondary" href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/profile-pic.png" alt="Profile" class="profile-pic">
            <h4 style="color: var(--color-text-light); margin-top: 10px;"><?php echo htmlspecialchars($donor_name); ?></h4>
            <p class="user-role">Donor Account</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="donor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="post_creation.php" class="active"><i class="fas fa-cloud-upload-alt"></i> New Donation</a></li>
                <li><a href="track_donation.php"><i class="fas fa-tracking"></i> Track Donation</a></li>
                <li><a href="donation_history.php"><i class="fas fa-history"></i> Donation History</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <h2>📦 Create a New Donation Post</h2>

        <?php if($success_message): ?>
            <div class="card" style="background-color:#e8f5e9;color:#388e3c;padding:15px;margin-bottom:20px;">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="card" style="background-color:#ffebee;color:#d32f2f;padding:15px;margin-bottom:20px;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:1000px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_post">

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="e.g., Excess Bakery Goods" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Briefly describe the food and any special requirements." required></textarea>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Produce">Fresh Produce</option>
                        <option value="Baked Goods">Baked Goods</option>
                        <option value="Dairy">Dairy & Eggs</option>
                        <option value="Meals">Prepared Meals</option>
                        <option value="Canned Goods">Canned Goods</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity (kg)</label>
                    <input type="number" name="quantity" step="0.1" min="0.1" required>
                </div>

                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" required>
                </div>

                <div class="form-group">
                    <label>Pickup Location</label>
                    <input type="text" name="location" placeholder="Full pickup address" required>
                </div>

                <div class="form-group">
                    <label>Upload Video Proof (Max 15MB)</label>
                    <input type="file" name="video" accept="video/*">
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Submit Post for Approval</button>
                    <button type="reset" class="btn btn-secondary" style="flex:1;">Reset</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
