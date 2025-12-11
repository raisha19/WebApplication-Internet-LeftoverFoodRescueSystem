<?php
// volunteer_my_assignments.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); // Ensure this file provides the $pdo connection

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'volunteer') {
    header("Location: login.php");
    exit;
}

$volunteer_id = $_SESSION['user_id'];
$volunteer_name = $_SESSION['name'] ?? 'Volunteer User';

$success_message = '';
$error_message = '';

// --- Update assignment status (REVISED LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $post_id = (int)($_POST['post_id']);
    // receiver_id is now required from the form
    $receiver_id = (int)($_POST['receiver_id']); 
    $new_status = $_POST['new_status'];

    try {
        $pdo->beginTransaction();

        if (in_array($new_status, ['Picked Up', 'Delivered'])) {
            // 1. Update the volunteer's assignment status in the assignments table
            $pdo->prepare("UPDATE assignments SET volunteer_status=?, delivery_time_estimate=NOW() WHERE assignment_id=? AND volunteer_id=?")
                ->execute([$new_status, $assignment_id, $volunteer_id]);
            
            // 2. Only update the global food_posts status and transfer address upon final 'Delivered' status
            if ($new_status === 'Delivered') {
                
                // Get the delivery address from the requests table using post_id and receiver_id
                $stmt_addr = $pdo->prepare("SELECT delivery_address FROM requests WHERE post_id=? AND receiver_id=? AND request_status='Accepted'");
                $stmt_addr->execute([$post_id, $receiver_id]);
                $delivery_address = $stmt_addr->fetchColumn();

                // Update food_posts status and delivery address
                $pdo->prepare("UPDATE food_posts SET status=?, delivery_address=? WHERE post_id=?")
                    ->execute([$new_status, $delivery_address, $post_id]);
            }
            
            $success_message = "Status updated to $new_status!";
        }
        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Database error: " . $e->getMessage();
    }
}

// --- Fetch active assignments (REVISED QUERY) ---
$stmt = $pdo->prepare("
    SELECT 
        a.assignment_id, a.post_id, a.volunteer_status, a.receiver_id, 
        fp.title, fp.category, fp.quantity_kg, fp.pickup_location,
        u_donor.name AS donor_name, u_receiver.name AS receiver_name, r.delivery_address
    FROM assignments a
    JOIN food_posts fp ON a.post_id = fp.post_id
    JOIN users u_donor ON fp.donor_id = u_donor.user_id
    JOIN users u_receiver ON a.receiver_id = u_receiver.user_id
    -- Link request to assignment via post_id AND receiver_id to ensure we get the correct address
    JOIN requests r ON a.post_id = r.post_id AND a.receiver_id = r.receiver_id
    
    -- Filter by volunteer and assignment status only 
    WHERE a.volunteer_id=? AND a.volunteer_status!='Delivered' 
    ORDER BY a.assignment_date ASC
");
$stmt->execute([$volunteer_id]);
$assigned_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Assignments - Volunteer Dashboard</title>
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
                <li><a href="volunteer_dashboard3.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="volunteer_claim_task.php"><i class="fas fa-search-location"></i> Claim Task</a></li>
                <li><a href="volunteer_my_assignments.php" class="active"><i class="fas fa-route"></i> My Assignments</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>My Active Assignments</h2>
        </div>

        <?php if ($success_message): ?>
            <div class="card" style="background-color: #e8f5e9; border-left: 5px solid #388e3c; color: #388e3c; padding: 15px; margin-bottom: 20px;"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="card" style="background-color: #ffebee; border-left: 5px solid #d32f2f; color: #d32f2f; padding: 15px; margin-bottom: 20px;"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if (empty($assigned_tasks)): ?>
            <div class="card" style="text-align:center; padding:50px; background:#f7f7f7;">
                <i class="fas fa-box-open" style="font-size: 3rem; color: #2196F3;"></i>
                <h4>You have no active assignments. Claim tasks to start delivering!</h4>
            </div>
        <?php else: ?>
            <div class="dashboard-grid" style="grid-template-columns:1fr;">
                <?php foreach ($assigned_tasks as $task): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($task['title']); ?></h3>
                        <p><strong>Donor:</strong> <?= htmlspecialchars($task['donor_name']); ?> | <strong>Quantity:</strong> <?= htmlspecialchars($task['quantity_kg']); ?> kg</p>
                        <p><strong>Pickup:</strong> <?= htmlspecialchars($task['pickup_location']); ?></p>
                        <p><strong>Receiver:</strong> <?= htmlspecialchars($task['receiver_name']); ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($task['delivery_address']); ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($task['volunteer_status']); ?></p>

                        <?php if ($task['volunteer_status'] === 'Accepted'): ?>
                            <form method="POST" style="margin-top:10px;">
                                <input type="hidden" name="assignment_id" value="<?= $task['assignment_id'] ?>">
                                <input type="hidden" name="post_id" value="<?= $task['post_id'] ?>">
                                <input type="hidden" name="receiver_id" value="<?= $task['receiver_id'] ?>"> <input type="hidden" name="new_status" value="Picked Up">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-truck-moving"></i> Mark Picked Up</button>
                            </form>
                        <?php elseif ($task['volunteer_status'] === 'Picked Up'): ?>
                            <form method="POST" style="margin-top:10px;">
                                <input type="hidden" name="assignment_id" value="<?= $task['assignment_id'] ?>">
                                <input type="hidden" name="post_id" value="<?= $task['post_id'] ?>">
                                <input type="hidden" name="receiver_id" value="<?= $task['receiver_id'] ?>"> <input type="hidden" name="new_status" value="Delivered">
                                <button type="submit" class="btn btn-success"><i class="fas fa-handshake"></i> Confirm Delivery</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>