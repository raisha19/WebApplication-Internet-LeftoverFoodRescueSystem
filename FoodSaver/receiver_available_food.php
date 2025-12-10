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


// --- 2. PHP HANDLER FOR REQUEST ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    // NEW: Capture the delivery address from the POST data
    $delivery_address = trim($_POST['delivery_address'] ?? '');

    if ($post_id > 0 && !empty($delivery_address)) {
        try {
            // Check if the receiver has already requested this post
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE post_id = ? AND receiver_id = ?");
            $stmt_check->execute([$post_id, $receiver_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_message = "You have already requested this item (Post ID $post_id).";
            } else {
                // 1. Insert the request into the 'requests' table, including the delivery_address
                $stmt_insert = $pdo->prepare("
                    INSERT INTO requests (post_id, receiver_id, delivery_address, request_status) 
                    VALUES (?, ?, ?, 'Pending')
                ");
                // The new parameter is $delivery_address
                $stmt_insert->execute([$post_id, $receiver_id, $delivery_address]);

                // 2. Update the 'food_posts' status to 'Requested'
                // This ensures that an Approved post is immediately flagged as 'Requested' and disappears from the main list.
                $stmt_update = $pdo->prepare("UPDATE food_posts SET status = 'Requested' WHERE post_id = ? AND status = 'Approved'");
                $stmt_update->execute([$post_id]);

                if ($stmt_update->rowCount() > 0) {
                    $success_message = "Request for Post ID $post_id submitted successfully! Delivery Address: " . htmlspecialchars($delivery_address);
                } else {
                    $error_message = "Request submitted, but post status was not updated (might have been requested by another receiver just now).";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database action failed: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid Post ID or Delivery Address for request.";
    }

    // Flash messages to session for display on reload (PRG pattern)
    if (!empty($success_message)) $_SESSION['success_message'] = $success_message;
    if (!empty($error_message)) $_SESSION['error_message'] = $error_message;

    // Redirect to clear POST data
    header("Location: receiver_available_food.php");
    exit;
}


// --- 3. DATA FETCHING: Fetch Available (Approved) Food Posts ---
try {
    // Select posts that are 'Approved' (meaning no one has requested them yet) and are not expired
    $stmt = $pdo->prepare("
        SELECT 
            fp.post_id, fp.title, fp.description, fp.category, fp.quantity_kg, fp.expiry_date, fp.pickup_location, fp.delivery_address,
            u.name AS donor_name
        FROM 
            food_posts fp
        JOIN 
            users u ON fp.donor_id = u.user_id
        WHERE 
            fp.status = 'Approved' 
            AND fp.expiry_date >= CURDATE() -- Only show food that hasn't expired
        ORDER BY 
            fp.created_at DESC
    ");
    $stmt->execute();
    $available_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Additionally, fetch the IDs of posts the current receiver has already requested
    $stmt_requested = $pdo->prepare("SELECT post_id FROM requests WHERE receiver_id = ? AND request_status = 'Pending'");
    $stmt_requested->execute([$receiver_id]);
    $requested_post_ids = $stmt_requested->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message .= " | Available posts fetch error: " . $e->getMessage();
    $available_posts = [];
    $requested_post_ids = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Food - FoodSaver</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Basic Modal Styles (Add these to your style.css or here) */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Crucial for padding/border calculation */
        }
    </style>
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
                <h4><?php echo htmlspecialchars($receiver_name); ?></h4><p class="user-role">Receiver Account</p></div>
            <nav class="sidebar-nav"><ul>
                <li><a href="receiver_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</a></li> 
                <li><a href="receiver_available_food.php" class="active"><i class="fas fa-list-ul"></i> Available Food</a></li>
                <li><a href="receiver_review.php"><i class="fas fa-star"></i> Review Food</a></li>
                <li><a href="receiver_history.php"><i class="fas fa-history"></i> Request History</a></li>
            </ul></nav>
        </aside>

        <main class="main-content">
            <div class="dashboard-header animate-on-load">
                <h2>Available Food Listings 🍎</h2>
                <p>Browse posts that have been approved and are ready for request.</p>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="card" style="background-color: #e8f5e9; border-left: 5px solid var(--color-primary); color: #388e3c; padding: 15px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="card" style="background-color: #ffebee; border-left: 5px solid #d32f2f; color: #d32f2f; padding: 15px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <section id="available-posts">
                <h3 style="color: var(--color-primary); margin-bottom: 20px;"><i class="fas fa-utensils"></i> Ready to Request (<?php echo count($available_posts); ?>)</h3>
                
                <?php if (empty($available_posts)): ?>
                    <div class="card" style="text-align: center; padding: 30px; color: #666;">
                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 15px;"></i>
                        <p>No food posts are currently available for request.</p>
                    </div>
                <?php else: ?>
                    <div class="post-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($available_posts as $post): 
                            $is_requested = in_array($post['post_id'], $requested_post_ids);
                        ?>
                            <div class="card post-card" style="border: 1px solid #ddd; padding: 20px;">
                                <h4><?php echo htmlspecialchars($post['title']); ?> (<?php echo htmlspecialchars($post['quantity_kg']); ?> kg)</h4>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($post['category']); ?></p>
                                <p><strong>Donor:</strong> <?php echo htmlspecialchars($post['donor_name']); ?></p>
                                <p><strong>Expiry Date:</strong> <span style="color: <?php echo (strtotime($post['expiry_date']) < strtotime('+2 days') ? 'red' : '#4CAF50'); ?>; font-weight: bold;"><?php echo htmlspecialchars($post['expiry_date']); ?></span></p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars(substr($post['description'], 0, 100)) . (strlen($post['description']) > 100 ? '...' : ''); ?></p>
                                
                                <div style="margin-top: 15px; text-align: right;">
                                    <?php if ($is_requested): ?>
                                        <button class="btn" disabled style="background-color: #FFC107; color: white;">
                                            <i class="fas fa-hourglass-half"></i> Already Requested
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary request-btn" 
                                                data-post-id="<?php echo htmlspecialchars($post['post_id']); ?>" 
                                                style="padding: 8px 15px;">
                                            <i class="fas fa-hand-pointer"></i> Request Item
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div id="requestModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3 style="color: var(--color-primary); margin-bottom: 20px;">Confirm Request and Delivery Address</h3>
            <form id="requestForm" method="POST" action="receiver_available_food.php">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="post_id" id="modal_post_id" value="">
                
                <div class="form-group">
                    <label for="delivery_address"><i class="fas fa-map-marker-alt"></i> **Your Delivery Address**</label>
                    <textarea id="delivery_address" name="delivery_address" rows="4" required placeholder="Enter the full address where you want the food delivered (Street, City, Postal Code)."></textarea>
                </div>
                
                <p style="margin-top: 20px; font-size: 0.9em; color: #666;">By submitting, you agree to the terms and conditions of receiving the donation.</p>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; margin-top: 10px;">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Get the modal elements
        var modal = document.getElementById("requestModal");
        var span = document.getElementsByClassName("close-btn")[0];

        // Get all request buttons
        var requestButtons = document.querySelectorAll(".request-btn");
        var modalPostIdInput = document.getElementById("modal_post_id");

        // When the user clicks the button, open the modal and set post_id
        requestButtons.forEach(button => {
            button.onclick = function() {
                var postId = this.getAttribute("data-post-id");
                modalPostIdInput.value = postId;
                modal.style.display = "block";
            }
        });

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>