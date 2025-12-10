<?php
// admin_dashboard.php - Admin Control Panel
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

// 1. SECURITY CHECK: Ensure user is logged in AND is an 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect non-admins or guests to login page
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin User';
$success_message = '';
$error_message = '';

// --- 2. PHP HANDLERS FOR ACTIONS (Approve/Reject/Suspend) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0); // Can be post_id or user_id

    if ($id > 0) {
        try {
            if ($action === 'approve_post') {
                // When Admin approves, the status moves from Pending Approval to Approved, ready for receiver/assignment.
                $stmt = $pdo->prepare("UPDATE food_posts SET status = 'Approved' WHERE post_id = ? AND status = 'Pending Approval'");
                $stmt->execute([$id]);
                $success_message = "Post ID $id approved successfully! Now available for request/assignment.";
            } elseif ($action === 'reject_post') {
                $stmt = $pdo->prepare("UPDATE food_posts SET status = 'Rejected' WHERE post_id = ?");
                $stmt->execute([$id]);
                $success_message = "Post ID $id rejected successfully.";
            } elseif ($action === 'suspend_user') {
                // SUSPEND ACTION REMAINS DISABLED (NO 'account_status' COLUMN IN DB)
                $error_message = "Cannot perform user suspension. The required database column 'account_status' is missing from the 'users' table. This feature is disabled.";
            } else {
                $error_message = "Invalid action specified.";
            }
        } catch (PDOException $e) {
            $error_message = "Database action failed: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid ID for action.";
    }

    // Flash messages to session for display on reload (PRG pattern)
    if (!empty($success_message)) $_SESSION['success_message'] = $success_message;
    if (!empty($error_message)) $_SESSION['error_message'] = $error_message;

    // Redirect to clear POST data
    header("Location: admin_dashboard3.php");
    exit;
}

// Retrieve flash messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}




// B. Fetch Pending Posts for Approval
try {
    $stmt = $pdo->query("SELECT fp.post_id, u.name AS donor_name, fp.title, fp.expiry_date, fp.video_url
        FROM food_posts fp
        JOIN users u ON fp.donor_id = u.user_id
        WHERE fp.status = 'Pending Approval'
        ORDER BY fp.created_at ASC");
    $pending_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " | Pending posts fetch error: " . $e->getMessage();
    $pending_posts = [];
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Verification - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reused styles from style.css, but local scope for safety */
        .status-active, .status-suspended { 
            background-color: #e3f2fd; 
            color: #1976D2;             
            padding: 3px 8px; 
            border-radius: 4px; 
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="container"><nav class="navbar"><a href="index.html" class="logo">Food<span>Saver</span></a><ul class="nav-links"><li><a href="index.html" class="btn btn-secondary">Home</a></li><li><a href="auth.php?action=logout" class="btn btn-secondary">Logout</a></li></ul></nav></div>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=A" alt="Profile" class="profile-pic"><h4><?php echo htmlspecialchars($admin_name); ?></h4><p class="user-role">System Administrator</p></div>
            <nav class="sidebar-nav"><ul>
                <li><a href="admin_dashboard3.php"><i class="fas fa-chart-line"></i> Analytics Dashboard</a></li>
                <li><a href="post_verification.php" class="active"><i class="fas fa-clipboard-check"></i> Post Verification</a></li>
                <li><a href="admin_donations_info.php"><i class="fas fa-list-alt"></i> All Donations</a></li>
            </ul></nav>
        </aside>

        <main class="main-content">
            <div class="dashboard-header animate-on-load">
                <h2>Post Verification 💻</h2>
                    <p>Manage and verify posts on the FoodSaver platform.</p>
            </div>
            <section id="approvals" style="margin-bottom: 40px;">
                <h3 style="color: var(--color-primary); margin-bottom: 20px;"><i class="fas fa-video"></i> Pending Post Verification (<?php echo count($pending_posts); ?>)</h3>
                <div class="card" style="padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Donor</th>
                                <th>Item Title</th>
                                <th>Expiry</th>
                                <th>Video Link</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_posts)): ?>
                                <tr><td colspan="6" style="text-align: center; color: #666;">No posts currently require verification. Great job!</td></tr>
                            <?php endif; ?>
                            <?php foreach ($pending_posts as $post): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($post['post_id']); ?></td>
                                    <td><?php echo htmlspecialchars($post['donor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><?php echo htmlspecialchars($post['expiry_date']); ?></td>
                                    <td>
                                        <?php if (!empty($post['video_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($post['video_url']); ?>" target="_blank">View Video</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="post_verification.php" style="display: inline-block; margin: 0;">
                                            <input type="hidden" name="action" value="approve_post">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
                                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px;">Approve</button>
                                        </form>
                                        <form method="POST" action="post_verification.php" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to REJECT Post ID <?php echo htmlspecialchars($post['post_id']); ?>?');">
                                            <input type="hidden" name="action" value="reject_post">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
                                            <button type="submit" class="btn" style="background-color: #F44336; color: white; padding: 5px 10px;">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            

        </main>
    </div>

    <script src="assets/js/script.js"></script>
    
</body>
</html>