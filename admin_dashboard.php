<?php
// admin_dashboard.php (single-file dashboard: Products, Deliveries, Reports, Notifications)
// Requires: db_connect.php (creates $conn mysqli connection)
// Database: campusshop_db
// Tables required:
//  - products(id, name, price, stock, category, caption, image_path)
//  - pending_deliveries(id, username, amount, payment_method, status, created_at)
//  - notifications(id, user_id, message, created_at)

session_start();
include 'db_connect.php';

// Enable errors for debugging (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Admin auth check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt: " . print_r($_SESSION, true));
    header("Location: index.php");
    exit;
}
// Lightweight JSON endpoint for product fetch (used by openEditProduct)
if (isset($_GET['fetch_product'])) {
    $pid = intval($_GET['fetch_product']);
    header('Content-Type: application/json');
    
    if ($pid <= 0) {
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id, name, price, stock, category, caption, image_path FROM products WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database preparation failed: ' . $conn->error);
        }
        $stmt->bind_param("i", $pid);
        if (!$stmt->execute()) {
            throw new Exception('Database execution failed: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            echo json_encode([
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'category' => $row['category'],
                'caption' => $row['caption'],
                'image_path' => $row['image_path']
            ]);
        } else {
            echo json_encode(['error' => 'Product not found']);
        }
    } catch (Exception $e) {
        $errorMsg = 'Error fetching product: ' . $e->getMessage();
        error_log($errorMsg . ' at ' . date('Y-m-d H:i:s'));
        echo json_encode(['error' => $errorMsg]);
    } finally {
        if (isset($stmt)) $stmt->close();
    }
    exit; // Ensure no further code executes
}

// Global categories
$valid_categories = ['Textbooks', 'Branded Jumpers', 'Bottles', 'Pens', 'Note Books', 'Wall Clocks', 'T-Shirts'];

// Helper
function post_val($k, $d='') { return isset($_POST[$k]) ? $_POST[$k] : $d; }
$message = null;

// --------------------
// HANDLE REQUESTS
// --------------------

// Add / Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $action = $_POST['action'];
    $name = $conn->real_escape_string(post_val('name'));
    $price = floatval(post_val('price', 0));
    $stock = intval(post_val('stock', 0));
    $category = $conn->real_escape_string(post_val('category'));
    $caption = $conn->real_escape_string(post_val('caption', ''));
    $id = intval(post_val('id', 0)); // For edit
    $image_path = null;

    // Basic validation
    if (!in_array($category, $valid_categories)) {
        $message = "Invalid category selected.";
    } elseif ($price <= 0) {
        $message = "Price must be greater than zero.";
    } elseif ($stock < 0) {
        $message = "Stock cannot be negative.";
    } elseif (strlen($caption) > 255) {
        $message = "Caption must be 255 characters or less.";
    } elseif ($action === 'edit' && $id <= 0) {
        $message = "Invalid product ID for editing.";
    } else {

        // Image upload (optional for add, update for edit)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['image'];
            $image_info = getimagesize($image['tmp_name']);
            $allowed = ['image/jpeg','image/png','image/jpg'];
            if ($image_info && in_array($image_info['mime'], $allowed)) {
                if (!is_dir('images')) mkdir('images', 0755, true);
                $fn = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($image['name']));
                $new_path = 'images/' . $fn;
                if (move_uploaded_file($image['tmp_name'], $new_path)) {
                    $image_path = $new_path;
                    // Delete old image if editing
                    if ($action === 'edit') {
                        $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($old_row = $result->fetch_assoc()) {
                            if (!empty($old_row['image_path']) && file_exists($old_row['image_path'])) {
                                @unlink($old_row['image_path']);
                            }
                        }
                        $stmt->close();
                    }
                } else {
                    $message = "Failed to upload image.";
                }
            } else {
                $message = "Invalid image format. Use JPEG or PNG.";
            }
        }

        if (!isset($message)) {
            if ($action === 'add') {
                $sql = "INSERT INTO products (name, price, stock, category, caption, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdisss", $name, $price, $stock, $category, $caption, $image_path);
                if ($stmt->execute()) {
                    $message = "Product added successfully.";
                } else {
                    $message = "Failed to add product: " . $stmt->error;
                    error_log("Add product failed: " . $stmt->error);
                }
                $stmt->close();
            } else { // edit
                // Get current image path if no new image uploaded
                if (!$image_path) {
                    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($old_row = $result->fetch_assoc()) {
                        $image_path = $old_row['image_path'];
                    }
                    $stmt->close();
                }
                
                $sql = "UPDATE products SET name = ?, price = ?, stock = ?, category = ?, caption = ?, image_path = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdisssi", $name, $price, $stock, $category, $caption, $image_path, $id);
                if ($stmt->execute()) {
                    $message = "Product updated successfully.";
                } else {
                    $message = "Failed to update product: " . $stmt->error;
                    error_log("Update product failed: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}

// Delete product
if (isset($_GET['delete_product'])) {
    $id = intval($_GET['delete_product']);
    if ($id > 0) {
        $sql = "SELECT image_path FROM products WHERE id = ?";
        $st = $conn->prepare($sql);
        $st->bind_param("i",$id);
        $st->execute();
        $r = $st->get_result();
        if ($r && ($row = $r->fetch_assoc()) && !empty($row['image_path'])) {
            if (file_exists($row['image_path'])) @unlink($row['image_path']);
        }
        $st->close();

        $del = $conn->prepare("DELETE FROM products WHERE id = ?");
        $del->bind_param("i",$id);
        if ($del->execute()) $message = "Product deleted.";
        else { $message = "Failed to delete product: ".$del->error; error_log("Delete product failed: ".$del->error); }
        $del->close();
    }
}

// Mark delivery complete
if (isset($_GET['complete_delivery'])) {
    $id = intval($_GET['complete_delivery']);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE pending_deliveries SET status = 'Completed' WHERE id = ?");
        $stmt->bind_param("i",$id);
        if ($stmt->execute()) $message = "Delivery marked completed.";
        else { $message = "Failed to update delivery: ".$stmt->error; error_log("Complete delivery failed: ".$stmt->error); }
        $stmt->close();
    }
}

// Delete delivery (optional)
if (isset($_GET['delete_delivery'])) {
    $id = intval($_GET['delete_delivery']);
    if ($id > 0) {
        $del = $conn->prepare("DELETE FROM pending_deliveries WHERE id = ?");
        $del->bind_param("i",$id);
        if ($del->execute()) $message = "Delivery deleted.";
        else { $message = "Failed to delete delivery: ".$del->error; error_log("Delete delivery failed: ".$del->error); }
        $del->close();
    }
}

// Notifications: add (send), edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $notification_message = trim($conn->real_escape_string(post_val('message')));
    if (empty($notification_message)) {
        $message = "Notification cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (NULL, ?, NOW())");
        $stmt->bind_param("s", $notification_message);
        if ($stmt->execute()) $message = "Notification sent.";
        else { $message = "Failed to send notification: ".$stmt->error; error_log("Send notification failed: ".$stmt->error); }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_notification'])) {
    $nid = intval(post_val('nid', 0));
    $msg = $conn->real_escape_string(post_val('nmessage', ''));
    if ($nid <= 0 || $msg === '') $message = "Invalid notification edit.";
    else {
        $stmt = $conn->prepare("UPDATE notifications SET message = ?, created_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $msg, $nid);
        if ($stmt->execute()) $message = "Notification updated.";
        else { $message = "Failed to update notification: ".$stmt->error; error_log("Edit notification failed: ".$stmt->error); }
        $stmt->close();
    }
}

if (isset($_GET['delete_notification'])) {
    $nid = intval($_GET['delete_notification']);
    if ($nid > 0) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i",$nid);
        if ($stmt->execute()) $message = "Notification deleted.";
        else { $message = "Failed to delete notification: ".$stmt->error; error_log("Delete notification failed: ".$stmt->error); }
        $stmt->close();
    }
}

// --------------------
// FETCH DASHBOARD DATA
// --------------------
$totalSales = 0;
if ($res = $conn->query("SELECT SUM(amount) AS total_sales FROM pending_deliveries WHERE status = 'Completed'")) {
    $r = $res->fetch_assoc(); $totalSales = $r['total_sales'] ? floatval($r['total_sales']) : 0;
}

$totalProducts = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM products")) { $r = $res->fetch_assoc(); $totalProducts = intval($r['cnt']); }

$pendingCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM pending_deliveries WHERE status = 'Pending'")) { $r = $res->fetch_assoc(); $pendingCount = intval($r['cnt']); }

$notifCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")) { $r = $res->fetch_assoc(); $notifCount = intval($r['cnt']); }

// Sales by month (last 12 months)
$salesLabels = []; $salesData = [];
$months_sql = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS total
    FROM pending_deliveries
    WHERE status = 'Completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
";
$monthMap = [];
if ($res = $conn->query($months_sql)) {
    while ($row = $res->fetch_assoc()) $monthMap[$row['ym']] = floatval($row['total']);
}
for ($i=11;$i>=0;$i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $salesLabels[] = date('M Y', strtotime($m.'-01'));
    $salesData[] = isset($monthMap[$m]) ? $monthMap[$m] : 0;
}

// Category distribution
$catLabels = []; $catData = [];
if ($res = $conn->query("SELECT category, COUNT(*) AS cnt FROM products GROUP BY category")) {
    while ($r = $res->fetch_assoc()) { $catLabels[] = $r['category']; $catData[] = intval($r['cnt']); }
}

// Recent orders (last 8)
$recentOrders = [];
if ($res = $conn->query("SELECT id, username, amount, payment_method, status, created_at FROM pending_deliveries ORDER BY created_at DESC LIMIT 8")) {
    while ($r = $res->fetch_assoc()) {
        $recentOrders[] = $r;
    }
}

// Products and pending lists for tables
$products = []; if ($res = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 200")) while ($r=$res->fetch_assoc()) $products[] = $r;
$pendingDeliveries = []; if ($res = $conn->query("SELECT id, username, phone, location, payment_method, amount, status, created_at FROM pending_deliveries ORDER BY id DESC LIMIT 200")) while ($r=$res->fetch_assoc()) $pendingDeliveries[] = $r;

// Notifications list
$notifications = [];
if ($res = $conn->query("SELECT id, user_id, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 200")) {
    while ($r = $res->fetch_assoc()) $notifications[] = $r;
}

// JSON for charts
$salesLabelsJson = json_encode($salesLabels);
$salesDataJson = json_encode($salesData);
$catLabelsJson = json_encode($catLabels);
$catDataJson = json_encode($catData);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — CampusShop</title>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous"/>

<style>
:root{
    --primary:#091bbe; --primary-2:#1231d1; --accent:#4591e7; --muted:#6b7280;
    --bg:#f3f4f6; --card:#fff; --success:#1059b9; --danger:#dc2626;
    --sidebar-width:260px; --base-font:16px;
}
*{box-sizing:border-box;font-family: 'Poppins', Arial, sans-serif}
body{margin:0;background:var(--bg);color:#111827;font-size:var(--base-font)}
a{color:inherit;text-decoration:none}

/* Layout */
.sidebar{
    position:fixed; left:0; top:0; bottom:0; width:var(--sidebar-width);
    background:linear-gradient(180deg,var(--primary),var(--primary-2));
    color:#fff; padding:28px 18px; display:flex; flex-direction:column; gap:18px; z-index:1000;
}
.main-wrap{ margin-left:var(--sidebar-width); padding:28px 32px; min-height:100vh; transition:all .2s ease; }

/* Sidebar */
.brand{ display:flex; gap:12px; align-items:center }
.brand .logo{ width:52px;height:52px;background:#fff;color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px }
.brand h1{margin:0;font-size:20px;font-weight:700}
.brand p{margin:0;font-size:13px;opacity:0.95;color:#eaf3ff}

.menu{ margin-top:6px; display:flex; flex-direction:column; gap:8px; }
.menu a{ display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; font-weight:600; color:rgba(255,255,255,0.95); }
.menu a:hover{ background:rgba(255,255,255,0.06); }
.menu a.active{ background:rgba(255,255,255,0.08); }

.sidebar .footer{ margin-top:auto; display:flex; align-items:center; gap:12px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.04) }
.sidebar .footer .info{ font-size:14px }
.sidebar .footer .info small{ display:block; color:#e6f0ff; opacity:0.9; font-weight:500 }

/* Topbar & content */
.topbar{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px }
.search{ display:flex; align-items:center; gap:10px; background:var(--card); padding:10px 14px; border-radius:12px; box-shadow:0 6px 18px rgba(17,24,39,0.06); width:66% }
.search input{ border:0; outline:0; font-size:15px; width:100% }
.top-actions{ display:flex; align-items:center; gap:10px }

.card-row{ display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:20px }
.stat-card{ background:var(--card); padding:18px; border-radius:12px; box-shadow:0 6px 24px rgba(17,24,39,0.04) }
.stat-label{ color:var(--muted); font-size:14px }
.stat-value{ font-size:20px; font-weight:700; margin-top:6px }

/* Grid and panels */
.grid{ display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:18px }
.panel{ background:var(--card); padding:18px; border-radius:12px; box-shadow:0 6px 24px rgba(17,24,39,0.04) }

table{ width:100%; border-collapse:collapse; font-size:14px }
th, td{ padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:middle }
th{ background: linear-gradient(90deg,var(--primary),var(--primary-2)); color:#fff; font-weight:700; }
td img{ width:56px; height:56px; object-fit:cover; border-radius:8px }

.btn{ display:inline-block; padding:8px 12px; border-radius:8px; background:var(--primary); color:#fff; font-weight:700; border:none; cursor:pointer }
.btn.secondary{ background:#eef3ff; color:var(--primary); font-weight:700 }
.small{ font-size:0.85rem; padding:6px 8px; border-radius:6px }

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.modal {
    background: var(--card);
    border-radius: 10px;
    padding: 18px;
    width: 560px;
    max-width: 96%;
    box-shadow: 0 10px 40px rgba(2, 6, 23, 0.2);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
}

body.dark .modal {
    background: #071229;
    border: 1px solid rgba(255, 255, 255, 0.03);
}

.modal-content {
    background: none;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* spacing/responsive */
@media (max-width:1100px) {
    .card-row{ grid-template-columns: repeat(2,1fr) }
    .grid{ grid-template-columns: 1fr }
    .search{ width:100% }
}
@media (max-width:700px) {
    .sidebar{ display:none }
    .main-wrap{ margin-left:0; padding:14px }
}

/* dark theme */
body.dark{ background:#071029; color:#dbeafe }
body.dark .panel, body.dark .stat-card, body.dark .search, body.dark .modal{ background:#071229; color:#e6f0ff; box-shadow:none; border:1px solid rgba(255,255,255,0.03) }
body.dark th{ background: linear-gradient(90deg,#0b2b5b,#08306d) }

/* messages */
.message{ padding:10px 12px; border-radius:8px; margin-bottom:12px }
.success{ background: rgba(16,89,185,0.08); color:var(--success); border:1px solid rgba(16,89,185,0.2) }
.error{ background: rgba(220,38,38,0.06); color:var(--danger); border:1px solid rgba(220,38,38,0.2) }

textarea { resize: vertical; min-height: 60px; }
</style>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar" role="navigation" aria-label="Main sidebar">
        <div class="brand">
            <div class="logo"><img src="images/download.png" style="height: 40px; width: 40px;" alt="Logo"></div>
            <div>
                <h1>Bugema CampusShop</h1>
                <p>Admin Dashboard</p>
            </div>
        </div>

        <nav class="menu" aria-label="Sidebar menu">
            <a href="#" data-section="overview" class="active"><i class="fa fa-home"></i> Dashboard</a>
            <a href="#" data-section="products"><i class="fa fa-box"></i> Products</a>
            <a href="#" data-section="deliveries"><i class="fa fa-truck"></i> Deliveries</a>
            <a href="#" data-section="reports"><i class="fa fa-chart-line"></i> Reports</a>
            <a href="#" data-section="notifications"><i class="fa fa-bell"></i> Notifications</a>
            <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Log Out</a>
        </nav>

        <div class="footer">
            <div class="info">
                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <small>Administrator</small>
            </div>
            <div style="margin-left:auto; width:46px; height:46px; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; color:var(--primary)">A</div>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main-wrap" id="mainWrap">
        <!-- topbar -->
        <div class="topbar">
            <div class="search" role="search" aria-label="Search products and orders">
                <i class="fa fa-search" style="color:var(--muted)"></i>
                <input id="searchInput" placeholder="Search products, orders, notifications..." aria-label="Search input" />
            </div>

            <div class="top-actions">
                <button id="themeToggle" title="Toggle theme" class="btn secondary" aria-pressed="false"><i class="fa fa-moon"></i></button>
                <div style="background:var(--card); padding:10px 12px; border-radius:10px; display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-bell"></i> <strong style="margin-left:6px;"><?php echo $notifCount; ?></strong>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="text-align:right;">
                        <div style="font-weight:800;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div style="font-size:13px; color:var(--muted);">Administrator</div>
                    </div>
                    <div style="width:46px; height:46px; background:#eef3ff; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--primary)"><i class="fa fa-user"></i></div>
                </div>
            </div>
        </div>

        <!-- show message if exists -->
        <?php if (isset($message) && $message): ?>
            <div class="message <?php echo (stripos($message,'success')!==false || stripos($message,'added')!==false || stripos($message,'updated')!==false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Overview (default) -->
        <section id="overviewSection">
            <div class="card-row">
                <div class="stat-card">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value">UGX <?php echo number_format($totalSales); ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Completed sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Products</div>
                    <div class="stat-value"><?php echo $totalProducts; ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Items in catalog</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Deliveries</div>
                    <div class="stat-value"><?php echo $pendingCount; ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Awaiting completion</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Notifications (30d)</div>
                    <div class="stat-value"><?php echo $notifCount; ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Recent messages</div>
                </div>
            </div>

            <div class="grid">
                <div class="panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2 style="margin:0; font-size:18px;">Summary Sales</h2>
                        <div>
                            <select id="rangeSelect" style="padding:8px; border-radius:8px;">
                                <option selected>Last 12 months</option>
                                <option>Last 6 months</option>
                                <option>Last 30 days</option>
                            </select>
                        </div>
                    </div>
                    <canvas id="salesChart" height="140" aria-label="Sales chart"></canvas>
                </div>

                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="panel">
                        <h4 style="margin:0 0 10px 0;">Active Balance</h4>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:20px; font-weight:800;">UGX <?php echo number_format($totalSales * 0.12, 2); ?></div>
                                <div style="color:var(--muted); font-size:13px;">Estimated available balance</div>
                            </div>
                            <div>
                                <button id="addCardBtn" class="btn">Add Virtual Card</button>
                            </div>
                        </div>
                        <hr style="margin:12px 0;">
                        <div style="color:var(--muted); font-size:13px;">
                            <div>Incomes: UGX <?php echo number_format($totalSales); ?></div>
                            <div>Expenses: UGX <?php echo number_format($totalSales * 0.2); ?></div>
                            <div>Taxes: UGX <?php echo number_format($totalSales * 0.05); ?></div>
                        </div>
                    </div>

                    <div class="panel">
                        <h4 style="margin:0 0 10px 0;">Category Distribution</h4>
                        <canvas id="catChart" height="180"></canvas>
                    </div>

                    <div class="panel">
                        <h4 style="margin:0 0 10px 0;">Upcoming Payments</h4>
                        <ul style="padding:0; list-style:none; margin:0;">
                            <li style="padding:8px 0; border-bottom:1px dashed #f1f5f9;">Payonner — UGX 1,200,000</li>
                            <li style="padding:8px 0; border-bottom:1px dashed #f1f5f9;">Easy Pay — UGX 822,823</li>
                            <li style="padding:8px 0;">FastSpring — UGX 421,038</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section id="productsSection" style="display:none;">
            <div class="panel" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Manage Products</h3>
                    <div>
                        <button class="btn secondary small" id="addNewBtn">Add New</button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Caption</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productTable">
                        <?php if (count($products) === 0): ?>
                            <tr><td colspan="8">No products found.</td></tr>
                        <?php else: foreach ($products as $p):
                            $image_path = !empty($p['image_path']) ? htmlspecialchars($p['image_path']) : '';
                            $caption = htmlspecialchars($p['caption'] ?? 'No caption');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['id']); ?></td>
                            <td>
                                <?php if($image_path): ?>
                                    <img src="<?php echo $image_path; ?>" alt="Product image" style="width:56px; height:56px; object-fit:cover; border-radius:8px;">
                                <?php else: ?>
                                    <div style="width:56px;height:56px;background:#eef3ff;border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:12px;">No img</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td title="<?php echo $caption; ?>"><?php echo strlen($caption) > 30 ? substr($caption, 0, 30) . '...' : $caption; ?></td>
                            <td>UGX <?php echo number_format($p['price']); ?></td>
                            <td><?php echo htmlspecialchars($p['stock']); ?></td>
                            <td><?php echo htmlspecialchars($p['category']); ?></td>
                            <td>
                                <button class="small btn secondary" onclick="openEditProduct(<?php echo $p['id'];?>)">Edit</button>
                                <a class="small btn" style="background:#ff5b5b; padding:6px 8px;" href="?delete_product=<?php echo $p['id'];?>" onclick="return confirm('Delete this product? This cannot be undone.')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Add (scroll target for 'Add New') -->
            <div class="panel" id="add-section" style="margin-top:16px;">
                <h4 style="margin:0 0 8px 0;">Add Product</h4>
                <form id="addProductForm" method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <input name="name" placeholder="Product name" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                        <input type="number" step="0.01" name="price" placeholder="Price (UGX)" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                        <input type="number" name="stock" placeholder="Stock qty" value="0" min="0" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                        <select name="category" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;">
                            <option value="">Category</option>
                            <?php foreach ($valid_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="caption" placeholder="Short caption" style="padding:10px; border-radius:8px; border:1px solid #e6eefc;"></textarea>
                        <input type="file" name="image" accept="image/*" />
                        <div style="display:flex; gap:10px;">
                            <button class="btn" type="submit">Add Product</button>
                            <button type="button" id="clearAdd" class="btn secondary">Clear</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Deliveries Section -->
        <section id="deliveriesSection" style="display:none;">
            <div class="panel">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Pending Deliveries</h3>
                    <div><a href="#deliveries" class="btn secondary small" onclick="document.querySelector('#deliveriesSection').scrollIntoView({behavior:'smooth'})">View All</a></div>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead><tr><th>ID</th><th>User</th><th>Phone</th><th>Location</th><th>Payment</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="pendingTable">
                        <?php if (count($pendingDeliveries) === 0): ?>
                            <tr><td colspan="8">No pending deliveries.</td></tr>
                        <?php else: foreach ($pendingDeliveries as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['id']); ?></td>
                                <td><?php echo htmlspecialchars($p['username']); ?></td>
                                <td><?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['location'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo $p['amount'] ? 'UGX '.number_format($p['amount']):'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($p['status']); ?></td>
                                <td>
                                    <?php if ($p['status'] === 'Pending'): ?>
                                        <a class="small btn secondary" href="?complete_delivery=<?php echo $p['id'];?>" onclick="return confirm('Mark completed?')">Complete</a>
                                    <?php endif; ?>
                                    <a class="small btn" style="background:#ff5b5b" href="?delete_delivery=<?php echo $p['id'];?>" onclick="return confirm('Delete delivery?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Reports Section -->
        <section id="reportsSection" style="display:none;">
            <div class="panel">
                <h3 style="margin:0 0 10px 0;">Sales Reports</h3>
                <p style="color:var(--muted)">Sales summary and category distribution.</p>
                <div style="display:flex; gap:16px; margin-top:12px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:350px; background:var(--card); padding:12px; border-radius:8px;">
                        <canvas id="salesChart2" height="140"></canvas>
                    </div>
                    <div style="width:320px; background:var(--card); padding:12px; border-radius:8px;">
                        <canvas id="catChart2" height="140"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Notifications Section -->
        <section id="notificationsSection" style="display:none;">
            <div class="panel" style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Notifications</h3>
                    <div><a href="#notifications" class="btn secondary small" onclick="document.querySelector('#notificationsSection').scrollIntoView({behavior:'smooth'})">View All</a></div>
                </div>
            </div>

            <div class="panel" style="margin-bottom:12px;">
                <h4 style="margin:0 0 8px 0;">Send New Notification</h4>
                <form method="POST" action="admin_dashboard.php">
                    <textarea name="message" placeholder="Message to users" required style="width:100%; min-height:80px; padding:10px; border-radius:8px; border:1px solid #e6eefc;"></textarea>
                    <div style="margin-top:8px; text-align:right;">
                        <button class="btn" type="submit" name="send_notification">Send</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h4 style="margin:0 0 8px 0;">Recent Notifications</h4>
                <table>
                    <thead><tr><th>ID</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($notifications) === 0): ?>
                            <tr><td colspan="4">No notifications found.</td></tr>
                        <?php else: foreach ($notifications as $n): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($n['id']); ?></td>
                                <td><?php echo htmlspecialchars($n['message']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($n['created_at']))); ?></td>
                                <td>
                                    <button class="small btn secondary edit-btn" data-id="<?php echo $n['id']; ?>" data-message="<?php echo htmlspecialchars($n['message']); ?>">Edit</button>
                                    <a class="small btn" style="background:#ff5b5b" href="?delete_notification=<?php echo $n['id'];?>" onclick="return confirm('Delete notification?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Edit Product Modal -->
        <div id="editProductModal" style="display:none;">
            <div class="modal-overlay">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Edit product">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3>Edit Product</h3>
                        <button class="btn secondary" onclick="closeEditProduct()">Close</button>
                    </div>
                    <form id="editProductForm" method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editProductHiddenId" name="id">
                        
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <input id="editName" name="name" placeholder="Product name" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                            <input type="number" step="0.01" id="editPrice" name="price" placeholder="Price (UGX)" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                            <input type="number" id="editStock" name="stock" placeholder="Stock qty" min="0" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                            <select id="editCategory" name="category" required style="padding:10px; border-radius:8px; border:1px solid #e6eefc;">
                                <option value="">Category</option>
                                <?php foreach ($valid_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <textarea id="editCaption" name="caption" placeholder="Short caption" style="padding:10px; border-radius:8px; border:1px solid #e6eefc; min-height:60px;"></textarea>
                            <input type="file" name="image" accept="image/*" />
                            <div id="editCurrentImage" style="margin:10px 0;"></div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn" type="submit">Update Product</button>
                                <button type="button" class="btn secondary" onclick="closeEditProduct()">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Notification Modal -->
        <div id="editNotificationModal" style="display:none;">
            <div class="modal-overlay" id="notifOverlay">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Edit notification">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3>Edit Notification</h3>
                        <button class="btn secondary" id="closeNotifModal">Close</button>
                    </div>
                    <form id="editForm" method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="edit_notification" value="1">
                        <input type="hidden" id="notif_id" name="nid">
                        <label for="notif_message">Message:</label>
                        <textarea id="notif_message" name="nmessage" rows="4" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #e6eefc; margin-top:10px;"></textarea>
                        <div style="text-align:right; margin-top:10px;">
                            <button type="submit" class="btn">Save Changes</button>
                            <button type="button" class="btn secondary" id="cancelEdit">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Virtual Card Modal (client demo) -->
        <div id="cardModal" style="display:none;">
            <div class="modal-overlay" id="cardOverlay">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Add virtual card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Create Virtual Card</h3>
                        <button class="btn secondary" id="closeCardModal">Close</button>
                    </div>
                    <form id="virtualCardForm" style="margin-top:10px;">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <input name="card_name" placeholder="Card name" required style="padding:10px;border-radius:8px;border:1px solid #e6eefc;">
                            <input name="limit" type="number" placeholder="Limit (UGX)" required style="padding:10px;border-radius:8px;border:1px solid #e6eefc;">
                            <div style="text-align:right;">
                                <button class="btn" type="submit">Create</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    // ------------------
    // Client-side JS
    // ------------------

    // Section navigation (sidebar)
    const menuLinks = document.querySelectorAll('.menu a[data-section]');
    const sections = {
        overview: document.getElementById('overviewSection'),
        products: document.getElementById('productsSection'),
        deliveries: document.getElementById('deliveriesSection'),
        reports: document.getElementById('reportsSection'),
        notifications: document.getElementById('notificationsSection'),
    };

    function showSection(name) {
        Object.values(sections).forEach(s => { if (s) s.style.display = 'none'; });
        menuLinks.forEach(a => a.classList.remove('active'));
        if (sections[name]) sections[name].style.display = '';
        menuLinks.forEach(a => { if (a.dataset.section === name) a.classList.add('active'); });
        document.getElementById('mainWrap').scrollTop = 0;
    }

    menuLinks.forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const name = a.dataset.section;
            showSection(name);
        });
    });

    showSection('overview');

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('#productTable tr').forEach(r => {
            const txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
        document.querySelectorAll('#pendingTable tr').forEach(r => {
            const txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
        document.querySelectorAll('#notificationsSection table tbody tr').forEach(r => {
            const txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
    });

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    function applyTheme() {
        const t = localStorage.getItem('campus_theme') || 'light';
        if (t === 'dark') { 
            document.body.classList.add('dark'); 
            themeToggle.innerHTML = '<i class="fa fa-sun"></i>'; 
            themeToggle.setAttribute('aria-pressed', 'true'); 
        }
        else { 
            document.body.classList.remove('dark'); 
            themeToggle.innerHTML = '<i class="fa fa-moon"></i>'; 
            themeToggle.setAttribute('aria-pressed', 'false'); 
        }
    }
    themeToggle.addEventListener('click', function() {
        const cur = document.body.classList.contains('dark') ? 'dark' : 'light';
        const next = cur === 'dark' ? 'light' : 'dark';
        localStorage.setItem('campus_theme', next);
        applyTheme();
    });
    applyTheme();

    // Close modals when clicking overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.style.display = 'none';
            }
        });
    });

    // Add virtual card modal (demo)
    document.getElementById('addCardBtn').addEventListener('click', function() {
        document.getElementById('cardModal').style.display = 'block';
    });
    document.getElementById('closeCardModal').addEventListener('click', function() {
        document.getElementById('cardModal').style.display = 'none';
    });
    document.getElementById('virtualCardForm').addEventListener('submit', function(e) {
        e.preventDefault();
        alert('Virtual card created (demo). Implement backend to persist cards.');
        document.getElementById('cardModal').style.display = 'none';
    });

    // Add New scrolls
    document.getElementById('addNewBtn').addEventListener('click', function() {
        document.getElementById('add-section').scrollIntoView({behavior:'smooth', block:'center'});
    });

    // Clear Add form
    document.getElementById('clearAdd').addEventListener('click', function() {
        document.getElementById('addProductForm').reset();
    });

        // Edit Product
    function openEditProduct(id) {
    console.log('Fetching product with ID:', id); // Debug log
    fetch('admin_dashboard.php?fetch_product=' + encodeURIComponent(id))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data); // Debug log
            if (data.error) {
                console.error('Fetch error:', data.error);
                alert(data.error);
                return;
            }
            // Populate form fields
            document.getElementById('editProductHiddenId').value = data.id;
            document.getElementById('editName').value = data.name || '';
            document.getElementById('editPrice').value = data.price || '';
            document.getElementById('editStock').value = data.stock || 0;
            document.getElementById('editCategory').value = data.category || '';
            document.getElementById('editCaption').value = data.caption || '';

            // Show current image
            if (data.image_path) {
                document.getElementById('editCurrentImage').innerHTML =
                    '<img src="' + data.image_path + '" alt="Current image" style="width:100px; height:100px; object-fit:cover; border-radius:8px; margin-bottom:5px;">' +
                    '<small style="color:var(--muted);">New image will replace this</small>';
            } else {
                document.getElementById('editCurrentImage').innerHTML =
                    '<div style="width:100px; height:100px; background:#eef3ff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--muted);">No image</div>';
            }

            document.getElementById('editProductModal').style.display = 'block';
        })
        .catch(err => {
            console.error('Error fetching product:', err);
            alert('Could not fetch product information. Please try again or check the console for details.');
        });
}

    // Edit Notification
    const editNotificationModal = document.getElementById('editNotificationModal');
    const closeNotifModal = document.getElementById('closeNotifModal');
    const cancelEdit = document.getElementById('cancelEdit');
    const editBtns = document.querySelectorAll('.edit-btn');

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const message = btn.getAttribute('data-message');
            document.getElementById('notif_id').value = id;
            document.getElementById('notif_message').value = message;
            editNotificationModal.style.display = 'block';
        });
    });

    if (closeNotifModal) closeNotifModal.addEventListener('click', () => editNotificationModal.style.display = 'none');
    if (cancelEdit) cancelEdit.addEventListener('click', () => editNotificationModal.style.display = 'none');

    // Sales charts
    const salesLabels = <?php echo $salesLabelsJson; ?>;
    const salesData = <?php echo $salesDataJson; ?>;
    const catLabels = <?php echo $catLabelsJson; ?>;
    const catData = <?php echo $catDataJson; ?>;

    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(salesCtx, {
        type: 'line', 
        data: { 
            labels: salesLabels, 
            datasets: [{ 
                label: 'Revenue (UGX)', 
                data: salesData, 
                borderColor: '#0b63ff', 
                backgroundColor: 'rgba(11,99,255,0.08)', 
                tension: 0.25, 
                fill: true, 
                pointRadius: 3 
            }] 
        },
        options: { 
            responsive: true, 
            plugins: { legend: { display: false } }, 
            scales: { y: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString() } } } 
        }
    });

    const salesCtx2 = document.getElementById('salesChart2') ? document.getElementById('salesChart2').getContext('2d') : null;
    if (salesCtx2) {
        new Chart(salesCtx2, {
            type: 'bar', 
            data: { labels: salesLabels, datasets: [{ label: 'Revenue (UGX)', data: salesData, backgroundColor: '#0b63ff' }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString() } } } }
        });
    }

    const catCtx = document.getElementById('catChart').getContext('2d');
    new Chart(catCtx, { 
        type: 'doughnut', 
        data: { 
            labels: catLabels, 
            datasets: [{ data: catData, backgroundColor: ['#60a5fa', '#93c5fd', '#fca5a5', '#fbbf24', '#34d399', '#c084fc'] }] 
        }, 
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } } 
    });

    const catCtx2 = document.getElementById('catChart2') ? document.getElementById('catChart2').getContext('2d') : null;
    if (catCtx2) { 
        new Chart(catCtx2, { 
            type: 'doughnut', 
            data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: ['#60a5fa', '#93c5fd', '#fca5a5', '#fbbf24', '#34d399', '#c084fc'] }] }, 
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } } 
        }); 
    }
</script>

<?php
// close DB connection
$conn->close();
?>
</body>
</html>