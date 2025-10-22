<?php
session_start();
include 'db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user data
$stmt = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Debug: Log profile picture path
if (empty($user['profile_picture'])) {
    error_log("Profile picture is empty for user ID: $user_id");
} else {
    error_log("Profile picture path for user ID $user_id: " . $user['profile_picture']);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $profile_picture = $user['profile_picture'];

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'Uploads/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;

        // Check if Uploads directory exists and is writable
        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            $error = "Uploads directory does not exist or is not writable.";
            error_log("Profile picture upload failed: Uploads directory issue");
        } elseif (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Delete old profile picture if exists
                if ($profile_picture && file_exists($profile_picture)) {
                    unlink($profile_picture);
                }
                $profile_picture = $file_path;
            } else {
                $error = "Failed to upload profile picture.";
                error_log("Profile picture upload failed: Unable to move file to $file_path");
            }
        } else {
            $error = "Invalid file type or size exceeds 5MB.";
            error_log("Profile picture upload failed: Invalid file type ($file_type) or size ($file_size)");
        }
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "File upload error: " . $_FILES['profile_picture']['error'];
        error_log("Profile picture upload error code: " . $_FILES['profile_picture']['error']);
    }

    // Update username and profile picture
    if (!isset($error)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, profile_picture = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_username, $profile_picture, $user_id);
        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            $success = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!$conn || $conn->connect_error) {
        $response = ['success' => false, 'message' => 'Database connection failed.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $name = trim($_POST['feedback_name']);
    $email = trim($_POST['feedback_email']);
    $message = trim($_POST['feedback_message']);
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (empty($name) || empty($email) || empty($message)) {
        $response = ['success' => false, 'message' => 'All fields are required.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Invalid email format.'];
    } elseif (strlen($name) > 255 || strlen($email) > 255) {
        $response = ['success' => false, 'message' => 'Name or email exceeds maximum length.'];
    } else {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, name, email, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $response = ['success' => false, 'message' => 'Failed to prepare query: ' . $conn->error];
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param("isss", $user_id, $name, $email, $message);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Feedback submitted successfully!'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to submit feedback: ' . $stmt->error];
                error_log("Failed to submit feedback: " . $stmt->error);
            }
            $stmt->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Fetch notifications
$stmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch order history
$stmt = $conn->prepare("SELECT o.id, o.order_date, o.total_amount, o.status FROM orders o WHERE o.user_id = ? ORDER BY o.order_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get favorites count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get cart count
$stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Bugema CampusShop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            --border-color: #d1d5db;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-gray);
            background: var(--white);
            padding-bottom: 100px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background: var(--primary-green);
            color: var(--white);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .logo-icon {
            width: 35px;
            height: 35px;
            background: var(--accent-yellow);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .menu-icon {
            display: none;
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 250px;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 2rem;
            z-index: 1100;
            transition: right 0.3s ease;
            overflow-y: auto;
        }

        .mobile-menu.active {
            right: 0;
        }

        .close-icon {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--dark-gray);
            cursor: pointer;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .mobile-nav {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .mobile-nav a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 1rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .mobile-nav a:hover,
        .mobile-nav a.active {
            background: var(--secondary-green);
            color: var(--white);
        }

        .mobile-search-bar {
            margin: 1rem 0;
            position: relative;
        }

        .mobile-username {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 500;
            padding: 0.5rem;
            border-radius: 8px;
        }

        .search-bar {
            flex: 1;
            max-width: 400px;
            margin: 0 1rem;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--white);
            color: var(--dark-gray);
            border: 1px solid var(--text-gray);
        }

        .search-input::placeholder {
            color: var(--text-gray);
        }

        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 1rem;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            padding: 5px;
        }

        .search-results.active {
            display: block;
        }

        .search-results .no-results {
            padding: 10px;
            color: var(--text-gray);
            font-size: 0.9rem;
            text-align: center;
        }

        .search-results .suggestion {
            padding: 8px 10px;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--dark-gray);
            border-bottom: 1px solid var(--light-gray);
            transition: background 0.2s ease;
        }

        .search-results .suggestion:last-child {
            border-bottom: none;
        }

        .search-results .suggestion:hover,
        .search-results .suggestion.selected {
            background: var(--primary-green);
            color: var(--white);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-btn {
            background: var(--accent-yellow);
            color: var(--dark-gray);
           padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .header-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .username a {
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .username a:hover {
            color: var(--accent-yellow);
            text-decoration: underline;
        }

        .cart-btn, .favorites-btn {
            position: relative;
            font-size: 20px;
        }

        .cart-count,
        .favorites-count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--error-red);
            color: var(--white);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        nav {
            margin-top: 0.5rem;
            background: var(--secondary-green);
            border-radius: 8px;
            padding: 0.5rem;
            padding-left: 6.5rem;
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            list-style: none;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary-green);
        }

        .profile-container {
            display: flex;
            min-height: calc(100vh - 70px);
            gap: 20px;
        }

        .profile-left {
            width: 30%;
            background-color: var(--white);
            padding: 30px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-height: calc(100vh - 150px);
            margin-left: 30px;
            margin-bottom: 30px;
            overflow-y: auto;
            position: fixed;
            margin-top: 30px;
        }

        .profile-left img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid var(--light-gray);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-left h2 {
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
            color: var(--primary-green);
            margin-bottom: 25px;
        }

        .profile-left form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-left label {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .profile-left input[type="text"],
        .profile-left input[type="file"] {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark-gray);
            transition: border-color 0.3s ease;
        }

        .profile-left input[type="text"]:focus,
        .profile-left input[type="file"]:focus {
            border-color: var(--secondary-green);
            outline: none;
            box-shadow: 0 0 5px rgba(69, 145, 231, 0.3);
        }

        .profile-left button {
            padding: 12px;
            background-color: var(--primary-green);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .profile-left button:hover {
            background-color: var(--secondary-green);
            transform: translateY(-2px);
        }

        .profile-right {
            flex: 1;
            margin-left: 32%;
            padding: 30px;
        }

        .section {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-2px);
        }

        .section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-green);
        }

        .notification {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .notification:last-child {
            border-bottom: none;
        }

        .notification p {
            font-size: 1rem;
            color: var(--dark-gray);
            margin-bottom: 8px;
        }

        .notification small {
            font-size: 0.85rem;
            color: var(--text-gray);
            font-style: italic;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: var(--white);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        th {
            background-color: var(--light-gray);
            color: var(--primary-green);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: var(--dark-gray);
        }

        tr:hover {
            background-color: var(--light-gray);
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .payment-option {
            text-align: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .payment-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .payment-option img {
            width: 60px;
            height: auto;
            margin-bottom: 10px;
        }

        .payment-option p {
            font-size: 0.95rem;
            color: var(--dark-gray);
            font-weight: 500;
        }

        /* HELP CENTER - UPDATED WITH RETURN POLICY */
        .help-center {
            text-align: left;
        }

        .help-center h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--secondary-green);
        }

        .return-policy {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--primary-green);
        }

        .return-policy h5 {
            color: var(--primary-green);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .return-policy ul {
            list-style: disc;
            padding-left: 20px;
            margin: 10px 0;
        }

        .return-policy li {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 5px;
            line-height: 1.5;
        }

        .help-center-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .help-center p {
            font-size: 0.95rem;
            color: var(--text-gray);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .chatbot-btn {
            background: var(--light-gray);
            color: var(--primary-green);
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .chatbot-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: translateY(-2px);
        }

        .account-settings {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .account-settings a {
            color: var(--secondary-green);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            padding: 10px 15px;
            border: 1px solid var(--secondary-green);
            width: 20%;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .account-settings a:hover {
            background-color: var(--secondary-green);
            color: var(--white);
        }

        .message {
            font-size: 0.95rem;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .message.success {
            background: var(--success-green);
            color: var(--white);
        }

        .message.error {
            background: var(--error-red);
            color: var(--white);
        }

        /* SCROLL TO TOP BUTTON - NEW */
        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-green);
            color: var(--white);
            border: none;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(9, 27, 190, 0.3);
            transition: all 0.3s ease;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }

        .scroll-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .scroll-to-top:hover {
            background: var(--secondary-green);
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 6px 20px rgba(69, 145, 231, 0.4);
        }

        .scroll-to-top:active {
            transform: translateY(0) scale(0.95);
        }

        /* BOTTOM BAR */
        .bottom-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 0.5rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .bottom-bar-actions {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .bottom-bar-actions a,
        .bottom-bar-actions button {
            color: var(--dark-gray);
            padding: 8px;
            border-radius: 50%;
            text-decoration: none;
            font-weight: 500;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: background 0.3s ease, color 0.3s ease;
            position: relative;
            border: none;
        }

        .bottom-bar-actions a:hover,
        .bottom-bar-actions button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        /* MODALS */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: fadeIn 0.3s ease-out;
            text-align: center;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-gray);
            cursor: pointer;
        }

        .modal-close:hover {
            color: var(--error-red);
        }

        .feedback-form,
        .chatbot-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feedback-form input,
        .feedback-form textarea,
        .chatbot-form input {
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .feedback-form button,
        .chatbot-form button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        /* FOOTER */
        footer {
            background: var(--dark-gray);
            color: var(--white);
            padding: 2rem 0;
            margin-top: 50px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .footer-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            color: var(--accent-yellow);
        }

        .footer-section ul li a {
            color: var(--text-gray);
            text-decoration: none;
        }

        .footer-section ul li a:hover {
            color: var(--white);
        }

        .footer-bottom {
            border-top: 1px solid var(--text-gray);
            padding-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .profile-container {
                flex-direction: column;
            }
            .profile-left {
                width: 100%;
                position: relative;
                top: 0;
                max-height: none;
                margin-bottom: 20px;
            }
            .profile-right {
                margin-left: 0;
                padding: 20px;
            }
            .scroll-to-top {
                bottom: 80px;
                left: 20px;
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }
            .menu-icon {
                display: block;
            }
            .search-bar,
            .header-actions,
            nav {
                display: none;
            }
            .bottom-bar {
                display: block;
            }
            .help-center-buttons {
                grid-template-columns: 1fr;
            }
            .return-policy {
                padding: 15px;
            }
            .return-policy li {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .profile-left {
                padding: 20px;
                margin-left: 0;
            }
            .account-settings a {
                width: 50%;
            }
            .bottom-bar-actions a,
            .bottom-bar-actions button {
                width: 36px;
                height: 36px;
                font-size: 1.2rem;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <div class="logo-icon">
                        <img style="height: 50px; width: 50px; border-radius:25px;" src="images/download.png" alt="">
                    </div>
                    <span>Bugema CampusShop</span>
                </div>
                <button class="menu-icon">☰</button>
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search for Textbooks, Branded Jumpers, Pens...">
                    <button class="search-btn">🔍</button>
                    <div class="search-results"></div>
                </div>
                <div class="header-actions">
                    <span class="username"><a href="profile.php">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></a></span>
                    <a href="logout.php" class="header-btn"><i class="fas fa-sign-out-alt"></i></a>
                    <a href="favorites.php" class="header-btn favorites-btn">
                        <i class="fas fa-heart"></i>
                        <span class="favorites-count"><?php echo $favorites_count; ?></span>
                    </a>
                    <a href="cart.php" class="header-btn cart-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                </div>
            </div>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="textbooks.php">Textbooks</a></li>
                    <li><a href="Branded Jumpers.php">Branded Jumpers</a></li>
                    <li><a href="Pens.php">Pens</a></li>
                    <li><a href="Wall Clocks.php">Wall Clocks</a></li>
                    <li><a href="Note Books.php">Note Books</a></li>
                    <li><a href="T-Shirts.php">T-Shirts</a></li>
                    <li><a href="Bottles.php">Bottles</a></li>
                    <li><a href="favorites.php">Favorites</a></li>
                </ul>
            </nav>
            <div class="mobile-menu">
                <button class="close-icon">✖</button>
                <span class="mobile-username"><a href="profile.php">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></a></span>
                <div class="mobile-search-bar">
                    <input type="text" class="search-input" placeholder="Search for Textbooks, Branded Jumpers, Pens...">
                    <button class="search-btn">🔍</button>
                    <div class="search-results"></div>
                </div>
                <div class="mobile-nav">
                    <a href="index.php">Home</a>
                    <a href="textbooks.php">Textbooks</a>
                    <a href="Branded Jumpers.php">Branded Jumpers</a>
                    <a href="Pens.php">Pens</a>
                    <a href="Wall Clocks.php">Wall Clocks</a>
                    <a href="Note Books.php">Note Books</a>
                    <a href="T-Shirts.php">T-Shirts</a>
                    <a href="Bottles.php">Bottles</a>
                    <a href="favorites.php">Favorites</a>
                    <a href="profile.php" class="active">Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="bottom-bar">
        <div class="bottom-bar-actions">
            <a href="profile.php" data-tooltip="Profile"><i class="fas fa-user"></i></a>
            <a href="favorites.php" data-tooltip="Favorites"><i class="fas fa-heart"></i> <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
            <a href="cart.php" data-tooltip="Cart"><i class="fas fa-shopping-cart"></i> <span class="cart-count"><?php echo $cart_count; ?></span></a>
            <button class="feedback-btn" id="mobile-feedback-btn" data-tooltip="Feedback"><i class="fas fa-comments"></i></button>
            <a href="https://wa.me/+256755087665" target="_blank" data-tooltip="Help"><i class="fab fa-whatsapp"></i></a>
        </div>
    </div>

    <!-- FEEDBACK MODAL -->
    <div class="modal" id="feedback-modal">
        <div class="modal-content">
            <button class="modal-close" id="feedback-modal-close">&times;</button>
            <h2>Leave Your Feedback</h2>
            <div class="feedback-message" id="feedback-message" style="display: none;"></div>
            <form id="feedback-form" class="feedback-form">
                <label for="feedback_name">Name</label>
                <input type="text" id="feedback_name" name="feedback_name" value="<?php echo htmlspecialchars($username); ?>" required>
                <label for="feedback_email">Email</label>
                <input type="email" id="feedback_email" name="feedback_email" placeholder="Enter your email" required>
                <label for="feedback_message">Message</label>
                <textarea id="feedback_message" name="feedback_message" required></textarea>
                <button type="submit" name="submit_feedback">Submit Feedback</button>
            </form>
        </div>
    </div>

    <!-- CHATBOT MODAL -->
    <div class="modal" id="chatbot-modal">
        <div class="modal-content">
            <button class="modal-close" id="chatbot-modal-close">&times;</button>
            <h2>Chat with CampusShop Support</h2>
            <div class="chatbot-messages" id="chatbot-messages"></div>
            <form id="chatbot-form" class="chatbot-form">
                <input type="text" id="chatbot-input" name="chatbot_input" placeholder="Type your message..." required>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <section class="profile-container">
        <!-- LEFT SIDE - PROFILE -->
        <div class="profile-left">
            <center>
                <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'default-avatar.png'); ?>" alt="Profile Picture">
                <h2><?= htmlspecialchars($user['username']); ?></h2>
            </center>

            <form method="POST" enctype="multipart/form-data">
                <label for="username">Edit Username:</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']); ?>">

                <label for="profile_picture">Change Profile Picture:</label>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/*">

                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>

        <?php if (isset($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- RIGHT SIDE -->
        <div class="profile-right">
            <!-- NOTIFICATIONS -->
            <div class="section">
                <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $note): ?>
                        <div class="notification">
                            <p><?= htmlspecialchars($note['message']); ?></p>
                            <small><?= htmlspecialchars($note['created_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No notifications found.</p>
                <?php endif; ?>
            </div>

            <!-- ORDER HISTORY -->
            <div class="section">
                <h3><i class="fas fa-history"></i> Order History</h3>
                <?php if (!empty($orders)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Total (UGX)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['id']); ?></td>
                                    <td><?= htmlspecialchars($order['order_date']); ?></td>
                                    <td><?= htmlspecialchars(number_format($order['total_amount'], 0)); ?></td>
                                    <td><?= htmlspecialchars($order['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No orders found.</p>
                <?php endif; ?>
            </div>

            <!-- PAYMENT OPTIONS -->
            <div class="section">
                <h3><i class="fas fa-credit-card"></i> Payment Options</h3>
                <div class="payment-options">
                    <div class="payment-option">
                        <img src="images/mobile money.png" alt="Mobile Money">
                        <p>Mobile Money</p>
                    </div>
                    <div class="payment-option">
                        <img src="images/paypal.png" alt="PayPal">
                        <p>PayPal</p>
                    </div>
                    <div class="payment-option">
                        <img src="images/visa.png" alt="Visa">
                        <p>Visa</p>
                    </div>
                    <div class="payment-option">
                        <img src="images/pay on delivery.jpeg" alt="Cash on Delivery">
                        <p>Cash on Delivery</p>
                    </div>
                </div>
            </div>

            <!-- HELP CENTER - UPDATED -->
            <div class="section">
                <h3><i class="fas fa-question-circle"></i> Help Center</h3>
                <div class="help-center">
                    <p>Need assistance? We're here to help you with your shopping experience at Bugema CampusShop.</p>
                    
                    <!-- RETURN POLICY -->
                    <div class="return-policy">
                        <h5><i class="fas fa-shipping-fast"></i> Return Policy</h5>
                        <ul>
                            <li><strong>30-Day Return Window:</strong> Returns accepted within 30 days from delivery date</li>
                            <li><strong>Full Refund:</strong> Complete refund for unused items in original packaging</li>
                            <li><strong>Defective Items:</strong> Free return shipping for damaged or defective products</li>
                            <li><strong>Size Issues:</strong> Exchange available for wrong size clothing (T-Shirts, Jumpers)</li>
                            <li><strong>Non-Returnable:</strong> Personalized items, opened electronics, or hygiene products</li>
                            <li><strong>Process:</strong> Contact us via WhatsApp or chatbot, we'll arrange pickup within 48 hours</li>
                            <li><strong>Refund Time:</strong> Processed within 3-5 business days to original payment method</li>
                        </ul>
                        <p style="font-size: 0.85rem; color: var(--success-green); margin-top: 10px;">
                            <strong>Questions?</strong> Message us anytime!
                        </p>
                    </div>

                    <div class="help-center-buttons">
                        <button class="chatbot-btn" id="chatbot-btn">
                            <i class="fas fa-comments"></i> Chat with Us
                        </button>
                        <a href="https://wa.me/+256755087665" target="_blank" class="chatbot-btn">
                            <i class="fab fa-whatsapp"></i> WhatsApp Support
                        </a>
                        <button class="chatbot-btn" onclick="window.open('mailto:campusshop@bugemauniv.ac.ug')" style="background: var(--accent-yellow);">
                            <i class="fas fa-envelope"></i> Email Support
                        </button>
                    </div>
                </div>
            </div>

            <!-- ACCOUNT SETTINGS -->
            <div class="section">
                <h3><i class="fas fa-cog"></i> Account Settings</h3>
                <div class="account-settings">
                    <a href="change_password.php">
                        <i class="fas fa-lock"></i> Change Password
                    </a>
                    <a href="delete_account.php">
                        <i class="fas fa-user-times"></i> Delete Account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- SCROLL TO TOP BUTTON -->
    <button class="scroll-to-top" id="scrollToTop" title="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // SCROLL TO TOP
        const scrollToTopBtn = document.getElementById('scrollToTop');
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }
        });
        scrollToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // FEEDBACK MODAL
        const feedbackBtn = document.getElementById('floating-feedback-btn') || document.getElementById('mobile-feedback-btn');
        const feedbackModal = document.getElementById('feedback-modal');
        const feedbackModalClose = document.getElementById('feedback-modal-close');
        const feedbackForm = document.getElementById('feedback-form');
        const feedbackMessage = document.getElementById('feedback-message');

        if (feedbackBtn) {
            feedbackBtn.addEventListener('click', () => {
                feedbackModal.style.display = 'flex';
                feedbackMessage.style.display = 'none';
            });
        }

        feedbackModalClose.addEventListener('click', () => {
            feedbackModal.style.display = 'none';
            feedbackForm.reset();
            feedbackMessage.style.display = 'none';
        });

        feedbackModal.addEventListener('click', (e) => {
            if (e.target === feedbackModal) {
                feedbackModal.style.display = 'none';
                feedbackForm.reset();
                feedbackMessage.style.display = 'none';
            }
        });

        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(feedbackForm);
            formData.append('submit_feedback', 'true');
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                feedbackMessage.style.display = 'block';
                feedbackMessage.className = `feedback-message ${data.success ? 'success' : 'error'}`;
                feedbackMessage.textContent = data.message;
                if (data.success) {
                    feedbackForm.reset();
                    setTimeout(() => {
                        feedbackModal.style.display = 'none';
                        feedbackMessage.style.display = 'none';
                    }, 2000);
                }
            })
            .catch(error => {
                feedbackMessage.style.display = 'block';
                feedbackMessage.className = 'feedback-message error';
                feedbackMessage.textContent = 'An error occurred: ' + error.message;
            });
        });

        // CHATBOT
        const chatbotBtn = document.getElementById('chatbot-btn');
        const chatbotModal = document.getElementById('chatbot-modal');
        const chatbotModalClose = document.getElementById('chatbot-modal-close');
        const chatbotForm = document.getElementById('chatbot-form');
        const chatbotMessages = document.getElementById('chatbot-messages');
        const chatbotInput = document.getElementById('chatbot-input');

        const responses = {
            'hello': 'Hi! How can I assist you today?',
            'delivery': 'We offer fast campus delivery within 24 hours to your dorm or a campus pickup point.',
            'discount': 'Bugema University students with valid ID enjoy exclusive discounts. Verify at checkout!',
            'products': 'We offer textbooks, branded jumpers, pens, wall clocks, notebooks, T-shirts, and bottles.',
            'contact': 'Reach us at +256 7550 87665 (WhatsApp) or campusshop@bugemauniv.ac.ug',
            'return': 'Our 30-day return policy covers unused items. See Help Center for details!',
            'help': 'Ask about delivery, discounts, products, returns, or contact info.',
            'default': 'Sorry, I didn\'t understand. Try: delivery, discounts, products, or returns!'
        };

        function addMessage(content, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('chatbot-message', sender);
            messageDiv.innerHTML = `<p>${content}</p>`;
            chatbotMessages.appendChild(messageDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        if (chatbotBtn) chatbotBtn.addEventListener('click', () => chatbotModal.style.display = 'flex');
        if (chatbotModalClose) chatbotModalClose.addEventListener('click', () => chatbotModal.style.display = 'none');
        
        chatbotModal.addEventListener('click', (e) => {
            if (e.target === chatbotModal) chatbotModal.style.display = 'none';
        });

        chatbotForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const message = chatbotInput.value.trim();
            if (!message) return;
            
            addMessage(message, 'user');
            const lowerMessage = message.toLowerCase();
            let response = responses['default'];
            
            for (const key in responses) {
                if (lowerMessage.includes(key)) {
                    response = responses[key];
                    break;
                }
            }
            
            setTimeout(() => addMessage(response, 'bot'), 500);
            chatbotInput.value = '';
        });

        // MOBILE MENU
        const menuIcon = document.querySelector('.menu-icon');
        const mobileMenu = document.querySelector('.mobile-menu');
        const closeIcon = document.querySelector('.close-icon');

        if (menuIcon) menuIcon.addEventListener('click', () => mobileMenu.classList.add('active'));
        if (closeIcon) closeIcon.addEventListener('click', () => mobileMenu.classList.remove('active'));
        if (mobileMenu) mobileMenu.addEventListener('click', (e) => {
            if (e.target.tagName === 'A') mobileMenu.classList.remove('active');
        });

        // ESCAPE KEY
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                feedbackModal.style.display = 'none';
                chatbotModal.style.display = 'none';
                mobileMenu.classList.remove('active');
            }
        });
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>