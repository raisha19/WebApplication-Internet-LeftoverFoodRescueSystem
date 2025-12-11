<?php
session_start();
include('db_connect.php');

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$receiver_id = $_SESSION['user_id'];

/* ❗ 1. ACTIVE DELIVERY COUNT (Accepted requests) */
$query_active = $pdo->prepare("
    SELECT COUNT(*) AS active_delivery
    FROM requests
    WHERE receiver_id = ?
      AND request_status = 'Accepted'
");
$query_active->execute([$receiver_id]);
$active_delivery = $query_active->fetch(PDO::FETCH_ASSOC)['active_delivery'];

/* ❗ 2. SUCCESSFUL RECEIPTS (Accepted requests) */
$query_success = $pdo->prepare("
    SELECT COUNT(*) AS successful
    FROM requests
    WHERE receiver_id = ?
      AND request_status = 'Accepted'
");
$query_success->execute([$receiver_id]);
$successful_receipts = $query_success->fetch(PDO::FETCH_ASSOC)['successful'];

/* ❗ 3. TOTAL KG RECEIVED (JOIN with food_posts) */
$query_kg = $pdo->prepare("
    SELECT SUM(food_posts.quantity_kg) AS total_kg
    FROM requests
    JOIN food_posts ON requests.post_id = food_posts.post_id
    WHERE requests.receiver_id = ?
      AND requests.request_status = 'Accepted'
");
$query_kg->execute([$receiver_id]);
$total_kg = $query_kg->fetch(PDO::FETCH_ASSOC)['total_kg'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receiver Dashboard - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="index.html" class="logo">Food<span>Saver</span></a>
            <ul class="nav-links">
                <li><a href="index.html" class="btn btn-secondary">Home</a></li>
                <li><a href="auth.php?action=logout" class="btn btn-secondary">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=R" alt="Profile" class="profile-pic">
            <h4><?php echo $_SESSION['user_name'] ?? "Receiver"; ?></h4>
            <p class="user-role">Receiver Account</p>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li><a href="receiver_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</a></li>
                <li><a href="receiver_available_food.php"><i class="fas fa-list-ul"></i> Available Food</a></li>
                <li><a href="receiver_review.php"><i class="fas fa-star"></i> Review Food</a></li>
                <li><a href="receiver_history.php"><i class="fas fa-history"></i> Request History</a></li>
                <li><a href="receiver_track_delivery.php"><i class="fas fa-truck"></i> Track Deliveries</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>Welcome! Time to Receive Rescued Food.</h2>
            <a href="receiver_available_food.php" class="btn btn-primary"><i class="fas fa-search"></i> View Latest Listings</a>
        </div>

        <h3 style="color: var(--color-primary); margin-bottom: 20px;">Your Impact & Status</h3>

        <div class="dashboard-grid">

            <!-- ACTIVE DELIVERY -->
            <div class="card metric-card animate-on-load">
                <i class="fas fa-boxes" style="font-size: 2rem; color: #03A9F4;"></i>
                <h3><?php echo $active_delivery; ?></h3>
                <p>Active Delivery</p>
            </div>

            <!-- SUCCESSFUL RECEIPTS -->
            <div class="card metric-card animate-on-load" style="animation-delay: 0.2s;">
                <i class="fas fa-check-double" style="font-size: 2rem; color: var(--color-primary);"></i>
                <h3><?php echo $successful_receipts; ?></h3>
                <p>Successful Receipts</p>
            </div>

            <!-- TOTAL KG RECEIVED -->
            <div class="card metric-card animate-on-load" style="animation-delay: 0.4s;">
                <i class="fas fa-hand-holding-box" style="font-size: 2rem; color: var(--color-secondary);"></i>
                <h3><?php echo $total_kg; ?> kg</h3>
                <p>Food Received Total</p>
            </div>

        </div>
    </main>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>