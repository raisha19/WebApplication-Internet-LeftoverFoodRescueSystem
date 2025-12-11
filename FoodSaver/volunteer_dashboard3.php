<?php
// volunteer_dashboard_main.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'volunteer') {
    header("Location: login.php");
    exit;
}

$volunteer_id = $_SESSION['user_id'];
$volunteer_name = $_SESSION['name'] ?? 'Volunteer User';

// Fetch summary metrics
try {
    // Total Available Tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_posts fp JOIN requests r ON fp.post_id = r.post_id WHERE fp.status='Requested' AND r.request_status='Pending'");
    $stmt->execute();
    $total_available_tasks = (int)$stmt->fetchColumn();

    // Total Active Assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE volunteer_id=? AND volunteer_status!='Delivered'");
    $stmt->execute([$volunteer_id]);
    $total_active_assignments = (int)$stmt->fetchColumn();

    // Total Completed Deliveries
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE volunteer_id=? AND volunteer_status='Delivered'");
    $stmt->execute([$volunteer_id]);
    $total_completed = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_available_tasks = $total_active_assignments = $total_completed = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Volunteer Dashboard - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="volunteer_dashboard_main.php" class="logo">Food<span>Saver</span></a>
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
            <img src="assets/images/profile-pic.png" alt="Profile" class="profile-pic">
            <h4><?= htmlspecialchars($volunteer_name); ?></h4>
            <p class="user-role">Volunteer Account</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="volunteer_dashboard3.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="volunteer_claim_task.php"><i class="fas fa-search-location"></i> Claim Task</a></li>
                <li><a href="volunteer_my_assignments.php"><i class="fas fa-route"></i> My Assignments</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>Welcome <?= htmlspecialchars($volunteer_name); ?> 🚚</h2>
            <p>Quick overview of your tasks and progress.</p>
        </div>

        <section>
            <div class="dashboard-grid">
                <div class="card metric-card" style="background-color: #e3f2fd;">
                    <h3><?= $total_available_tasks ?></h3>
                    <p>Available Tasks to Claim</p>
                </div>
                <div class="card metric-card" style="background-color: #fae3f2;">
                    <h3><?= $total_active_assignments ?></h3>
                    <p>Active Assignments</p>
                </div>
                <div class="card metric-card" style="background-color: #e8f5e9;">
                    <h3><?= $total_completed ?></h3>
                    <p>Completed Deliveries</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
