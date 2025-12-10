<?php
// track_donation.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: login.php");
    exit;
}

$donor_id = $_SESSION['user_id'];
$donor_name = $_SESSION['name'];

$success_message = '';
$error_message = '';
$posts = [];

// --- DELETE POST Handler ---
if (isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    $post_id_to_delete = (int)($_POST['post_id'] ?? 0);

    if ($post_id_to_delete > 0 && isset($pdo)) {
        try {
            // Check if post status allows deletion (only 'Pending Approval' can be deleted)
            $stmt_check = $pdo->prepare("SELECT status FROM food_posts WHERE post_id = ? AND donor_id = ?");
            $stmt_check->execute([$post_id_to_delete, $donor_id]);
            $current_status = $stmt_check->fetchColumn();

            if ($current_status === 'Pending Approval') {
                $pdo->beginTransaction();
                
                // Delete related requests first
                $pdo->prepare("DELETE FROM requests WHERE post_id = ?")->execute([$post_id_to_delete]);
                
                // Delete the post
                $stmt = $pdo->prepare("DELETE FROM food_posts WHERE post_id = ? AND donor_id = ?");
                $stmt->execute([$post_id_to_delete, $donor_id]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Post deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Could not delete post.";
                }
                $pdo->commit();
            } else {
                $_SESSION['error_message'] = "Post can only be deleted if its status is 'Pending Approval'. Current status: " . htmlspecialchars($current_status);
            }

            header("Location: track_donation.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Deletion failed: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid post ID or database connection missing.";
    }
}

// --- EDIT POST Handler ---
if (isset($_POST['action']) && $_POST['action'] === 'edit_post') {
    $post_id_to_edit = (int)($_POST['post_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? null;
    $location = trim($_POST['location'] ?? '');

    if ($post_id_to_edit <= 0 || empty($title) || empty($description) || empty($location) || $quantity <= 0) {
        $error_message = "Invalid data provided for post update.";
    } elseif (isset($pdo)) {
        try {
            // Only allow update if status is 'Pending Approval'
            $stmt = $pdo->prepare("UPDATE food_posts SET 
                title = ?, description = ?, category = ?, quantity_kg = ?, expiry_date = ?, pickup_location = ?
                WHERE post_id = ? AND donor_id = ? AND status = 'Pending Approval'");
            $stmt->execute([$title, $description, $category, $quantity, $expiry_date, $location, $post_id_to_edit, $donor_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Donation post updated successfully!";
            } else {
                $_SESSION['error_message'] = "Post could not be updated. Ensure it is Pending Approval and belongs to you.";
            }

            header("Location: track_donation.php");
            exit;
        } catch (PDOException $e) {
            $error_message = "Update failed: " . $e->getMessage();
        }
    }
}

// --- Flash Messages ---
if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error_message = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// --- Fetch Posts (Fetching all columns needed for display and details) ---
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT post_id, title, category, quantity_kg, expiry_date, status, pickup_location, description FROM food_posts WHERE donor_id = ? ORDER BY created_at DESC");
        $stmt->execute([$donor_id]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- FETCH VOLUNTEER/RECEIVER DETAILS FOR TRACKING ---
        foreach ($posts as &$post) {
            $post['volunteer_name'] = 'N/A';
            $post['receiver_name'] = 'N/A';
            
            if ($post['status'] !== 'Pending Approval' && $post['status'] !== 'Rejected') {
                $stmt_details = $pdo->prepare("
                    SELECT 
                        u_vol.name AS volunteer_name, 
                        u_rec.name AS receiver_name 
                    FROM assignments a
                    JOIN users u_vol ON a.volunteer_id = u_vol.user_id
                    JOIN users u_rec ON a.receiver_id = u_rec.user_id
                    WHERE a.post_id = ?
                    LIMIT 1
                ");
                $stmt_details->execute([$post['post_id']]);
                $details = $stmt_details->fetch(PDO::FETCH_ASSOC);

                if ($details) {
                    $post['volunteer_name'] = htmlspecialchars($details['volunteer_name']);
                    $post['receiver_name'] = htmlspecialchars($details['receiver_name']);
                }
            }
        }
        unset($post); // Break the reference with the last element
    } catch (PDOException $e) {
        $error_message = "Could not fetch posts: " . $e->getMessage();
    }
}

// Fallback to empty array if no posts
$data_to_show = $posts ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track My Donation - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* Status pills */
.post-status { display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.85em; font-weight:bold; }
.status-pending-approval { background-color:#FFC107; color:#333; }
.status-assigned { background-color:#FF9800; color:white; }
.status-picked-up { background-color:#2196F3; color:white; } /* New status pill */
.status-delivered { background-color:#00BCD4; color:white; }
.status-rejected { background-color:#F44336; color:white; }

/* Modal styles (General for both edit and details) */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1000; }
.modal-overlay .card { width:400px; padding:20px; background:white; border-radius:10px; transform:scale(0.8); transition:0.3s; }
.modal-overlay.active { display:flex; }
.modal-overlay.active .card { transform:scale(1); }
.modal-overlay h3 { margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.modal-overlay label { display: block; font-weight: bold; margin-top: 10px; }
.modal-overlay input, .modal-overlay select, .modal-overlay textarea { width:100%; padding:8px; margin:5px 0; box-sizing:border-box; }
.modal-overlay button { cursor:pointer; }
</style>
</head>
<body>

<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="index.html" class="logo">Food<span>Saver</span></a>
            <ul class="nav-links">
                <li><a class="btn btn-secondary" href="index.html">Home</a></li>
                <li><a class="btn btn-secondary" href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="dashboard-layout">
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/profile-pic.png" alt="Profile" class="profile-pic">
        <h4 style="color: var(--color-text-light); margin-top:10px;"><?php echo htmlspecialchars($donor_name); ?></h4>
        <p class="user-role">Donor Account</p>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="donor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="post_creation.php"><i class="fas fa-cloud-upload-alt"></i> New Donation</a></li>
            <li><a href="track_donation.php" class="active"><i class="fas fa-route"></i> Track Donation</a></li>
            <li><a href="donation_history.php"><i class="fas fa-history"></i> Donation History</a></li>
        </ul>
    </nav>
</aside>

<main class="main-content">
    <?php if ($success_message): ?>
        <div class="card" style="background-color:#e8f5e9;color:#388e3c;padding:15px;margin-bottom:20px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="card" style="background-color:#ffebee;color:#d32f2f;padding:15px;margin-bottom:20px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card animate-on-load" style="padding:0;">
        <h3 style="padding:20px; border-bottom:1px solid #eee; color:var(--color-primary);">📦 Track My Donation</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data_to_show as $post): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($post['title']); ?></td>
                        <td><?php echo htmlspecialchars($post['category']); ?></td>
                        <td><?php echo htmlspecialchars($post['quantity_kg']); ?> kg</td>
                        <td><?php echo htmlspecialchars($post['expiry_date']); ?></td>
                        <td>
                            <span class="post-status status-<?php echo strtolower(str_replace(' ', '-', $post['status'])); ?>">
                                <?php echo htmlspecialchars($post['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php $post_id = $post['post_id']; ?>
                            <?php if ($post['status'] === 'Pending Approval'): ?>
                                <button class="btn" style="padding:8px 15px;background:#FF9800;color:white;margin-right:5px;"
                                    onclick="openEditModal(<?php echo $post_id;?>, '<?php echo addslashes($post['title']); ?>', '<?php echo addslashes($post['description']); ?>', '<?php echo $post['category']; ?>', '<?php echo $post['quantity_kg']; ?>', '<?php echo $post['expiry_date']; ?>', '<?php echo addslashes($post['pickup_location']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this post? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                    <button type="submit" class="btn" style="padding:8px 15px;background:#f44336;color:white;"><i class="fas fa-times"></i> Delete</button>
                                </form>
                            <?php else: 
                                // Prepare data attributes for JavaScript
                                $details_data = [
                                    'title' => $post['title'],
                                    'description' => $post['description'],
                                    'category' => $post['category'],
                                    'quantity_kg' => $post['quantity_kg'],
                                    'expiry_date' => $post['expiry_date'],
                                    'status' => $post['status'],
                                    'pickup_location' => $post['pickup_location'],
                                    'volunteer_name' => $post['volunteer_name'],
                                    'receiver_name' => $post['receiver_name']
                                ];
                                $json_data = htmlspecialchars(json_encode($details_data), ENT_QUOTES, 'UTF-8');
                            ?>
                                <button class="btn" style="padding:8px 15px;background:#607D8B;color:white;" onclick="openDetailsModal(<?php echo $json_data; ?>)">
                                    <i class="fas fa-search"></i> Details
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</div>

<div id="edit-post-modal" class="modal-overlay">
    <div class="card">
        <h3>Edit Donation Post</h3>
        <form method="POST" action="track_donation.php">
            <input type="hidden" name="action" value="edit_post">
            <input type="hidden" name="post_id" id="edit-post-id">
            <label>Title</label>
            <input type="text" name="title" id="edit-title" required>
            <label>Description</label>
            <textarea name="description" id="edit-description" required></textarea>
            <label>Category</label>
            <select name="category" id="edit-category" required>
                <option value="Produce">Produce</option>
                <option value="Baked Goods">Baked Goods</option>
                <option value="Dairy">Dairy & Eggs</option>
                <option value="Meals">Meals</option>
                <option value="Canned Goods">Canned Goods</option>
                <option value="Other">Other</option>
            </select>
            <label>Quantity (kg)</label>
            <input type="number" step="0.01" name="quantity" id="edit-quantity" required>
            <label>Expiry Date</label>
            <input type="date" name="expiry_date" id="edit-expiry-date">
            <label>Pickup Location</label>
            <input type="text" name="location" id="edit-location" required>
            <div style="text-align:right;margin-top:10px;">
                <button type="button" onclick="closeEditModal()">Cancel</button>
                <button type="submit" style="background:#FF9800;color:white;padding:8px 15px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="details-post-modal" class="modal-overlay">
    <div class="card">
        <h3>Donation Tracking Details</h3>
        <h4 id="details-title" style="color:var(--color-primary); margin-top: 0; margin-bottom: 15px;"></h4>
        
        <p><strong>Status:</strong> <span id="details-status"></span></p>
        <p><strong>Quantity:</strong> <span id="details-quantity"></span> kg</p>
        <p><strong>Expiry Date:</strong> <span id="details-expiry-date"></span></p>
        <p><strong>Category:</strong> <span id="details-category"></span></p>
        
        <hr style="margin: 15px 0;">

        <p><strong>Pickup Location:</strong> <span id="details-pickup-location"></span></p>
        <p><strong>Assigned Receiver:</strong> <span id="details-receiver-name"></span></p>
        <p><strong>Assigned Volunteer:</strong> <span id="details-volunteer-name"></span></p>
        
        <hr style="margin: 15px 0;">

        <label>Description:</label>
        <p id="details-description" style="white-space: pre-wrap; margin-top: 5px;"></p>

        <div style="text-align:right;margin-top:10px;">
            <button style="background-color: #FF9800; color: white; padding: 8px 15px; border-radius: 5px;" type="button" onclick="closeDetailsModal()">Close</button>
        </div>
    </div>
</div>

<script>
const editPostModal = document.getElementById('edit-post-modal');
const detailsPostModal = document.getElementById('details-post-modal');

// --- Edit Modal Functions (Minor update to use class) ---
function openEditModal(post_id, title, description, category, quantity, expiry_date, location){
    document.getElementById('edit-post-id').value = post_id;
    document.getElementById('edit-title').value = title;
    document.getElementById('edit-description').value = description;
    document.getElementById('edit-category').value = category;
    document.getElementById('edit-quantity').value = quantity;
    document.getElementById('edit-expiry-date').value = expiry_date;
    
    // Unescape location which may contain quotes/slashes
    const tempElement = document.createElement('textarea');
    tempElement.innerHTML = location;
    document.getElementById('edit-location').value = tempElement.value;

    editPostModal.classList.add('active');
}

function closeEditModal(){
    editPostModal.classList.remove('active');
}


// --- NEW Details Modal Functions ---
function openDetailsModal(data){
    // Update modal content
    document.getElementById('details-title').textContent = data.title;
    document.getElementById('details-status').textContent = data.status;
    document.getElementById('details-status').className = 'post-status status-' + data.status.toLowerCase().replace(' ', '-');
    document.getElementById('details-quantity').textContent = data.quantity_kg;
    document.getElementById('details-expiry-date').textContent = data.expiry_date;
    document.getElementById('details-category').textContent = data.category;
    document.getElementById('details-pickup-location').textContent = data.pickup_location;
    document.getElementById('details-receiver-name').textContent = data.receiver_name;
    document.getElementById('details-volunteer-name').textContent = data.volunteer_name;
    document.getElementById('details-description').textContent = data.description; // Preserve line breaks

    detailsPostModal.classList.add('active');
}

function closeDetailsModal(){
    detailsPostModal.classList.remove('active');
}

// Close modals when clicking outside
window.onclick = function(event){
    if(event.target === editPostModal) closeEditModal();
    if(event.target === detailsPostModal) closeDetailsModal();
}
</script>

</body>
</html>