<?php
// volunteer_claim_task.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Session must be started here, or via an include, to check for user_id
include('db_connect.php'); // Assumes this provides the $pdo object

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'volunteer') {
    header("Location: login.php");
    exit;
}

$volunteer_id = $_SESSION['user_id'];
$volunteer_name = $_SESSION['name'] ?? 'Volunteer User'; 

$success_message = '';
$error_message = '';

// Claim task action (Atomic Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = (int)$_POST['post_id'];

    try {
        $pdo->beginTransaction();

        // 1. ATOMIC CHECK & LOCK: Update food_posts ONLY if it is currently 'Requested'.
        // If 0 rows are affected, another volunteer claimed it first.
        $stmt_update_post = $pdo->prepare("UPDATE food_posts SET status='Assigned' WHERE post_id=? AND status='Requested'");
        $stmt_update_post->execute([$post_id]);
        
        // Check if the update succeeded (i.e., exactly 1 row was changed).
        if ($stmt_update_post->rowCount() === 0) {
            $error_message = "Task ID $post_id is already claimed or no longer available.";
            $pdo->rollBack();
        } else {
            // 2. Task is successfully locked as 'Assigned'. Find the pending request.
            $stmt_request = $pdo->prepare("SELECT receiver_id FROM requests WHERE post_id=? AND request_status='Pending' ORDER BY requested_at ASC LIMIT 1");
            $stmt_request->execute([$post_id]);
            $request_data = $stmt_request->fetch(PDO::FETCH_ASSOC);

            if ($request_data) {
                $receiver_id = $request_data['receiver_id'];

                // 3. Insert the assignment
                $stmt_assign = $pdo->prepare("INSERT INTO assignments (post_id, volunteer_id, receiver_id, assignment_date, volunteer_status) VALUES (?, ?, ?, CURDATE(), 'Accepted')");
                $stmt_assign->execute([$post_id, $volunteer_id, $receiver_id]);

                // 4. Update the request status
                $pdo->prepare("UPDATE requests SET request_status='Accepted' WHERE post_id=? AND receiver_id=?")->execute([$post_id, $receiver_id]);

                $success_message = "You have successfully claimed Post ID $post_id! It has been moved to My Assignments.";
                $pdo->commit(); // Finalize the transaction
            } else {
                // If the post status was updated but no request was found, roll back.
                $error_message = "Critical Error: Post was locked, but no matching request was found.";
                $pdo->rollBack();
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Claim failed (DB Error): " . $e->getMessage();
    }
}

// Fetch available tasks (only those with status='Requested' will show up)
$stmt_available = $pdo->prepare("
    SELECT fp.post_id, fp.title, fp.category, fp.quantity_kg, fp.pickup_location, u.name AS donor_name
    FROM food_posts fp
    JOIN users u ON fp.donor_id = u.user_id
    JOIN requests r ON fp.post_id = r.post_id
    WHERE fp.status='Requested' AND r.request_status='Pending'
    GROUP BY fp.post_id
    ORDER BY fp.created_at ASC
");
$stmt_available->execute();
$available_tasks = $stmt_available->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Claim Tasks - Volunteer Dashboard</title>
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
                <li><a href="volunteer_claim_task.php" class="active"><i class="fas fa-search-location"></i> Claim Task</a></li>
                <li><a href="volunteer_my_assignments.php"><i class="fas fa-route"></i> My Assignments</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>Available Tasks to Claim</h2>
        </div>

        <?php if ($success_message): ?>
            <div class="card" style="background-color: #e8f5e9; border-left: 5px solid #388e3c; color: #388e3c; padding: 15px; margin-bottom: 20px;"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="card" style="background-color: #ffebee; border-left: 5px solid #d32f2f; color: #d32f2f; padding: 15px; margin-bottom: 20px;"><?= $error_message ?></div>
        <?php endif; ?>

        <section>
            <?php if (empty($available_tasks)): ?>
                <div class="card" style="text-align:center; padding:50px; background:#f7f7f7;">
                    <i class="fas fa-check" style="font-size: 3rem; color: #4CAF50;"></i>
                    <h4>No tasks available to claim.</h4>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($available_tasks as $task): ?>
                        <div class="card">
                            <h4><?= htmlspecialchars($task['title']); ?> (<?= htmlspecialchars($task['quantity_kg']); ?> kg)</h4>
                            <p><strong>Category:</strong> <?= htmlspecialchars($task['category']); ?></p>
                            <p><strong>Donor:</strong> <?= htmlspecialchars($task['donor_name']); ?></p>
                            <p><strong>Pickup Location:</strong> <?= htmlspecialchars($task['pickup_location']); ?></p>

                            <form method="POST" action="" style="margin-top: 10px; text-align:right;">
                                <input type="hidden" name="post_id" value="<?= $task['post_id']; ?>">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-hand-paper"></i> Claim Task</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>