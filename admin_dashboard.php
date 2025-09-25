<?php
session_start();
include 'db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt: " . print_r($_SESSION, true));
    header("Location: index.php");
    exit;
}

// Handle add/edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = $conn->real_escape_string($_POST['name']);
    $price = floatval($_POST['price']);
    $category = $conn->real_escape_string($_POST['category']);
    $caption = $conn->real_escape_string($_POST['caption']);
    $image_path = NULL;

    // Validate category
    $valid_categories = ['Textbooks', 'Branded Jumpers', 'Bottles', 'Pens', 'Note Books', 'Wall Clocks', 'T-Shirts'];
    if (!in_array($category, $valid_categories)) {
        $message = "Invalid category selected.";
    } elseif (strlen($caption) > 255) {
        $message = "Caption must be 255 characters or less.";
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $image_info = getimagesize($image['tmp_name']);
            if (in_array($image_info['mime'], $allowed_types)) {
                $filename = time() . '_' . basename($image['name']);
                $path = 'images/' . $filename;
                if (move_uploaded_file($image['tmp_name'], $path)) {
                    $image_path = $path;
                } else {
                    $message = "Failed to upload image.";
                }
            } else {
                $message = "Invalid image format. Use JPEG or PNG.";
            }
        }

        if ($action === 'add' && !isset($message)) {
            $sql = "INSERT INTO products (name, price, category, caption, image_path) VALUES ('$name', '$price', '$category', " . ($caption ? "'$caption'" : 'NULL') . ", " . ($image_path ? "'$image_path'" : 'NULL') . ")";
            if ($conn->query($sql) === false) {
                $message = "Failed to add product: " . $conn->error;
            } else {
                $message = "Product added successfully.";
            }
        } elseif ($action === 'edit' && !isset($message)) {
            $id = intval($_POST['id']);
            error_log("Editing product ID: $id, Name: $name, Price: $price, Category: $category, Caption: $caption, Image: " . ($image_path ?: 'NULL'));
            $sql = "UPDATE products SET name='$name', price='$price', category='$category', caption=" . ($caption ? "'$caption'" : 'NULL') . ($image_path ? ", image_path='$image_path'" : "") . " WHERE id='$id'";
            if ($conn->query($sql) === false) {
                $message = "Failed to update product: " . $conn->error;
            } else {
                // Delete old image if a new one was uploaded
                if ($image_path) {
                    $old_image_sql = "SELECT image_path FROM products WHERE id='$id'";
                    $old_image_result = $conn->query($old_image_sql);
                    if ($old_image_result && $old_image_row = $old_image_result->fetch_assoc() && $old_image_row['image_path']) {
                        if (file_exists($old_image_row['image_path'])) {
                            unlink($old_image_row['image_path']);
                        }
                    }
                }
                $message = "Product updated successfully.";
            }
        }
    }
}

// Handle delete product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "SELECT image_path FROM products WHERE id='$id'";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc() && $row['image_path']) {
        if (file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
    }
    $sql = "DELETE FROM products WHERE id='$id'";
    if ($conn->query($sql) === false) {
        $message = "Failed to delete product: " . $conn->error;
    } else {
        $message = "Product deleted successfully.";
    }
}

// Handle complete delivery
if (isset($_GET['complete_delivery'])) {
    $id = intval($_GET['complete_delivery']);
    $sql = "UPDATE pending_deliveries SET status='Completed' WHERE id='$id'";
    if ($conn->query($sql) === false) {
        $message = "Failed to mark delivery as completed: " . $conn->error;
        error_log("Failed to complete delivery ID $id: " . $conn->error);
    } else {
        $message = "Delivery marked as completed.";
    }
}

// Handle send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $notification_message = trim($conn->real_escape_string($_POST['message']));
    if (empty($notification_message)) {
        $message = "Notification message cannot be empty.";
    } else {
        // Send to all users (user_id = NULL for broadcast)
        $sql = "INSERT INTO notifications (user_id, message, created_at) VALUES (NULL, '$notification_message', NOW())";
        if ($conn->query($sql) === false) {
            $message = "Failed to send notification: " . $conn->error;
            error_log("Failed to send notification: " . $conn->error);
        } else {
            $message = "Notification sent successfully.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bugema CampusShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-green: #091bbeff;
            --secondary-green: #4591e7ff;
            --accent-yellow: #facc15;
            --light-gray: #f3f4f6;
            --dark-gray: #111827;
            --text-gray: #4b5563;
            --white: #ffffff;
            --error-red: #dc2626;
            --success-green: #1059b9ff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
            position: relative;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 15px;
        }

        header {
            background: var(--primary-green);
            color: var(--white);
            padding: 1rem 0;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .header-btn {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .header-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .dashboard-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .dashboard-section h2 {
            color: var(--primary-green);
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[type="file"] {
            padding: 3px;
        }

        button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        button:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: translateY(-2px);
        }

        .message {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .success { color: var(--success-green); }
        .error { color: var(--error-red); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background: var(--primary-green);
            color: var(--white);
        }

        td img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .action-links a {
            color: var(--primary-green);
            text-decoration: none;
            margin-right: 10px;
        }

        .action-links a:hover {
            text-decoration: underline;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            animation: fadeIn 0.3s ease-out;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--text-gray);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--error-red);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }

            table {
                font-size: 0.8rem;
            }

            td img {
                width: 40px;
                height: 40px;
            }

            .modal {
                width: 95%;
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }

            .header-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                font-size: 0.8rem;
            }

            button {
                font-size: 0.8rem;
            }

            .modal h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Admin Dashboard - Bugema CampusShop</h1>
            <div class="header-actions">
                <a href="index.php" class="header-btn">Back to Shop</a>
                <a href="logout.php" class="header-btn">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Add Product Form -->
        <div class="dashboard-section">
            <h2>Add New Product</h2>
            <?php if (isset($message)): ?>
                <p class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            <form method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (UGX)</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="Textbooks">Textbooks</option>
                        <option value="Branded Jumpers">Branded Jumpers</option>
                        <option value="Bottles">Bottles</option>
                        <option value="Pens">Pens</option>
                        <option value="Note Books">Note Books</option>
                        <option value="Wall Clocks">Wall Clocks</option>
                        <option value="T-Shirts">T-Shirts</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="caption">Caption (Description)</label>
                    <textarea name="caption" placeholder="Enter a brief product description"></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <button type="submit">Add Product</button>
            </form>
        </div>

        <!-- Send Notification Form -->
        <div class="dashboard-section">
            <h2>Send Notification</h2>
            <form method="POST" action="admin_dashboard.php">
                <div class="form-group">
                    <label for="message">Notification Message</label>
                    <textarea name="message" required placeholder="Enter notification message (e.g., New stock available!)"></textarea>
                </div>
                <button type="submit" name="send_notification">Send Notification</button>
            </form>
        </div>

        <!-- Product List -->
        <div class="dashboard-section">
            <h2>Manage Products</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price (UGX)</th>
                    <th>Category</th>
                    <th>Caption</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
                <?php
                $sql = "SELECT * FROM products";
                $result = $conn->query($sql);
                if ($result === false) {
                    echo "<tr><td colspan='7'>Error fetching products: " . htmlspecialchars($conn->error) . "</td></tr>";
                    error_log("Product list query failed: " . $conn->error);
                } elseif ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . number_format($row['price']) . "</td>";
                        echo "<td>" . (isset($row['category']) && !empty($row['category']) ? htmlspecialchars($row['category']) : 'N/A') . "</td>";
                        echo "<td>" . (empty($row['caption']) ? 'N/A' : htmlspecialchars($row['caption'])) . "</td>";
                        echo "<td><img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'></td>";
                        echo "<td class='action-links'>";
                        echo "<a href='admin_dashboard.php?edit=" . htmlspecialchars($row['id']) . "'>Edit</a>";
                        echo "<a href='admin_dashboard.php?delete=" . htmlspecialchars($row['id']) . "' onclick='return confirm(\"Are you sure you want to delete this product?\")'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No products found.</td></tr>";
                }
                ?>
            </table>
        </div>

        <!-- Pending Deliveries -->
        <div class="dashboard-section">
            <h2>Pending Deliveries</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Phone Number</th>
                    <th>Location</th>
                    <th>Payment Method</th>
                    <th>Amount (UGX)</th>
                    <th>Actions</th>
                </tr>
                <?php
                $sql = "SHOW TABLES LIKE 'pending_deliveries'";
                $table_check = $conn->query($sql);
                if ($table_check->num_rows === 0) {
                    echo "<tr><td colspan='7'>Error: 'pending_deliveries' table does not exist.</td></tr>";
                    error_log("Error: 'pending_deliveries' table does not exist.");
                } else {
                    $sql = "SELECT id, username, phone, location, payment_method, amount FROM pending_deliveries WHERE status='Pending'";
                    $result = $conn->query($sql);
                    if ($result === false) {
                        echo "<tr><td colspan='7'>Error fetching pending deliveries: " . htmlspecialchars($conn->error) . "</td></tr>";
                        error_log("Pending deliveries query failed: " . $conn->error);
                    } elseif ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                            echo "<td>" . (isset($row['location']) && !empty($row['location']) ? htmlspecialchars($row['location']) : 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
                            echo "<td>" . ($row['amount'] ? number_format($row['amount']) : 'N/A') . "</td>";
                            echo "<td class='action-links'>";
                            echo "<a href='admin_dashboard.php?complete_delivery=" . htmlspecialchars($row['id']) . "' onclick='return confirm(\"Mark this delivery as completed?\")'>Complete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No pending deliveries found.</td></tr>";
                    }
                }
                ?>
            </table>
        </div>

        <!-- Edit Product Modal -->
        <?php
        if (isset($_GET['edit'])) {
            $id = intval($_GET['edit']);
            error_log("Edit requested for product ID: $id");
            if ($id <= 0) {
                $message = "Invalid product ID.";
            } else {
                $sql = "SELECT * FROM products WHERE id='$id'";
                $result = $conn->query($sql);
                if ($result === false) {
                    $message = "Error fetching product: " . $conn->error;
                    error_log("Edit query failed: " . $conn->error);
                } elseif ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
        ?>
            <div class="modal-overlay">
                <div class="modal">
                    <span class="modal-close" onclick="window.location.href='admin_dashboard.php'">&times;</span>
                    <h2>Edit Product (ID: <?php echo htmlspecialchars($row['id']); ?>)</h2>
                    <form method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                        <div class="form-group">
                            <label for="name">Product Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Price (UGX)</label>
                            <input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($row['price']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="Textbooks" <?php echo $row['category'] === 'Textbooks' ? 'selected' : ''; ?>>Textbooks</option>
                                <option value="Branded Jumpers" <?php echo $row['category'] === 'Branded Jumpers' ? 'selected' : ''; ?>>Branded Jumpers</option>
                                <option value="Bottles" <?php echo $row['category'] === 'Bottles' ? 'selected' : ''; ?>>Bottles</option>
                                <option value="Pens" <?php echo $row['category'] === 'Pens' ? 'selected' : ''; ?>>Pens</option>
                                <option value="Note Books" <?php echo $row['category'] === 'Note Books' ? 'selected' : ''; ?>>Note Books</option>
                                <option value="Wall Clocks" <?php echo $row['category'] === 'Wall Clocks' ? 'selected' : ''; ?>>Wall Clocks</option>
                                <option value="T-Shirts" <?php echo $row['category'] === 'T-Shirts' ? 'selected' : ''; ?>>T-Shirts</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="caption">Caption (Description)</label>
                            <textarea name="caption"><?php echo htmlspecialchars($row['caption'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="image">Update Image</label>
                            <input type="file" name="image" accept="image/*">
                            <p>Current Image: <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 50px; height: 50px;"></p>
                        </div>
                        <button type="submit">Update Product</button>
                    </form>
                </div>
            </div>
        <?php
                } else {
                    $message = "Product not found for ID: $id";
                    error_log("Product not found for ID: $id");
                }
            }
        }
        ?>
    </div>

    <script>
        // No additional JavaScript needed; close button uses window.location.href
    </script>
</body>
</html>

<?php
$conn->close();
?>