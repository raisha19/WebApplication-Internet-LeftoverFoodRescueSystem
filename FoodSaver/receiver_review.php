<?php
// receiver_review.php (UPDATED TO TARGET food_posts TABLE)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); // Assumes this provides the $pdo object

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    header("Location: login.php");
    exit;
}

$receiver_id = $_SESSION['user_id'];
$receiver_name = $_SESSION['name'] ?? 'Receiver User';

$success_message = '';
$error_message = '';

// --- Handle Review Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) { // Changed from assignment_id to post_id
    $post_id = (int)$_POST['post_id'];
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    // Basic validation
    if ($rating < 1 || $rating > 5) {
        $error_message = "Please select a rating between 1 and 5 stars.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE food_posts 
                SET rating=?, review_comment=?, receiver_reviewed=TRUE 
                WHERE post_id=? AND status='Delivered'
            "); // Targets food_posts table
            $stmt->execute([$rating, $comment, $post_id]);
            
            $success_message = "Thank you for your review! Your feedback has been submitted.";
            header("Location: receiver_review.php?success=1");
            exit;

        } catch (PDOException $e) {
            $error_message = "Submission failed: " . $e->getMessage();
        }
    }
}

// Check for success redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Thank you for your review! Your feedback has been submitted.";
}


// --- Fetch Delivered & Unreviewed Tasks ---
// NOTE: We rely on the ASSIGNMENTS table to know WHICH receiver received it.
$stmt = $pdo->prepare("
    SELECT 
        fp.post_id, fp.title, fp.category, fp.quantity_kg, fp.created_at,
        u_donor.name AS donor_name, u_volunteer.name AS volunteer_name
    FROM food_posts fp
    JOIN users u_donor ON fp.donor_id = u_donor.user_id
    
    -- Join assignments to find the volunteer and confirm THIS receiver was assigned it
    JOIN assignments a ON fp.post_id = a.post_id AND a.receiver_id = ? 
    JOIN users u_volunteer ON a.volunteer_id = u_volunteer.user_id
    
    WHERE 
        fp.status = 'Delivered' AND 
        fp.receiver_reviewed = FALSE
    ORDER BY fp.created_at DESC
");
$stmt->execute([$receiver_id]);
$tasks_to_review = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Deliveries - Receiver Dashboard</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    .star-rating { unicode-bidi: bidi-override; direction: rtl; text-align: center; }
    .star-rating > input { display: none; }
    .star-rating > label { font-size: 30px; color: #ccc; cursor: pointer; padding: 0 5px; }
    .star-rating > input:checked ~ label, 
    .star-rating > label:hover,
    .star-rating > label:hover ~ label { color: gold; }
    .review-form-card { margin-bottom: 25px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
</style>
</head>
<body>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="receiver_dashboard.php" class="logo">Food<span>Saver</span></a>
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
            <h4><?= htmlspecialchars($receiver_name); ?></h4>
            <p class="user-role">Receiver Account</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="receiver_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="receiver_request_food.php"><i class="fas fa-plus-circle"></i> Request Food</a></li>
                <li><a href="receiver_review.php" class="active"><i class="fas fa-star"></i> Leave Review</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>Review Your Recent Deliveries</h2>
        </div>

        <?php if ($success_message): ?>
            <div class="card" style="background-color: #e8f5e9; border-left: 5px solid #388e3c; color: #388e3c; padding: 15px; margin-bottom: 20px;"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="card" style="background-color: #ffebee; border-left: 5px solid #d32f2f; color: #d32f2f; padding: 15px; margin-bottom: 20px;"><?= $error_message ?></div>
        <?php endif; ?>

        <section>
            <?php if (empty($tasks_to_review)): ?>
                <div class="card" style="text-align:center; padding:50px; background:#f7f7f7;">
                    <i class="fas fa-box-check" style="font-size: 3rem; color: #2196F3;"></i>
                    <h4>You have no recent deliveries awaiting review.</h4>
                </div>
            <?php else: ?>
                <?php foreach ($tasks_to_review as $task): ?>
                    <div class="card review-form-card">
                        <h3>Review: <?= htmlspecialchars($task['title']); ?> (<?= htmlspecialchars($task['quantity_kg']); ?> kg)</h3>
                        <p><strong>Donated by:</strong> <?= htmlspecialchars($task['donor_name']); ?></p>
                        <p><strong>Delivered by:</strong> <?= htmlspecialchars($task['volunteer_name']); ?></p>
                        <p><strong>Donation Date:</strong> <?= date('F j, Y', strtotime($task['created_at'])); ?></p>

                        <form method="POST" action="receiver_review.php">
                            <input type="hidden" name="post_id" value="<?= $task['post_id']; ?>">
                            
                            <div class="form-group" style="margin-top: 15px;">
                                <label for="rating_<?= $task['post_id']; ?>">Your Rating (Required):</label>
                                <div class="star-rating" id="rating_<?= $task['post_id']; ?>">
                                    <input type="radio" id="star5_<?= $task['post_id']; ?>" name="rating" value="5" required><label for="star5_<?= $task['post_id']; ?>" title="5 stars">&#9733;</label>
                                    <input type="radio" id="star4_<?= $task['post_id']; ?>" name="rating" value="4"><label for="star4_<?= $task['post_id']; ?>" title="4 stars">&#9733;</label>
                                    <input type="radio" id="star3_<?= $task['post_id']; ?>" name="rating" value="3"><label for="star3_<?= $task['post_id']; ?>" title="3 stars">&#9733;</label>
                                    <input type="radio" id="star2_<?= $task['post_id']; ?>" name="rating" value="2"><label for="star2_<?= $task['post_id']; ?>" title="2 stars">&#9733;</label>
                                    <input type="radio" id="star1_<?= $task['post_id']; ?>" name="rating" value="1"><label for="star1_<?= $task['post_id']; ?>" title="1 star">&#9733;</label>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 25px;">
                                <label for="comment_<?= $task['post_id']; ?>">Comments (Optional):</label>
                                <textarea id="comment_<?= $task['post_id']; ?>" name="comment" rows="3" placeholder="Share your experience (e.g., quality of food, volunteer friendliness)..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Submit Review</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>