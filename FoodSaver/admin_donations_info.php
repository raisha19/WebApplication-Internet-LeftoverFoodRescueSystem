<?php
// admin_posts.php - Displays all donation posts for admin users (DELETE FUNCTIONALITY REMOVED).

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

// --- 1. AUTHENTICATION: Check for Admin Role ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect non-admin users or unauthenticated users to login page
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['name'];

$success_message = '';
$error_message = '';
$posts = [];

// --- DELETE POST Handler REMOVED ---
/* if (isset($_POST['action']) && $_POST['action'] === 'delete_post_admin') {
    // ... delete logic is removed ...
}
*/


// --- Flash Messages ---
if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error_message = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// --- 2. FETCH ALL POSTS (Including Donor Name) ---
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                fp.post_id, fp.title, fp.category, fp.quantity_kg, fp.expiry_date, fp.status, fp.pickup_location, fp.description, 
                u.name AS donor_name, u.user_id AS donor_id
            FROM food_posts fp
            JOIN users u ON fp.donor_id = u.user_id
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute();
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
        unset($post); 
    } catch (PDOException $e) {
        $error_message = "Could not fetch posts: " . $e->getMessage();
    }
}

$data_to_show = $posts ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - All Donations - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* Status pills */
.post-status { display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.85em; font-weight:bold; }
.status-pending-approval { background-color:#FFC107; color:#333; }
.status-assigned { background-color:#FF9800; color:white; }
.status-picked-up { background-color:#2196F3; color:white; } 
.status-delivered { background-color:#00BCD4; color:white; }
.status-rejected { background-color:#F44336; color:white; }

/* Modal styles */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1000; }
.modal-overlay .card { width:400px; padding:20px; background:white; border-radius:10px; transform:scale(0.8); transition:0.3s; }
.modal-overlay.active { display:flex; }
.modal-overlay.active .card { transform:scale(1); }
.modal-overlay h3 { margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.modal-overlay label { display: block; font-weight: bold; margin-top: 10px; }
.modal-overlay p { margin: 5px 0; }
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
        <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=A" alt="Profile" class="profile-pic">
        <h4 style="color: var(--color-text-light); margin-top:10px;"><?php echo htmlspecialchars($admin_name); ?></h4>
        <p class="user-role">Administrator</p>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="admin_dashboard3.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="admin_donations_info.php" class="active"><i class="fas fa-list-alt"></i> All Donations</a></li>
            <li><a href="post_verification.php"><i class="fas fa-clipboard-check"></i> Post Verification</a></li>
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
        <h3 style="padding:20px; border-bottom:1px solid #eee; color:var(--color-primary);">🌍 All Donation Posts</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Title</th>
                    <th>Quantity</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data_to_show as $post): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($post['donor_name']); ?></td>
                        <td><?php echo htmlspecialchars($post['title']); ?></td>
                        <td><?php echo htmlspecialchars($post['quantity_kg']); ?> kg</td>
                        <td><?php echo htmlspecialchars($post['expiry_date']); ?></td>
                        <td>
                            <span class="post-status status-<?php echo strtolower(str_replace(' ', '-', $post['status'])); ?>">
                                <?php echo htmlspecialchars($post['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                // Prepare data attributes for JavaScript
                                $details_data = [
                                    'title' => $post['title'],
                                    'donor_name' => $post['donor_name'],
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
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($data_to_show)): ?>
            <p style="text-align: center; padding: 20px; color: #888;">No donation posts found in the system.</p>
        <?php endif; ?>
    </div>
</main>
</div>

<div id="details-post-modal" class="modal-overlay">
    <div class="card">
        <h3>Donation Tracking Details</h3>
        <h4 id="details-title" style="color:var(--color-primary); margin-top: 0; margin-bottom: 15px;"></h4>
        
        <p><strong>Donor:</strong> <span id="details-donor-name"></span></p>
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
const detailsPostModal = document.getElementById('details-post-modal');

// --- Details Modal Functions ---
function openDetailsModal(data){
    // Update modal content
    document.getElementById('details-title').textContent = data.title;
    document.getElementById('details-donor-name').textContent = data.donor_name;
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
    if(event.target === detailsPostModal) closeDetailsModal();
}
</script>

</body>
</html>