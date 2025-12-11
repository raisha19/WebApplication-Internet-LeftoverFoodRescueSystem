<?php
// receiver_available_food.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Ensure db_connect.php connects and sets $pdo
// You must ensure 'db_connect.php' is available and functional.
include('db_connect.php'); 

// 1. SECURITY CHECK: Ensure user is logged in AND is a 'receiver'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    // Redirect non-receivers or guests to login page
    header("Location: login.php");
    exit;
}

$receiver_id = $_SESSION['user_id'];
$receiver_name = $_SESSION['name'] ?? 'Receiver User';
$success_message = '';
$error_message = '';

// Retrieve flash messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
// FETCH RECEIVER'S OWN HISTORY FROM requests TABLE
// (All Accepted + Pending, excluding rejected)
$sql = "
    SELECT request_id, post_id, request_status, delivery_address, requested_at
    FROM requests
    WHERE receiver_id = ?
      AND request_status != 'Rejected'
    ORDER BY requested_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$receiver_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Request History</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="container"><nav class="navbar"><a href="index.html" class="logo">Food<span>Saver</span></a><ul class="nav-links"><li><a href="index.html" class="btn btn-secondary">Home</a></li><li><a href="auth.php?action=logout" class="btn btn-secondary">Logout</a></li></ul></nav></div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=R" alt="Profile" class="profile-pic">
                <h4><?php echo htmlspecialchars($receiver_name); ?></h4><p class="user-role">Receiver Account</p></div>
            <nav class="sidebar-nav"><ul>
                <li><a href="receiver_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</a></li> 
                <li><a href="receiver_available_food.php"><i class="fas fa-list-ul"></i> Available Food</a></li>
                <li><a href="receiver_history.php" class="active"><i class="fas fa-history"></i> Request History</a></li>
            </ul></nav>
        </aside>

    <main class="main-content">
    
        <div class="card animate-on-load" style="padding: 0;">
         <h3 style="padding: 20px; border-bottom: 1px solid #eee; color: var(--color-primary);">My Request History</h3>

        <?php if (empty($history)) { ?>
           <div class="no-data">You have no request history yet.</div>

         <?php } else { ?>
         <table class="data-table">
            <tr>
               <th>Request ID</th>
               <th>Post ID</th>
               <th>Request Status</th>
               <th>Delivery Address</th>
               <th>Requested At</th>
            </tr>

            <?php foreach ($history as $row) { ?>
            <tr>
               <td><?= $row['request_id'] ?></td>
               <td><?= $row['post_id'] ?></td>
               <td><?= htmlspecialchars($row['request_status']) ?></td>
               <td><?= htmlspecialchars($row['delivery_address']) ?></td>
               <td><?= htmlspecialchars($row['requested_at']) ?></td>
            </tr>
            <?php } ?>

         </table>
         <?php } ?>
        </div>
    </main>
</body>
</html>
