<?php
// admin_dashboard.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin User';

// --- METRICS ---
// Total KG Rescued (Delivered)
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity_kg),0) FROM food_posts WHERE status = 'Delivered'");
$total_kg_rescued = (float)$stmt->fetchColumn();

// Total Donation Posts
$stmt = $pdo->query("SELECT COUNT(*) FROM food_posts");
$total_posts = (int)$stmt->fetchColumn();

// Delivered Posts
$stmt = $pdo->query("SELECT COUNT(*) FROM food_posts WHERE status = 'Delivered'");
$delivered_count = (int)$stmt->fetchColumn();

// Delivery Success Rate
$success_rate = $total_posts > 0 ? number_format(($delivered_count / $total_posts) * 100, 1) : 0;

// Total Users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = (int)$stmt->fetchColumn();

// Active Volunteering Posts (Picked Up or Assigned)
$stmt = $pdo->query("SELECT COUNT(*) FROM food_posts WHERE status IN ('Picked Up','Assigned')");
$active_volunteering_posts = (int)$stmt->fetchColumn();

// --- MONTHLY RESCUED CHART DATA ---
$stmt = $pdo->query("
    SELECT MONTH(created_at) AS month, COALESCE(SUM(quantity_kg),0) AS total_kg
    FROM food_posts
    WHERE status = 'Delivered'
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
");
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$months = [];
$kg_data = [];
for ($i=1; $i<=12; $i++) {
    $months[] = date('M', mktime(0,0,0,$i,1));
    $found = false;
    foreach ($monthly_data as $row) {
        if ((int)$row['month'] === $i) {
            $kg_data[] = (float)$row['total_kg'];
            $found = true;
            break;
        }
    }
    if (!$found) $kg_data[] = 0;
}
$kg_data_json = json_encode($kg_data);
$months_json = json_encode($months);

// --- USER DISTRIBUTION ---
$stmt = $pdo->query("SELECT role, COUNT(user_id) AS count FROM users GROUP BY role");
$user_counts_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_labels = ['Donors','Receivers','Volunteers'];
$roles = ['donor','receiver','volunteer'];
$chart_data = [];
foreach ($roles as $role) {
    $chart_data[] = (int)($user_counts_raw[$role] ?? 0);
}
$chart_data_json = json_encode($chart_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=A" alt="Profile" class="profile-pic">
            <h4><?=htmlspecialchars($admin_name)?></h4>
            <p class="user-role">System Administrator</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="#analytics" class="active"><i class="fas fa-chart-line"></i> Analytics Dashboard</a></li>
                <li><a href="post_verification.php"><i class="fas fa-clipboard-check"></i> Post Verification</a></li>
                <li><a href="admin_donations_info.php"><i class="fas fa-list-alt"></i> All Donation</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>Admin Control Panel 💻</h2>
            <p>Oversight and management of the FoodSaver platform.</p>
        </div>

        <section id="analytics" style="margin-bottom: 40px;">
            <h3 style="color: var(--color-primary); margin-bottom: 20px;"><i class="fas fa-chart-pie"></i> Impact Metrics Overview</h3>
            <div class="dashboard-grid">
                <div class="card metric-card" style="background-color: #e8f5e9;">
                    <h3><?=number_format($total_kg_rescued,0)?> KG</h3>
                    <p>Total KG Rescued</p>
                </div>
                <div class="card metric-card" style="background-color: #fffde7;">
                    <h3><?=htmlspecialchars($success_rate)?>%</h3>
                    <p>Delivery Success Rate</p>
                </div>
                <div class="card metric-card" style="background-color: #e3f2fd;">
                    <h3><?=number_format($total_posts)?></h3>
                    <p>Total Donation Posts</p>
                </div>
                <div class="card metric-card" style="background-color: #ffe0b2;">
                    <h3><?=number_format($total_users)?></h3>
                    <p>Total Users</p>
                </div>
                <div class="card metric-card" style="background-color: #fae3f2;">
                    <h3><?= $active_volunteering_posts ?></h3>
                    <p>Active Volunteer Posts</p>
                </div>
            </div>

            <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr; margin-top:30px;">
                <div class="card">
                    <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Monthly Food Rescue (KG)</h4>
                    <canvas id="monthlyChart"></canvas>
                </div>
                <div class="card">
                    <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">User Distribution</h4>
                    <canvas id="userPieChart"></canvas>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?= $months_json ?>,
            datasets: [{
                label: 'KG Rescued',
                data: <?= $kg_data_json ?>,
                backgroundColor: 'rgba(76, 175, 80, 0.8)',
                borderColor: 'rgba(76, 175, 80, 1)',
                borderWidth: 1
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    const pieCtx = document.getElementById('userPieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                data: <?= $chart_data_json ?>,
                backgroundColor: ['#4CAF50', '#FFC107', '#2196F3'],
                hoverOffset: 4
            }]
        }
    });
});
</script>
</body>
</html>
