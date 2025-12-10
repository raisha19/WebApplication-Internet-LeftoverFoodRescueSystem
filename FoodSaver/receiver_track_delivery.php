<?php
// receiver_track.php - Allows a receiver to view and track the status of their assigned deliveries.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

// --- 1. AUTHENTICATION: Check for Receiver Role ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receiver') {
    // Redirect non-receiver users or unauthenticated users to login page
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

$error_message = '';
$assigned_posts = [];

// --- 2. FETCH ASSIGNED POSTS ---
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                fp.post_id, fp.title, fp.category, fp.quantity_kg, fp.expiry_date, fp.status, fp.pickup_location, fp.description, 
                u_donor.name AS donor_name,
                u_vol.name AS volunteer_name
            FROM assignments a
            JOIN food_posts fp ON a.post_id = fp.post_id
            JOIN users u_donor ON fp.donor_id = u_donor.user_id
            JOIN users u_vol ON a.volunteer_id = u_vol.user_id
            WHERE a.receiver_id = ?
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $assigned_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Could not fetch assigned posts: " . $e->getMessage();
    }
}

$data_to_show = $assigned_posts ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receiver - Track Deliveries - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* Status pills */
.post-status { display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.85em; font-weight:bold; }
/* Only show statuses applicable after assignment */
.status-assigned { background-color:#FF9800; color:white; }
.status-picked-up { background-color:#2196F3; color:white; } 
.status-delivered { background-color:#00BCD4; color:white; }
.status-rejected, .status-pending-approval { background-color:#F44336; color:white; } /* Should not appear, but styled for safety */

/* Modal styles */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1000; }
.modal-overlay .card { width:400px; max-width: 90%; padding:20px; background:white; border-radius:10px; transform:scale(0.8); transition:0.3s; }
.modal-overlay.active { display:flex; }
.modal-overlay.active .card { transform:scale(1); }
.modal-overlay h3 { margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.modal-overlay label { display: block; font-weight: bold; margin-top: 10px; }
.modal-overlay p { margin: 5px 0; }

/* Responsive Table */
@media screen and (max-width: 768px) {
    .data-table, .data-table thead, .data-table tbody, .data-table th, .data-table td, .data-table tr { 
        display: block; 
    }
    .data-table thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    .data-table tr { border: 1px solid #ccc; margin-bottom: 10px; display: flex; flex-direction: column;}
    .data-table td { 
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        padding-left: 50%; 
        text-align: right;
    }
    .data-table td:before { 
        position: absolute;
        top: 6px;
        left: 6px;
        width: 45%; 
        padding-right: 10px; 
        white-space: nowrap;
        text-align: left;
        font-weight: bold;
    }
    .data-table td:nth-of-type(1):before { content: "Title"; }
    .data-table td:nth-of-type(2):before { content: "Donor"; }
    .data-table td:nth-of-type(3):before { content: "Volunteer"; }
    .data-table td:nth-of-type(4):before { content: "Quantity"; }
    .data-table td:nth-of-type(5):before { content: "Status"; }
    .data-table td:nth-of-type(6):before { content: "Action"; }
}
</style>
</head>
<body>

<header class="header">
    <div class="container">
        <nav class="navbar">
            <a href="index.html" class="logo">Food<span>Saver</span></a>
            <ul class="nav-links">
                <li><a class="btn btn-secondary" href="receiver_dashboard.php">Receiver Home</a></li>
                <li><a class="btn btn-secondary" href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="dashboard-layout">
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/profile-pic.png" alt="Profile" class="profile-pic">
        <h4 style="color: var(--color-text-light); margin-top:10px;"><?php echo htmlspecialchars($user_name); ?></h4>
        <p class="user-role">Receiver</p>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="receiver_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="receiver_available_food.php"><i class="fas fa-list-alt"></i> Available Donations</a></li>
            <li><a href="receiver_track.php" class="active"><i class="fas fa-truck"></i> Track Deliveries</a></li>
        </ul>
    </nav>
</aside>

<main class="main-content">
    <?php if ($error_message): ?>
        <div class="card" style="background-color:#ffebee;color:#d32f2f;padding:15px;margin-bottom:20px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card animate-on-load" style="padding:0;">
        <h3 style="padding:20px; border-bottom:1px solid #eee; color:var(--color-primary);">🚚 My Assigned Donations (Delivery Tracker)</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Donor</th>
                    <th>Volunteer</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data_to_show as $post): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($post['title']); ?></td>
                        <td><?php echo htmlspecialchars($post['donor_name']); ?></td>
                        <td><?php echo htmlspecialchars($post['volunteer_name']); ?></td>
                        <td><?php echo htmlspecialchars($post['quantity_kg']); ?> kg</td>
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
                                    'volunteer_name' => $post['volunteer_name'],
                                    'description' => $post['description'],
                                    'category' => $post['category'],
                                    'quantity_kg' => $post['quantity_kg'],
                                    'expiry_date' => $post['expiry_date'],
                                    'status' => $post['status'],
                                    'pickup_location' => $post['pickup_location']
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
            <p style="text-align: center; padding: 20px; color: #888;">You have no assigned deliveries currently being tracked.</p>
            <div style="text-align: center; padding-bottom: 20px;">
                <a href="receiver_available_posts.php" class="btn btn-primary"><i class="fas fa-list"></i> View Available Donations</a>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- DETAILS MODAL -->
<div id="details-post-modal" class="modal-overlay">
    <div class="card">
        <h3>Delivery Tracking Details</h3>
        <h4 id="details-title" style="color:var(--color-primary); margin-top: 0; margin-bottom: 15px;"></h4>
        
        <p><strong>Current Status:</strong> <span id="details-status"></span></p>
        <p><strong>Assigned Volunteer:</strong> <span id="details-volunteer-name"></span></p>
        <p><strong>Donor:</strong> <span id="details-donor-name"></span></p>
        
        <hr style="margin: 15px 0;">

        <p><strong>Pickup Location:</strong> <span id="details-pickup-location"></span></p>
        <p><strong>Quantity:</strong> <span id="details-quantity"></span> kg</p>
        <p><strong>Category:</strong> <span id="details-category"></span></p>
        <p><strong>Expiry Date:</strong> <span id="details-expiry-date"></span></p>
        
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
    document.getElementById('details-volunteer-name').textContent = data.volunteer_name;
    
    document.getElementById('details-status').textContent = data.status;
    // Apply status styling
    document.getElementById('details-status').className = 'post-status status-' + data.status.toLowerCase().replace(' ', '-'); 

    document.getElementById('details-quantity').textContent = data.quantity_kg;
    document.getElementById('details-expiry-date').textContent = data.expiry_date;
    document.getElementById('details-category').textContent = data.category;
    document.getElementById('details-pickup-location').textContent = data.pickup_location;
    document.getElementById('details-description').textContent = data.description;

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