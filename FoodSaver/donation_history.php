<?php
// donor_dashboard.php

// --- 1. ERROR REPORTING & SESSION START ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// IMPORTANT: Ensure this path is correct and db_connect.php sets the $pdo variable
include('db_connect.php'); 

// Fallback for testing (REMOVE '?? 1' and '?? "Sarah Donor"' in production)
$donor_id = $_SESSION['user_id'];
$donor_name = $_SESSION['name'];

$success_message = '';
$error_message = '';
$posts = []; // Initialize posts array


// Retrieve flash messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// --- 4. FETCH DONOR POSTS (UPDATED to include rating and review) ---
try {
    // Fetch posts only if $pdo is successfully connected
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT post_id, title, category, quantity_kg, expiry_date, rating, review_comment, pickup_location FROM food_posts WHERE donor_id = ? and status != 'Rejected' ORDER BY created_at DESC");
        $stmt->execute([$donor_id]);
        $posts = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    if (empty($error_message)) {
           $error_message = "Could not fetch posts. Database Error: " . $e->getMessage();
    }
    $posts = [];
}

// Helper function to render star rating
function display_rating($rating) {
    if ($rating === null || $rating === '') {
        return 'N/A';
    }
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star" style="color: gold;"></i>';
        } else {
            $stars .= '<i class="far fa-star" style="color: #ccc;"></i>';
        }
    }
    return $stars;
}


// --- 5. DATA FOR DISPLAY (using real data or fallback simulation - MODIFIED FOR post_id) ---
// ADDED rating and review_comment to simulation data for testing
$simulated_posts = [
    // Note: The key is 'post_id'
    ['post_id' => 999, 'title' => 'Excess Bakery Goods', 'category' => 'Baked Goods', 'quantity_kg' => 15, 'expiry_date' => '2025-11-13', 'status' => 'Assigned', 'rating' => 4, 'review_comment' => 'Great bread, volunteer was quick.', 'volunteer' => 'John V.', 'description' => 'A lot of good bread.', 'pickup_location' => '123 Test St'],
    ['post_id' => 998, 'title' => 'Organic Produce Box', 'category' => 'Produce', 'quantity_kg' => 10, 'expiry_date' => '2025-11-15', 'status' => 'Pending Approval', 'rating' => null, 'review_comment' => null, 'volunteer' => 'N/A', 'description' => 'Fresh tomatoes and lettuce.', 'pickup_location' => '456 Sample Ave'],
    ['post_id' => 997, 'title' => 'Delivered Meals', 'category' => 'Meals', 'quantity_kg' => 50, 'expiry_date' => '2025-11-11', 'status' => 'Delivered', 'rating' => 5, 'review_comment' => 'Perfect quality, highly appreciated.', 'volunteer' => 'Mary W.', 'description' => '50 servings of soup.', 'pickup_location' => '789 Dummy Rd'],
];
$data_to_show = empty($posts) ? $simulated_posts : $posts;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header class="header">
        <div class="container"><nav class="navbar"><a href="index.html" class="logo">Food<span>Saver</span></a><ul class="nav-links"><li><a class="btn btn-secondary" href="index.html">Home</a></li><li><a class="btn btn-secondary" href="auth.php?action=logout" class="btn btn-secondary">Logout</a></li></ul></nav></div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=D" alt="Profile" class="profile-pic">
                <h4 style="color: var(--color-text-light); margin-top: 10px;"><?php echo htmlspecialchars($donor_name); ?></h4>
                <p class="user-role">Donor Account</p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="donor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="post_creation.php"><i class="fas fa-cloud-upload-alt"></i> New Donation</a></li>
                    <li><a href="track_donation.php"><i class="fas fa-route"></i> Track Donation</a></li>
                    <li><a href="donation_history.php" class="active"><i class="fas fa-history"></i> Donation History</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
    
            <?php if ($success_message): ?>
                <div class="card" style="background-color: #e8f5e9; border-left: 5px solid #388e3c; color: #388e3c; padding: 15px; margin-bottom: 20px;"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="card" style="background-color: #ffebee; border-left: 5px solid #d32f2f; color: #d32f2f; padding: 15px; margin-bottom: 20px;"><?= $error_message ?></div>
            <?php endif; ?>

            <div class="card animate-on-load" style="padding: 0;">
                <h3 style="padding: 20px; border-bottom: 1px solid #eee; color: var(--color-primary);">My Donation History</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry</th>
                            <th>Rating ⭐</th> <th>Review Comment</th> </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_to_show as $post): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($post['title']); ?></td>
                                <td><?php echo htmlspecialchars($post['category'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($post['quantity_kg']); ?> kg</td>
                                <td><?php echo htmlspecialchars($post['expiry_date']); ?></td>
                                <td><?php echo display_rating($post['rating'] ?? null); ?></td> <td>
                                    <?php 
                                    $comment = $post['review_comment'] ?? 'Not yet reviewed.';
                                    echo htmlspecialchars($comment); 
                                    ?>
                                </td> </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($data_to_show)): ?>
                    <p style="text-align: center; padding: 20px; color: #888;">No donation posts found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>