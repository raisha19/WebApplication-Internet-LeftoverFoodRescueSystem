<?php
// donor_dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('db_connect.php'); 

// --- CHECK LOGIN ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header("Location: login.php");
    exit;
}

$donor_id = $_SESSION['user_id'];
$donor_name = $_SESSION['name'];

$success_message = '';
$error_message = '';
$posts = [];

// --- HANDLE NEW POST ---
if (isset($_POST['action']) && $_POST['action'] === 'add_post') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $video_url = '';

    // Video upload
    if (!empty($_FILES['video']['name'])) {
        $target_dir = "uploads/videos/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $video_file = $_FILES['video']['name'];
        $file_tmp = $_FILES['video']['tmp_name'];
        $file_size = $_FILES['video']['size'];
        $file_ext = strtolower(pathinfo($video_file, PATHINFO_EXTENSION));
        $allowed_types = ['mp4','mov','avi','mkv'];

        if (!in_array($file_ext, $allowed_types)) {
            $error_message = "Invalid video format. Only mp4, mov, avi, mkv allowed.";
        } elseif ($file_size > 15 * 1024 * 1024) {
            $error_message = "Video exceeds maximum size of 15MB.";
        } else {
            $video_name = time() . '_' . basename($video_file);
            $target_file = $target_dir . $video_name;
            if (move_uploaded_file($file_tmp, $target_file)) $video_url = $target_file;
            else $error_message = "Failed to upload video.";
        }
    }

    // Validate inputs
    if (empty($title) || empty($description) || empty($location) || $quantity <= 0) {
        $error_message = "Please fill all required fields and ensure quantity > 0.";
    }

    if (empty($error_message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO food_posts 
                (donor_id, title, description, category, quantity_kg, expiry_date, pickup_location, video_url, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Approval')");
            $stmt->execute([$donor_id, $title, $description, $category, $quantity, $expiry_date, $location, $video_url]);

            $_SESSION['success_message'] = "Donation post submitted successfully! Pending Admin Approval.";
            header("Location: donor_dashboard.php");
            exit;
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

// --- FLASH MESSAGES ---
if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error_message = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// --- FETCH DONOR POSTS ---
try {
    $stmt = $pdo->prepare("SELECT * FROM food_posts WHERE donor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$donor_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not fetch posts: " . $e->getMessage();
}

// --- CALCULATE METRICS ---
$total_posts_count = count($posts);
$pending_count = count(array_filter($posts, fn($p) => $p['status'] === 'Pending Approval'));
$transit_count = count(array_filter($posts, fn($p) => $p['status'] === 'Rejected'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Donor Dashboard - FoodSaver</title>
<link rel="stylesheet" href="assets/css/style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* Modal Responsive Enhancements */
.modal-card input, .modal-card textarea, .modal-card select {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    margin-bottom: 15px;
    border-radius: 5px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}
.modal-card label {
    font-weight: bold;
}
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
            <img src="https://placehold.co/60x60/4CAF50/FFFFFF?text=D" alt="Profile" class="profile-pic">
            <h4 style="color: var(--color-text-light); margin-top: 10px;"><?php echo htmlspecialchars($donor_name); ?></h4>
            <p class="user-role">Donor Account</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="post_creation.php"><i class="fas fa-cloud-upload-alt"></i> New Donation</a></li>
                <li><a href="track_donation.php"><i class="fas fa-truck"></i> Track Donation</a></li>
                <li><a href="donation_history.php"><i class="fas fa-history"></i> Donation History</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="dashboard-header animate-on-load">
            <h2>👋 Welcome Back, <?php echo htmlspecialchars($donor_name); ?>!</h2>
            <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Add New Post</button>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="card success-card"><?php echo $success_message;?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="card error-card"><?php echo $error_message;?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card metric-card"><h3><?php echo $total_posts_count; ?></h3><p>Total Posts</p></div>
            <div class="card metric-card"><h3><?php echo $pending_count; ?></h3><p>Pending Approval</p></div>
            <div class="card metric-card"><h3><?php echo $transit_count; ?></h3><p>Rejected</p></div>
        </div>
    </main>
</div>

<!-- NEW POST MODAL -->
<div id="new-post-modal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100vh; background: rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:2000; padding:20px; overflow:auto;">
    <div class="card modal-card" style="
        max-width:600px; 
        width:100%; 
        background:#fff; 
        padding:30px; 
        border-radius:10px; 
        box-shadow:0 5px 20px rgba(0,0,0,0.3); 
        overflow-y:auto; 
        max-height:90vh;
        ">
        <h3 style="margin-bottom:20px; color: #d32f2f;">New Food Donation Post</h3>
        <form action="donor_dashboard.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_post">

            <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" required></textarea></div>
            <div class="form-group"><label>Category</label>
                <select name="category" required>
                    <option value="Produce">Produce</option>
                    <option value="Baked Goods">Baked Goods</option>
                    <option value="Dairy">Dairy & Eggs</option>
                    <option value="Meals">Prepared Meals</option>
                    <option value="Canned Goods">Canned Goods</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group"><label>Quantity (kg)</label><input type="number" step="0.1" min="0.1" name="quantity" required></div>
            <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" required></div>
            <div class="form-group"><label>Pickup Location</label><input type="text" name="location" required></div>
            <div class="form-group"><label>Video (optional)</label><input type="file" name="video" accept="video/*"></div>

            <div class="modal-buttons" style="display:flex; flex-wrap:wrap; justify-content:space-between; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Post</button>
            </div>
        </form>
    </div>
</div>

<script>
const newPostModal = document.getElementById('new-post-modal');
function openModal(){ newPostModal.style.display='flex'; }
function closeModal(){ newPostModal.style.display='none'; }
newPostModal.onclick = e => { if(e.target==newPostModal) closeModal(); }
</script>

</body>
</html>
