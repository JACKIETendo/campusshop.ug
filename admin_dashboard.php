<?php
// admin_dashboard.php (single-file dashboard: Products, Categories, Deliveries, Reports, Notifications, Feedback)
// Requires: db_connect.php (creates $conn mysqli connection)
// Database: campusshop_db
// Tables required:
//  - products(id, name, price, stock, category, caption, image_path)
//  - pending_deliveries(id, username, amount, payment_method, status, created_at)
//  - notifications(id, user_id, message, created_at)
//  - feedback(id, user_id, name, email, message, created_at, status, admin_reply)

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

// ==================== EMAIL FUNCTION ====================
function sendFeedbackReplyEmail($userEmail, $userName, $userMessage, $adminReply) {
    $to = $userEmail;
    $subject = "Reply to Your Feedback - Bugema CampusShop";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #091bbe; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; border-left: 4px solid #091bbe; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .user-message { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 3px solid #e6eefc; }
            .admin-reply { background: #e8f4fd; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 3px solid #091bbe; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Bugema CampusShop</h1>
                <p>Response to Your Feedback</p>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>Thank you for your feedback. Here is our response:</p>
                
                <div class='user-message'>
                    <strong>Your original message:</strong><br>
                    <em>" . nl2br(htmlspecialchars($userMessage)) . "</em>
                </div>
                
                <div class='admin-reply'>
                    <strong>Our response:</strong><br>
                    " . nl2br(htmlspecialchars($adminReply)) . "
                </div>
                
                <p>If you have any further questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                Bugema CampusShop Team</p>
            </div>
            <div class='footer'>
                <p>Bugema CampusShop - Bugema University<br>
                Email: campusshop@bugemauniv.ac.ug | Phone: +256 7550 87665</p>
                <p><small>This is an automated message. Please do not reply to this email.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Bugema CampusShop <noreply@campusshop.ug>" . "\r\n";
    $headers .= "Reply-To: campusshop@bugemauniv.ac.ug" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // For debugging, you can log instead of actually sending
    error_log("Attempting to send email to: $userEmail");
    
    return mail($to, $subject, $message, $headers);
}

// ==================== NOTIFICATION FUNCTION ====================
function createUserNotification($userId, $message) {
    global $conn;
    if ($userId) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $userId, $message);
        return $stmt->execute();
    }
    return false;
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

// --------------------
// CATEGORY MANAGEMENT
// --------------------

// Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    $category_action = $_POST['category_action'];
    $category_name = trim($conn->real_escape_string(post_val('category_name', '')));
    $category_id = intval(post_val('category_id', 0));
    
    if (empty($category_name)) {
        $message = "Category name cannot be empty.";
    } elseif (strlen($category_name) > 50) {
        $message = "Category name must be 50 characters or less.";
    } else {
        if ($category_action === 'add') {
            // Check if category already exists
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE category = ? LIMIT 1");
            $check_stmt->bind_param("s", $category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = "Category already exists and cannot be added again.";
            } else {
                // Add new category by inserting a dummy product (or you can create a categories table)
                $stmt = $conn->prepare("INSERT INTO products (name, price, stock, category, caption, image_path) VALUES (?, 0, 0, ?, 'System Category', '')");
                $dummy_name = "CATEGORY_" . strtoupper($category_name);
                $stmt->bind_param("ss", $dummy_name, $category_name);
                if ($stmt->execute()) {
                    $message = "Category added successfully.";
                    // Update valid categories array
                    $valid_categories[] = $category_name;
                } else {
                    $message = "Failed to add category: " . $stmt->error;
                    error_log("Add category failed: " . $stmt->error);
                }
                $stmt->close();
            }
            $check_stmt->close();
            
        } elseif ($category_action === 'edit' && $category_id > 0) {
            // Get old category name first
            $old_stmt = $conn->prepare("SELECT category FROM products WHERE id = ?");
            $old_stmt->bind_param("i", $category_id);
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            $old_category = '';
            if ($old_row = $old_result->fetch_assoc()) {
                $old_category = $old_row['category'];
            }
            $old_stmt->close();
            
            // Update category name across all products
            $stmt = $conn->prepare("UPDATE products SET category = ? WHERE category = ?");
            $stmt->bind_param("ss", $category_name, $old_category);
            if ($stmt->execute()) {
                $message = "Category updated successfully.";
                // Update valid categories array
                $key = array_search($old_category, $valid_categories);
                if ($key !== false) {
                    $valid_categories[$key] = $category_name;
                }
            } else {
                $message = "Failed to update category: " . $stmt->error;
                error_log("Update category failed: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Delete Category
if (isset($_GET['delete_category'])) {
    $category_to_delete = $conn->real_escape_string($_GET['delete_category']);
    
    if (!empty($category_to_delete)) {
        // Check if category has products
        $check_stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category = ? AND name NOT LIKE 'CATEGORY_%'");
        $check_stmt->bind_param("s", $category_to_delete);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $product_count = $row['product_count'];
        $check_stmt->close();
        
        if ($product_count > 0) {
            $message = "Cannot delete category '$category_to_delete' - it contains $product_count product(s). Move or delete products first.";
        } else {
            // Delete the category (dummy products)
            $stmt = $conn->prepare("DELETE FROM products WHERE category = ? AND name LIKE 'CATEGORY_%'");
            $stmt->bind_param("s", $category_to_delete);
            if ($stmt->execute()) {
                $message = "Category deleted successfully.";
                // Remove from valid categories array
                $key = array_search($category_to_delete, $valid_categories);
                if ($key !== false) {
                    unset($valid_categories[$key]);
                }
            } else {
                $message = "Failed to delete category: " . $stmt->error;
                error_log("Delete category failed: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// ==================== DELIVERY COMPLETION WITH STOCK REDUCTION ====================
if (isset($_GET['complete_delivery'])) {
    $id = intval($_GET['complete_delivery']);
    if ($id > 0) {
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        try {
            // Get delivery details including product_id and quantity
            $stmt = $conn->prepare("SELECT product_id, quantity FROM pending_deliveries WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $delivery = $result->fetch_assoc();
                $product_id = $delivery['product_id'];
                $quantity = $delivery['quantity'];
                
                // Update delivery status
                $update_stmt = $conn->prepare("UPDATE pending_deliveries SET status = 'Completed' WHERE id = ?");
                $update_stmt->bind_param("i", $id);
                $update_stmt->execute();
                
                // Reduce product stock if product_id exists
                if ($product_id && $product_id !== 'N/A') {
                    $stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stock_stmt->bind_param("iii", $quantity, $product_id, $quantity);
                    
                    if ($stock_stmt->execute()) {
                        if ($stock_stmt->affected_rows > 0) {
                            $message = "Delivery marked completed and stock reduced successfully.";
                        } else {
                            // If stock reduction failed (not enough stock), rollback
                            throw new Exception("Not enough stock to complete this delivery.");
                        }
                    } else {
                        throw new Exception("Failed to update product stock: " . $stock_stmt->error);
                    }
                    $stock_stmt->close();
                } else {
                    $message = "Delivery marked completed (no stock adjustment - product ID missing).";
                }
                
                $update_stmt->close();
            } else {
                throw new Exception("Delivery not found.");
            }
            
            $stmt->close();
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to complete delivery: " . $e->getMessage();
            error_log("Complete delivery failed: " . $e->getMessage());
        }
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

// ==================== FEEDBACK MANAGEMENT ====================

// Handle admin reply to feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_feedback'])) {
    $feedback_id = intval(post_val('feedback_id', 0));
    $admin_reply = trim($conn->real_escape_string(post_val('admin_reply', '')));
    
    if ($feedback_id <= 0) {
        $message = "Invalid feedback ID.";
    } elseif (empty($admin_reply)) {
        $message = "Reply message cannot be empty.";
    } else {
        // First get user details and original message
        $stmt = $conn->prepare("SELECT user_id, name, email, message FROM feedback WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $feedback = $result->fetch_assoc();
            $userName = $feedback['name'];
            $userEmail = $feedback['email'];
            $userMessage = $feedback['message'];
            $userId = $feedback['user_id'];
            
            // Update the feedback with admin reply
            $update_stmt = $conn->prepare("UPDATE feedback SET admin_reply = ?, status = 'replied', replied_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("si", $admin_reply, $feedback_id);
            
            if ($update_stmt->execute()) {
                $emailSent = false;
                $notificationCreated = false;
                
                // Send email notification if user provided email
                if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $emailSent = sendFeedbackReplyEmail($userEmail, $userName, $userMessage, $admin_reply);
                    error_log("Email send attempt result: " . ($emailSent ? "SUCCESS" : "FAILED"));
                } else {
                    error_log("No valid email provided for feedback ID: $feedback_id");
                }
                
                // Create in-app notification if user is registered
                if ($userId) {
                    $notificationMessage = "We've replied to your feedback: " . (strlen($admin_reply) > 50 ? substr($admin_reply, 0, 50) . "..." : $admin_reply);
                    $notificationCreated = createUserNotification($userId, $notificationMessage);
                }
                
                // Build success message
                if ($emailSent && $notificationCreated) {
                    $message = "Reply sent successfully! User notified via email and in-app notification.";
                } elseif ($emailSent) {
                    $message = "Reply sent successfully! User notified via email.";
                } elseif ($notificationCreated) {
                    $message = "Reply saved successfully! User notified via in-app notification.";
                } else {
                    $message = "Reply saved successfully! (No notifications sent - user has no email or account)";
                }
                
            } else {
                $message = "Failed to save reply: " . $update_stmt->error;
                error_log("Reply feedback failed: " . $update_stmt->error);
            }
            $update_stmt->close();
        } else {
            $message = "Feedback not found.";
        }
        $stmt->close();
    }
}

// Mark feedback as read
if (isset($_GET['mark_feedback_read'])) {
    $feedback_id = intval($_GET['mark_feedback_read']);
    if ($feedback_id > 0) {
        $stmt = $conn->prepare("UPDATE feedback SET status = 'read' WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);
        if ($stmt->execute()) {
            $message = "Feedback marked as read.";
        } else {
            $message = "Failed to update feedback: " . $stmt->error;
            error_log("Mark feedback read failed: " . $stmt->error);
        }
        $stmt->close();
    }
}

// Delete feedback
if (isset($_GET['delete_feedback'])) {
    $feedback_id = intval($_GET['delete_feedback']);
    if ($feedback_id > 0) {
        $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);
        if ($stmt->execute()) {
            $message = "Feedback deleted successfully.";
        } else {
            $message = "Failed to delete feedback: " . $stmt->error;
            error_log("Delete feedback failed: " . $stmt->error);
        }
        $stmt->close();
    }
}

        // --------------------
        // PRINT SALES REPORT
        // --------------------
        if (isset($_GET['print_report'])) {
            $report_type = $_GET['print_report'];
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            
            // Generate report data
            $report_data = [];
            $report_title = "";
            
            if ($report_type === 'sales_summary') {
            $report_title = "Sales Summary Report";
            
            // In the sales summary report section, update the query:
            $stmt = $conn->prepare("
                SELECT 
                    DATE(pd.created_at) as sale_date,
                    COALESCE(pd.product_name, 'Various Products') as product_name,
                    pd.product_id,
                    pd.product_image,
                    pd.quantity,
                    COUNT(*) as order_count,
                    COALESCE(SUM(pd.amount), 0) as total_amount,
                    COALESCE(AVG(pd.amount), 0) as avg_order_value,
                    pd.status
                FROM pending_deliveries pd
                WHERE DATE(pd.created_at) BETWEEN ? AND ?
                GROUP BY DATE(pd.created_at), pd.product_id, pd.status
                ORDER BY sale_date DESC, product_name
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            $stmt->close();
            
            // If no data found, show empty message
            if (empty($report_data)) {
                $report_data[] = [
                    'sale_date' => $start_date,
                    'product_name' => 'No products',
                    'order_count' => 0,
                    'total_amount' => 0,
                    'avg_order_value' => 0,
                    'status' => 'No data found'
                ];
            }
        } elseif ($report_type === 'category_sales') {
        $report_title = "Category Sales Report";
        
        // Simplified category sales report
        $stmt = $conn->prepare("
            SELECT 
                category,
                COUNT(*) as product_count,
                COALESCE(SUM(price * stock), 0) as inventory_value,
                COALESCE(AVG(price), 0) as avg_price
            FROM products 
            WHERE name NOT LIKE 'CATEGORY_%'
            GROUP BY category
            ORDER BY inventory_value DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data[] = [
                'category' => $row['category'],
                'product_count' => $row['product_count'],
                'total_sales' => $row['inventory_value'],
                'avg_price' => $row['avg_price']
            ];
        }
        $stmt->close();
    }
    
    // Store report data in session for printing
    $_SESSION['print_report'] = [
        'title' => $report_title,
        'type' => $report_type,
        'data' => $report_data,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    header("Location: admin_dashboard.php?show_print=true");
    exit;
}

// --------------------
// FETCH DASHBOARD DATA
// --------------------
$totalSales = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total_sales FROM pending_deliveries WHERE status = 'Completed'")) {
    $r = $res->fetch_assoc(); $totalSales = floatval($r['total_sales']);
}

$totalProducts = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE name NOT LIKE 'CATEGORY_%'")) { $r = $res->fetch_assoc(); $totalProducts = intval($r['cnt']); }

$pendingCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM pending_deliveries WHERE status = 'Pending'")) { $r = $res->fetch_assoc(); $pendingCount = intval($r['cnt']); }

$notifCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")) { $r = $res->fetch_assoc(); $notifCount = intval($r['cnt']); }

// Feedback counts
$unreadFeedbackCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM feedback WHERE status = 'new'")) { $r = $res->fetch_assoc(); $unreadFeedbackCount = intval($r['cnt']); }

$totalFeedbackCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM feedback")) { $r = $res->fetch_assoc(); $totalFeedbackCount = intval($r['cnt']); }

// ==================== STOCK DATA ====================
$totalStockValue = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(price * stock), 0) AS total_value FROM products WHERE name NOT LIKE 'CATEGORY_%'")) { 
    $r = $res->fetch_assoc(); $totalStockValue = floatval($r['total_value']); 
}

$totalStockItems = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(stock), 0) AS total_items FROM products WHERE name NOT LIKE 'CATEGORY_%'")) { 
    $r = $res->fetch_assoc(); $totalStockItems = intval($r['total_items']); 
}

$lowStockCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE stock <= 5 AND name NOT LIKE 'CATEGORY_%'")) { 
    $r = $res->fetch_assoc(); $lowStockCount = intval($r['cnt']); 
}

// Sales by month (last 12 months) - FIXED with COALESCE
$salesLabels = []; $salesData = [];
$months_sql = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
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
if ($res = $conn->query("SELECT category, COUNT(*) AS cnt FROM products WHERE name NOT LIKE 'CATEGORY_%' GROUP BY category")) {
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
$products = []; if ($res = $conn->query("SELECT * FROM products WHERE name NOT LIKE 'CATEGORY_%' ORDER BY id DESC LIMIT 200")) while ($r=$res->fetch_assoc()) $products[] = $r;
// Updated: Fetch pending deliveries with product information
$pendingDeliveries = []; 
if ($res = $conn->query("
    SELECT 
        pd.id, 
        pd.username, 
        pd.phone, 
        pd.location, 
        pd.payment_method, 
        pd.amount, 
        pd.status, 
        pd.created_at,
        pd.product_id,
        pd.product_name,
        pd.product_image,
        pd.quantity,
        COALESCE(pd.product_name, 'Unknown Product') as display_product_name,
        COALESCE(pd.product_image, '') as display_product_image
    FROM pending_deliveries pd
    ORDER BY pd.created_at DESC LIMIT 200
")) {
    while ($r = $res->fetch_assoc()) {
        // Use product information from pending_deliveries
        $r['product_name'] = $r['display_product_name'];
        $r['product_image'] = $r['display_product_image'];
        $r['product_id'] = $r['product_id'] ?? 'N/A';
        $r['quantity'] = $r['quantity'] ?? 1;
        $pendingDeliveries[] = $r;
    }
}

// Notifications list
$notifications = [];
if ($res = $conn->query("SELECT id, user_id, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 200")) {
    while ($r = $res->fetch_assoc()) $notifications[] = $r;
}

// Fetch categories for management (exclude dummy category products)
$categories = [];
if ($res = $conn->query("SELECT DISTINCT category, COUNT(*) as product_count FROM products WHERE name NOT LIKE 'CATEGORY_%' GROUP BY category ORDER BY category")) {
    while ($r = $res->fetch_assoc()) {
        $categories[] = $r;
    }
}

// Fetch feedback for management
$feedbackList = [];
if ($res = $conn->query("SELECT id, user_id, name, email, message, admin_reply, status, created_at, replied_at FROM feedback ORDER BY created_at DESC LIMIT 200")) {
    while ($r = $res->fetch_assoc()) {
        $feedbackList[] = $r;
    }
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
    --bg:#f3f4f6; --card:#fff; --success:#1059b9; --danger:#dc2626; --warning:#f59e0b;
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

.card-row{ display:grid; grid-template-columns: repeat(5, 1fr); gap:16px; margin-bottom:20px }
.stat-card{ background:var(--card); padding:18px; border-radius:12px; box-shadow:0 6px 24px rgba(17,24,39,0.04) }
.stat-label{ color:var(--muted); font-size:14px }
.stat-value{ font-size:20px; font-weight:700; margin-top:6px }
.stat-warning { color: var(--warning); }
.stat-danger { color: var(--danger); }

/* Grid and panels */
.grid{ display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:18px }
.panel{ background:var(--card); padding:18px; border-radius:12px; box-shadow:0 6px 24px rgba(17,24,39,0.04) }

table{ width:100%; border-collapse:collapse; font-size:14px }
th, td{ padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:middle }
th{ background: linear-gradient(90deg,var(--primary),var(--primary-2)); color:#fff; font-weight:700; }
td img{ width:56px; height:56px; object-fit:cover; border-radius:8px }

.btn{ display:inline-block; padding:8px 12px; border-radius:8px; background:var(--primary); color:#fff; font-weight:700; border:none; cursor:pointer }
.btn.secondary{ background:#eef3ff; color:var(--primary); font-weight:700 }
.btn.warning{ background:var(--warning); color:#fff; }
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

/* Feedback specific styles */
.feedback-item {
    border: 1px solid #e6eefc;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 12px;
    background: #fafbfe;
}

.feedback-item.unread {
    background: #e8f4fd;
    border-left: 4px solid var(--primary);
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.feedback-user {
    font-weight: 600;
    color: var(--primary);
}

.feedback-date {
    color: var(--muted);
    font-size: 12px;
}

.feedback-message {
    background: white;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 10px;
    border-left: 3px solid #e6eefc;
}

.feedback-reply {
    background: #f0f7ff;
    padding: 12px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 3px solid var(--success);
}

.feedback-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-new { background: #ffebee; color: #d32f2f; }
.status-read { background: #e3f2fd; color: #1976d2; }
.status-replied { background: #e8f5e8; color: #388e3c; }

/* Stock warning styles */
.stock-warning { color: var(--warning); font-weight: 600; }
.stock-danger { color: var(--danger); font-weight: 600; }

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    #printableReport, #printableReport * {
        visibility: visible;
    }
    #printableReport {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white;
        padding: 20px;
    }
    .btn, .sidebar, .topbar, .panel:not(#printableReport) {
        display: none !important;
    }
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
body.dark .feedback-item { background: #0a1a3a; border-color: #1e3a5f; }
body.dark .feedback-item.unread { background: #0a2342; }
body.dark .feedback-message { background: #071229; }
body.dark .feedback-reply { background: #0a2a4a; }

/* messages */
.message{ padding:10px 12px; border-radius:8px; margin-bottom:12px }
.success{ background: rgba(16,89,185,0.08); color:var(--success); border:1px solid rgba(16,89,185,0.2) }
.error{ background: rgba(220,38,38,0.06); color:var(--danger); border:1px solid rgba(220,38,38,0.2) }
.warning{ background: rgba(245,158,11,0.08); color:var(--warning); border:1px solid rgba(245,158,11,0.2) }

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
            <a href="#" data-section="categories"><i class="fa fa-tags"></i> Categories</a>
            <a href="#" data-section="deliveries"><i class="fa fa-truck"></i> Deliveries</a>
            <a href="#" data-section="reports"><i class="fa fa-chart-line"></i> Reports</a>
            <a href="#" data-section="notifications"><i class="fa fa-bell"></i> Notifications</a>
            <a href="#" data-section="feedback"><i class="fa fa-comments"></i> User Feedback 
                <?php if ($unreadFeedbackCount > 0): ?>
                    <span style="background:#ff4757; color:white; border-radius:50%; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; margin-left:auto;">
                        <?php echo $unreadFeedbackCount; ?>
                    </span>
                <?php endif; ?>
            </a>
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
            <div class="message <?php 
                if (stripos($message,'success')!==false || stripos($message,'added')!==false || stripos($message,'updated')!==false) echo 'success';
                elseif (stripos($message,'warning')!==false || stripos($message,'stock')!==false) echo 'warning';
                else echo 'error'; 
            ?>">
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
                    <div class="stat-label">Stock Value</div>
                    <div class="stat-value">UGX <?php echo number_format($totalStockValue); ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Total inventory value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Items in Stock</div>
                    <div class="stat-value <?php echo $totalStockItems < 100 ? 'stat-warning' : ''; ?>"><?php echo number_format($totalStockItems); ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">
                        <?php if ($lowStockCount > 0): ?>
                            <span class="stock-warning"><?php echo $lowStockCount; ?> low stock items</span>
                        <?php else: ?>
                            Stock levels good
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Deliveries</div>
                    <div class="stat-value"><?php echo $pendingCount; ?></div>
                    <div style="color:var(--muted); margin-top:8px; font-size:13px;">Awaiting completion</div>
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
                        <h4 style="margin:0 0 10px 0;">Stock Alerts</h4>
                        <?php
                        $lowStockProducts = [];
                        if ($res = $conn->query("SELECT name, stock FROM products WHERE stock <= 5 AND name NOT LIKE 'CATEGORY_%' ORDER BY stock ASC LIMIT 5")) {
                            while ($row = $res->fetch_assoc()) {
                                $lowStockProducts[] = $row;
                            }
                        }
                        ?>
                        <?php if (count($lowStockProducts) > 0): ?>
                            <ul style="padding:0; list-style:none; margin:0;">
                                <?php foreach ($lowStockProducts as $product): ?>
                                    <li style="padding:8px 0; border-bottom:1px dashed #f1f5f9;">
                                        <span style="font-weight:600;"><?php echo htmlspecialchars($product['name']); ?></span>
                                        <span class="<?php echo $product['stock'] == 0 ? 'stock-danger' : 'stock-warning'; ?>" style="float:right;">
                                            <?php echo $product['stock']; ?> left
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div style="margin-top:10px;">
                                <a href="?section=products" class="btn warning small">Manage Stock</a>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--muted); margin:0;">No stock alerts. All products have sufficient stock.</p>
                        <?php endif; ?>
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
                            $stock_class = '';
                            if ($p['stock'] == 0) $stock_class = 'stock-danger';
                            elseif ($p['stock'] <= 5) $stock_class = 'stock-warning';
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
                            <td class="<?php echo $stock_class; ?>"><?php echo htmlspecialchars($p['stock']); ?></td>
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

        <!-- Categories Section -->
        <section id="categoriesSection" style="display:none;">
            <div class="panel" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Manage Categories</h3>
                    <div>
                        <button class="btn secondary small" id="addCategoryBtn">Add New Category</button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Product Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTable">
                        <?php if (count($categories) === 0): ?>
                            <tr><td colspan="3">No categories found.</td></tr>
                        <?php else: foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['category']); ?></td>
                            <td><?php echo htmlspecialchars($cat['product_count']); ?></td>
                            <td>
                                <button class="small btn secondary" onclick="openEditCategory('<?php echo htmlspecialchars($cat['category']); ?>')">Edit</button>
                                <a class="small btn" style="background:#ff5b5b; padding:6px 8px;" 
                                   href="?delete_category=<?php echo urlencode($cat['category']); ?>" 
                                   onclick="return confirm('Delete category <?php echo htmlspecialchars(addslashes($cat['category'])); ?>? This will only work if no products are assigned to this category.')">
                                   Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add/Edit Category Form -->
            <div class="panel" id="category-form-section" style="margin-top:16px;">
                <h4 style="margin:0 0 8px 0;" id="categoryFormTitle">Add Category</h4>
                <form id="categoryForm" method="POST" action="admin_dashboard.php">
                    <input type="hidden" name="category_action" id="categoryAction" value="add">
                    <input type="hidden" name="category_id" id="categoryId" value="">
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <input id="categoryName" name="category_name" placeholder="Category name" required 
                               style="padding:10px; border-radius:8px; border:1px solid #e6eefc;" />
                        <div style="display:flex; gap:10px;">
                            <button class="btn" type="submit" id="categorySubmitBtn">Add Category</button>
                            <button type="button" id="clearCategory" class="btn secondary">Clear</button>
                            <button type="button" id="cancelEditCategory" class="btn secondary" style="display:none;">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

       <!-- Deliveries Section -->
<section id="deliveriesSection" style="display:none;">
    <div class="panel" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">Pending Deliveries</h3>
            <div><a href="#deliveries" class="btn secondary small" onclick="document.querySelector('#deliveriesSection').scrollIntoView({behavior:'smooth'})">View All</a></div>
        </div>
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Location</th>
                    <th>Payment</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="pendingTable">
                <?php if (count($pendingDeliveries) === 0): ?>
                    <tr><td colspan="11">No pending deliveries.</td></tr>
                <?php else: foreach ($pendingDeliveries as $p): 
                    $product_image = !empty($p['product_image']) ? htmlspecialchars($p['product_image']) : '';
                    $product_name = !empty($p['product_name']) ? htmlspecialchars($p['product_name']) : 'Unknown Product';
                    $quantity = isset($p['quantity']) ? intval($p['quantity']) : 1;
                    $created_date = date('M j, Y g:i A', strtotime($p['created_at']));
                    
                    // Check stock availability for pending deliveries
                    $stock_available = true;
                    if ($p['status'] === 'Pending' && $p['product_id'] && $p['product_id'] !== 'N/A') {
                        $stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ?");
                        $stock_check->bind_param("i", $p['product_id']);
                        $stock_check->execute();
                        $stock_result = $stock_check->get_result();
                        if ($stock_row = $stock_result->fetch_assoc()) {
                            $stock_available = ($stock_row['stock'] >= $quantity);
                        }
                        $stock_check->close();
                    }
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['id']); ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px; min-width:200px;">
                                <?php if($product_image && file_exists($product_image)): ?>
                                    <img src="<?php echo $product_image; ?>" alt="Product image" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">
                                <?php else: ?>
                                    <div style="width:40px;height:40px;background:#eef3ff;border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:10px;">No img</div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600; font-size:13px;"><?php echo $product_name; ?></div>
                                    <div style="font-size:11px; color:var(--muted);">ID: <?php echo htmlspecialchars($p['product_id'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;"><?php echo $quantity; ?></td>
                        <td><?php echo htmlspecialchars($p['username']); ?></td>
                        <td><?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($p['location'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                        <td><?php echo $p['amount'] ? 'UGX '.number_format($p['amount']):'N/A'; ?></td>
                        <td>
                            <span style="padding:4px 8px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; 
                                <?php if($p['status'] === 'Completed'): ?>
                                    background:#e8f5e8; color:#388e3c;
                                <?php elseif($p['status'] === 'Pending'): ?>
                                    background:#fff3cd; color:#856404;
                                <?php else: ?>
                                    background:#f8d7da; color:#721c24;
                                <?php endif; ?>
                            ">
                                <?php echo htmlspecialchars($p['status']); ?>
                            </span>
                            <?php if ($p['status'] === 'Pending' && !$stock_available): ?>
                                <br><small style="color:var(--danger); font-size:10px;">Insufficient stock</small>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px; color:var(--muted);"><?php echo $created_date; ?></td>
                        <td>
                            <?php if ($p['status'] === 'Pending'): ?>
                                <?php if ($stock_available): ?>
                                    <a class="small btn secondary" href="?complete_delivery=<?php echo $p['id'];?>" onclick="return confirm('Mark this delivery as completed? This will reduce the product stock.')">Complete</a>
                                <?php else: ?>
                                    <button class="small btn secondary" disabled title="Insufficient stock to complete delivery">Complete</button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a class="small btn" style="background:#ff5b5b" href="?delete_delivery=<?php echo $p['id'];?>" onclick="return confirm('Delete this delivery?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

        <!-- Reports Section -->
        <section id="reportsSection" style="display:none;">
            <div class="panel" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Sales Reports & Analytics</h3>
                    <div>
                        <button class="btn secondary small" id="printReportBtn">Print Report</button>
                    </div>
                </div>
            </div>

            <!-- Print Report Form -->
            <div class="panel" style="margin-bottom:16px;">
                <h4 style="margin:0 0 12px 0;">Generate Printable Report</h4>
                <form method="GET" action="admin_dashboard.php" id="reportForm">
                    <div style="display:grid; grid-template-columns: 1fr 1fr auto auto; gap:10px; align-items:end;">
                        <div>
                            <label style="display:block; margin-bottom:5px; font-size:14px;">Report Type</label>
                            <select name="print_report" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #e6eefc;">
                                <option value="sales_summary">Sales Summary</option>
                                <option value="category_sales">Category Sales</option>
                                <option value="stock_report">Stock Report</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px; font-size:14px;">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo date('Y-m-01'); ?>" 
                                   style="width:100%; padding:10px; border-radius:8px; border:1px solid #e6eefc;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px; font-size:14px;">End Date</label>
                            <input type="date" name="end_date" value="<?php echo date('Y-m-t'); ?>" 
                                   style="width:100%; padding:10px; border-radius:8px; border:1px solid #e6eefc;">
                        </div>
                        <div>
                            <button class="btn" type="submit">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Print Report Preview -->
            <?php if (isset($_GET['show_print']) && isset($_SESSION['print_report'])): 
                $report = $_SESSION['print_report'];
            ?>
            <div class="panel" id="printableReport">
                <div style="text-align:center; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #e6eefc;">
                    <h2 style="margin:0; color:var(--primary);">CampusShop - <?php echo htmlspecialchars($report['title']); ?></h2>
                    <p style="margin:5px 0; color:var(--muted);">
                        Period: <?php echo htmlspecialchars($report['start_date']); ?> to <?php echo htmlspecialchars($report['end_date']); ?>
                    </p>
                    <p style="margin:0; color:var(--muted); font-size:14px;">
                        Generated on: <?php echo htmlspecialchars($report['generated_at']); ?>
                    </p>
                </div>

                <?php if ($report['type'] === 'sales_summary'): ?>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="background:linear-gradient(90deg,var(--primary),var(--primary-2)); color:#fff;">
                            <th style="padding:12px; text-align:left;">Date</th>
                            <th style="padding:12px; text-align:left;">Product</th>
                            <th style="padding:12px; text-align:left;">Status</th>
                            <th style="padding:12px; text-align:right;">Orders</th>
                            <th style="padding:12px; text-align:right;">Total Amount</th>
                            <th style="padding:12px; text-align:right;">Avg. Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        $total_orders = 0;
                        foreach ($report['data'] as $row): 
                            // Ensure we have numeric values
                            $order_count = intval($row['order_count'] ?? 0);
                            $total_amount = floatval($row['total_amount'] ?? 0);
                            $avg_value = floatval($row['avg_order_value'] ?? 0);
                            
                            $grand_total += $total_amount;
                            $total_orders += $order_count;
                        ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;"><?php echo htmlspecialchars($row['sale_date'] ?? 'N/A'); ?></td>
                            <td style="padding:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <?php if(!empty($row['product_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['product_image']); ?>" alt="Product" style="width:30px; height:30px; object-fit:cover; border-radius:4px;">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($row['product_name'] ?? 'Various Products'); ?></div>
                                        <?php if(!empty($row['product_id'])): ?>
                                            <div style="font-size:11px; color:var(--muted);">ID: <?php echo htmlspecialchars($row['product_id']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px;">
                                <span style="padding:3px 6px; border-radius:8px; font-size:10px; font-weight:600; text-transform:uppercase;
                                    <?php if(($row['status'] ?? '') === 'Completed'): ?>
                                        background:#e8f5e8; color:#388e3c;
                                    <?php elseif(($row['status'] ?? '') === 'Pending'): ?>
                                        background:#fff3cd; color:#856404;
                                    <?php else: ?>
                                        background:#f8d7da; color:#721c24;
                                    <?php endif; ?>
                                ">
                                    <?php echo htmlspecialchars($row['status'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td style="padding:10px; text-align:right;"><?php echo number_format($order_count); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($total_amount, 2); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($avg_value, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8fafc; font-weight:bold;">
                            <td style="padding:12px;" colspan="3">TOTAL</td>
                            <td style="padding:12px; text-align:right;"><?php echo number_format($total_orders); ?></td>
                            <td style="padding:12px; text-align:right;">UGX <?php echo number_format($grand_total, 2); ?></td>
                            <td style="padding:12px; text-align:right;">UGX <?php echo number_format($total_orders > 0 ? $grand_total / $total_orders : 0, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php elseif ($report['type'] === 'category_sales'): ?>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="background:linear-gradient(90deg,var(--primary),var(--primary-2)); color:#fff;">
                            <th style="padding:12px; text-align:left;">Category</th>
                            <th style="padding:12px; text-align:right;">Products</th>
                            <th style="padding:12px; text-align:right;">Inventory Value</th>
                            <th style="padding:12px; text-align:right;">Avg. Price</th>
                            <th style="padding:12px; text-align:right;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($report['data'] as $row) {
                            $grand_total += floatval($row['total_sales'] ?? 0);
                        }
                        foreach ($report['data'] as $row): 
                            $product_count = intval($row['product_count'] ?? 0);
                            $inventory_value = floatval($row['total_sales'] ?? 0);
                            $avg_price = floatval($row['avg_price'] ?? 0);
                            $percentage = $grand_total > 0 ? ($inventory_value / $grand_total) * 100 : 0;
                        ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;"><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                            <td style="padding:10px; text-align:right;"><?php echo number_format($product_count); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($inventory_value, 2); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($avg_price, 2); ?></td>
                            <td style="padding:10px; text-align:right;"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8fafc; font-weight:bold;">
                            <td style="padding:12px;">TOTAL</td>
                            <td style="padding:12px; text-align:right;"><?php echo number_format(array_sum(array_column($report['data'], 'product_count'))); ?></td>
                            <td style="padding:12px; text-align:right;">UGX <?php echo number_format($grand_total, 2); ?></td>
                            <td style="padding:12px; text-align:right;">—</td>
                            <td style="padding:12px; text-align:right;">100%</td>
                        </tr>
                    </tbody>
                </table>
                <?php elseif ($report['type'] === 'stock_report'): ?>
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="background:linear-gradient(90deg,var(--primary),var(--primary-2)); color:#fff;">
                            <th style="padding:12px; text-align:left;">Product</th>
                            <th style="padding:12px; text-align:left;">Category</th>
                            <th style="padding:12px; text-align:right;">Price</th>
                            <th style="padding:12px; text-align:right;">Stock</th>
                            <th style="padding:12px; text-align:right;">Value</th>
                            <th style="padding:12px; text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_stock_value = 0;
                        $total_items = 0;
                        foreach ($report['data'] as $row): 
                            $stock = intval($row['stock'] ?? 0);
                            $price = floatval($row['price'] ?? 0);
                            $value = $stock * $price;
                            $total_stock_value += $value;
                            $total_items += $stock;
                        ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;"><?php echo htmlspecialchars($row['name'] ?? 'N/A'); ?></td>
                            <td style="padding:10px;"><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($price, 2); ?></td>
                            <td style="padding:10px; text-align:right;"><?php echo number_format($stock); ?></td>
                            <td style="padding:10px; text-align:right;">UGX <?php echo number_format($value, 2); ?></td>
                            <td style="padding:10px; text-align:center;">
                                <?php if ($stock == 0): ?>
                                    <span style="color:var(--danger); font-weight:600;">Out of Stock</span>
                                <?php elseif ($stock <= 5): ?>
                                    <span style="color:var(--warning); font-weight:600;">Low Stock</span>
                                <?php else: ?>
                                    <span style="color:var(--success); font-weight:600;">In Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8fafc; font-weight:bold;">
                            <td style="padding:12px;" colspan="3">TOTAL</td>
                            <td style="padding:12px; text-align:right;"><?php echo number_format($total_items); ?></td>
                            <td style="padding:12px; text-align:right;">UGX <?php echo number_format($total_stock_value, 2); ?></td>
                            <td style="padding:12px; text-align:center;">—</td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <div style="margin-top:20px; text-align:center;">
                    <button class="btn" onclick="window.print()">Print Report</button>
                    <a href="admin_dashboard.php?section=reports" class="btn secondary">Close</a>
                </div>
            </div>
            <?php unset($_SESSION['print_report']); endif; ?>

            <!-- Existing Charts -->
            <div class="panel">
                <h3 style="margin:0 0 10px 0;">Sales Analytics</h3>
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

        <!-- Feedback Section -->
        <section id="feedbackSection" style="display:none;">
            <div class="panel" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">User Feedback Management</h3>
                    <div>
                        <span class="btn secondary small">
                            Total: <?php echo $totalFeedbackCount; ?> | 
                            Unread: <span style="color:#ff4757;"><?php echo $unreadFeedbackCount; ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <?php if (count($feedbackList) === 0): ?>
                    <div style="text-align:center; padding:40px; color:var(--muted);">
                        <i class="fa fa-comments" style="font-size:48px; margin-bottom:16px; opacity:0.5;"></i>
                        <p>No user feedback received yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($feedbackList as $feedback): ?>
                        <div class="feedback-item <?php echo $feedback['status'] === 'new' ? 'unread' : ''; ?>">
                            <div class="feedback-header">
                                <div>
                                    <span class="feedback-user">
                                        <?php echo htmlspecialchars($feedback['name']); ?>
                                        <?php if ($feedback['user_id']): ?>
                                            <small>(User ID: <?php echo $feedback['user_id']; ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                    <div class="feedback-date">
                                        <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($feedback['created_at']))); ?>
                                        <?php if ($feedback['status'] === 'new'): ?>
                                            <span class="feedback-status status-new">New</span>
                                        <?php elseif ($feedback['status'] === 'read'): ?>
                                            <span class="feedback-status status-read">Read</span>
                                        <?php elseif ($feedback['status'] === 'replied'): ?>
                                            <span class="feedback-status status-replied">Replied</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <?php if ($feedback['status'] === 'new'): ?>
                                        <a href="?mark_feedback_read=<?php echo $feedback['id']; ?>" class="small btn secondary">Mark Read</a>
                                    <?php endif; ?>
                                    <button class="small btn secondary reply-feedback-btn" 
                                            data-id="<?php echo $feedback['id']; ?>"
                                            data-email="<?php echo htmlspecialchars($feedback['email']); ?>"
                                            data-name="<?php echo htmlspecialchars($feedback['name']); ?>">
                                        <?php echo $feedback['admin_reply'] ? 'Edit Reply' : 'Reply'; ?>
                                    </button>
                                    <a href="?delete_feedback=<?php echo $feedback['id']; ?>" class="small btn" style="background:#ff5b5b;" onclick="return confirm('Delete this feedback?')">Delete</a>
                                </div>
                            </div>
                            
                            <div class="feedback-message">
                                <strong>Message:</strong><br>
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            
                            <?php if ($feedback['email']): ?>
                                <div style="margin-top:8px; font-size:13px; color:var(--muted);">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($feedback['email']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($feedback['admin_reply']): ?>
                                <div class="feedback-reply">
                                    <strong>Your Reply (<?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($feedback['replied_at']))); ?>):</strong><br>
                                    <?php echo nl2br(htmlspecialchars($feedback['admin_reply'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

        <!-- Reply Feedback Modal -->
        <div id="replyFeedbackModal" style="display:none;">
            <div class="modal-overlay" id="replyOverlay">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Reply to feedback">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3>Reply to Feedback</h3>
                        <button class="btn secondary" id="closeReplyModal">Close</button>
                    </div>
                    <form id="replyForm" method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="reply_feedback" value="1">
                        <input type="hidden" id="feedback_id" name="feedback_id">
                        
                        <div style="margin-bottom:15px;">
                            <strong>To:</strong> 
                            <span id="replyUserName"></span> 
                            (<span id="replyUserEmail"></span>)
                        </div>
                        
                        <div style="background:#f8f9fa; padding:12px; border-radius:6px; margin-bottom:15px;">
                            <strong>Original Message:</strong>
                            <div id="originalMessage" style="margin-top:8px; font-style:italic;"></div>
                        </div>
                        
                        <label for="admin_reply">Your Reply:</label>
                        <textarea id="admin_reply" name="admin_reply" rows="6" required 
                                  placeholder="Type your response to the user..."
                                  style="width:100%; padding:12px; border-radius:8px; border:1px solid #e6eefc; margin-top:8px;"></textarea>
                        
                        <div style="text-align:right; margin-top:15px;">
                            <button type="submit" class="btn">Send Reply</button>
                            <button type="button" class="btn secondary" id="cancelReply">Cancel</button>
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
        categories: document.getElementById('categoriesSection'),
        deliveries: document.getElementById('deliveriesSection'),
        reports: document.getElementById('reportsSection'),
        notifications: document.getElementById('notificationsSection'),
        feedback: document.getElementById('feedbackSection'),
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
        document.querySelectorAll('#categoriesTable tr').forEach(r => {
            const txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
        document.querySelectorAll('#notificationsSection table tbody tr').forEach(r => {
            const txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(q) ? '' : 'none';
        });
        // Search in feedback section
        document.querySelectorAll('.feedback-item').forEach(item => {
            const txt = item.innerText.toLowerCase();
            item.style.display = txt.includes(q) ? '' : 'none';
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
        console.log('Fetching product with ID:', id);
        fetch('admin_dashboard.php?fetch_product=' + encodeURIComponent(id))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' . response.status + ' ' . response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
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

    function closeEditProduct() {
        document.getElementById('editProductModal').style.display = 'none';
    }

    // Category Management Functions
    function openEditCategory(categoryName) {
        document.getElementById('categoryAction').value = 'edit';
        document.getElementById('categoryFormTitle').textContent = 'Edit Category';
        document.getElementById('categoryName').value = categoryName;
        document.getElementById('categoryId').value = categoryName;
        document.getElementById('categorySubmitBtn').textContent = 'Update Category';
        document.getElementById('cancelEditCategory').style.display = 'inline-block';
        
        document.getElementById('category-form-section').scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function resetCategoryForm() {
        document.getElementById('categoryForm').reset();
        document.getElementById('categoryAction').value = 'add';
        document.getElementById('categoryFormTitle').textContent = 'Add Category';
        document.getElementById('categorySubmitBtn').textContent = 'Add Category';
        document.getElementById('cancelEditCategory').style.display = 'none';
        document.getElementById('categoryId').value = '';
    }

    // Event Listeners for Category Management
    document.getElementById('addCategoryBtn').addEventListener('click', function() {
        resetCategoryForm();
        document.getElementById('category-form-section').scrollIntoView({behavior: 'smooth', block: 'center'});
    });

    document.getElementById('clearCategory').addEventListener('click', resetCategoryForm);
    document.getElementById('cancelEditCategory').addEventListener('click', resetCategoryForm);

    // Print Report functionality
    document.getElementById('printReportBtn')?.addEventListener('click', function() {
        document.getElementById('reportForm').scrollIntoView({behavior: 'smooth', block: 'center'});
    });

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

    // Feedback Reply Functionality
    const replyFeedbackModal = document.getElementById('replyFeedbackModal');
    const closeReplyModal = document.getElementById('closeReplyModal');
    const cancelReply = document.getElementById('cancelReply');
    const replyBtns = document.querySelectorAll('.reply-feedback-btn');

    replyBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const feedbackId = btn.getAttribute('data-id');
            const userName = btn.getAttribute('data-name');
            const userEmail = btn.getAttribute('data-email');
            
            // Find the original message from the feedback item
            const feedbackItem = btn.closest('.feedback-item');
            const originalMessage = feedbackItem.querySelector('.feedback-message').innerText.replace('Message:\n', '').trim();
            
            // Populate the reply form
            document.getElementById('feedback_id').value = feedbackId;
            document.getElementById('replyUserName').textContent = userName;
            document.getElementById('replyUserEmail').textContent = userEmail;
            document.getElementById('originalMessage').textContent = originalMessage;
            document.getElementById('admin_reply').value = ''; // Clear previous reply
            
            // Show existing reply if editing
            const existingReply = feedbackItem.querySelector('.feedback-reply');
            if (existingReply) {
                const replyText = existingReply.innerText.replace(/Your Reply.*:\n/, '').trim();
                document.getElementById('admin_reply').value = replyText;
            }
            
            replyFeedbackModal.style.display = 'block';
        });
    });

    if (closeReplyModal) closeReplyModal.addEventListener('click', () => replyFeedbackModal.style.display = 'none');
    if (cancelReply) cancelReply.addEventListener('click', () => replyFeedbackModal.style.display = 'none');

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