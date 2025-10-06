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
            --border-color: #d1d5db; /* Darkened for higher opacity */
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-gray);
            background: var(--white);
            padding-bottom: 60px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Unchanged Navigation Styles */
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

        .cart-btn,
        .favorites-btn {
            position: relative;
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

        /* Modified Profile Styles */
        .profile-container {
            max-width: 1000px;
            margin: 3rem auto;
            padding: 0 15px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            background: var(--white);
        }

        .profile-header h1 {
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .profile-content {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .profile-left,
        .profile-right {
            flex: 1;
            min-width: 300px;
        }

        .profile-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-section:last-child {
            border-bottom: none;
        }

        .profile-section h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1rem;
        }

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 400px;
        }

        .profile-form label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-gray);
        }

        .profile-form input[type="text"],
        .profile-form input[type="file"] {
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .profile-form button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .profile-form button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .message {
            font-size: 0.9rem;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .message.success {
            background: var(--success-green);
            color: var(--white);
        }

        .message.error {
            background: var(--error-red);
            color: var(--white);
        }

        .notifications-list,
        .orders-list {
            list-style: none;
        }

        .notification-item,
        .order-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .notification-item:last-child,
        .order-item:last-child {
            border-bottom: none;
        }

        .notification-item p,
        .order-item p {
            margin: 0.5rem 0;
            color: var(--text-gray);
        }

        .order-item .status {
            font-weight: 500;
            color: var(--primary-green);
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .payment-option {
            text-align: center;
        }

        .payment-option img {
            width: 50px;
            height: auto;
            margin-bottom: 0.5rem;
        }

        .payment-option p {
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .help-center {
            text-align: center;
        }

        .help-center p {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .help-center a {
            color: var(--secondary-green);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .help-center a:hover {
            text-decoration: underline;
        }

        .account-settings {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .account-settings a {
            color: var(--secondary-green);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .account-settings a:hover {
            text-decoration: underline;
        }

        /* Unchanged Bottom Bar and Footer Styles */
        .floating-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1000;
            font-size: 2rem;
        }

        .floating-btn {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            position: relative;
            text-decoration: none;
        }

        .floating-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: scale(1.1);
        }

        .floating-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--dark-gray);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .floating-btn:hover::after {
            opacity: 1;
            visibility: visible;
        }

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

        .bottom-bar-actions a::after,
        .bottom-bar-actions button::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-gray);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .bottom-bar-actions a:hover::after,
        .bottom-bar-actions button:hover::after {
            opacity: 1;
            visibility: visible;
        }

        .feedback-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feedback-form label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-gray);
        }

        .feedback-form input,
        .feedback-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .feedback-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .feedback-form button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .feedback-form button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .feedback-message {
            font-size: 0.9rem;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .feedback-message.success {
            background: var(--success-green);
            color: var(--white);
        }

        .feedback-message.error {
            background: var(--error-red);
            color: var(--white);
        }
        .chatbot-btn {
            background: var(--light-gray);
            color: blue;
            padding: 10px 10px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, color 0.3s ease;
            margin: 0.5rem 0;
        }

        .chatbot-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .chatbot-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .chatbot-form input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .chatbot-form button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .chatbot-form button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .chatbot-messages {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--light-gray);
            margin-bottom: 1rem;
        }

        .chatbot-message {
            margin: 0.5rem 0;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .chatbot-message.user {
            background: var(--primary-green);
            color: var(--white);
            margin-left: 20%;
            border-bottom-right-radius: 0;
        }

        .chatbot-message.bot {
            background: var(--white);
            color: var(--dark-gray);
            margin-right: 20%;
            border-bottom-left-radius: 0;
        }

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

        .modal h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1.5rem;
        }

        footer {
            background: var(--dark-gray);
            color: var(--white);
            padding: 2rem 0;
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

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.9rem;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }

            .header-top {
                justify-content: space-between;
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

            .floating-buttons {
                display: none;
            }

            .profile-container {
                padding: 0 10px;
            }

            .profile-picture {
                width: 120px;
                height: 120px;
            }

            .profile-header h1 {
                font-size: 1.6rem;
            }

            .profile-content {
                flex-direction: column;
            }

            .profile-left,
            .profile-right {
                width: 100%;
            }

            .profile-section h2 {
                font-size: 1.2rem;
            }

            .payment-options {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            }
        }

        @media (min-width: 769px) {
            .menu-icon,
            .mobile-menu,
            .bottom-bar {
                display: none;
            }

            .search-bar,
            .header-actions,
            nav {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }

            .logo {
                font-size: 1.2rem;
            }

            .logo-icon {
                width: 30px;
                height: 30px;
            }

            .profile-picture {
                width: 100px;
                height: 100px;
            }

            .profile-header h1 {
                font-size: 1.3rem;
            }

            .profile-section h2 {
                font-size: 1rem;
            }

            .profile-form input[type="text"],
            .profile-form input[type="file"],
            .profile-form button {
                font-size: 0.8rem;
                padding: 8px;
            }
            .bottom-bar-actions a, .bottom-bar-actions button {
                padding: 6px;
                font-size: 1.2rem;
                width: 36px;
                height: 36px;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
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
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <div class="logo-icon"><img style="height: 50px; width: 50px; border-radius:25px;" src="images/download.png" alt=""></div>
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
                    <a href="logout.php" class="header-btn">Logout</a>
                    <a href="favorites.php" class="header-btn favorites-btn">
                        ❤️ Favorites
                        <span class="favorites-count"><?php echo $favorites_count; ?></span>
                    </a>
                    <a href="cart.php" class="header-btn cart-btn">
                        🛒 Cart
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
            <a href="profile.php" data-tooltip="Profile">👤</a>
            <a href="favorites.php" data-tooltip="Favorites">❤️ <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
            <a href="cart.php" data-tooltip="Cart">🛒 <span class="cart-count"><?php echo $cart_count; ?></span></a>
            <button class="feedback-btn" id="mobile-feedback-btn" data-tooltip="Feedback">💬</button>
            <a href="https://wa.me/+256755087665" target="_blank" data-tooltip="Help">📞</a>
        </div>
    </div>

    <div class="floating-buttons">
        <button class="floating-btn feedback-btn" id="floating-feedback-btn" data-tooltip="Feedback">💬</button>
        <a href="https://wa.me/+256755087665" class="floating-btn" target="_blank" data-tooltip="Help">📞</a>
    </div>

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
    <div class="modal" id="chatbot-modal" role="dialog" aria-labelledby="chatbot-title">
        <div class="modal-content">
            <button class="modal-close" id="chatbot-modal-close" aria-label="Close chatbot">&times;</button>
            <h2 id="chatbot-title">Chat with CampusShop Support</h2>
            <div class="chatbot-messages" id="chatbot-messages">
                <!-- Messages will be dynamically added here -->
            </div>
            <form id="chatbot-form" class="chatbot-form">
                <input type="text" id="chatbot-input" name="chatbot_input" placeholder="Type your message..." required aria-label="Chatbot input">
                <button type="submit" aria-label="Send message">Send</button>
            </form>
        </div>
    </div>

    <section class="profile-container">
        <div class="profile-header">
            <img src="<?php echo $user['profile_picture'] ?: 'images/default_profile.png'; ?>" alt="Profile Picture" class="profile-picture">
            <h1><?php echo htmlspecialchars($username); ?></h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="profile-content">
            <div class="profile-left">
                <div class="profile-section">
                    <h2>Edit Profile</h2>
                    <form class="profile-form" method="POST" enctype="multipart/form-data">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <label for="profile_picture">Profile Picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                        <button type="submit" name="update_profile">Update Profile</button>
                    </form>
                </div>

                <div class="profile-section">
                    <h2>Notifications</h2>
                    <ul class="notifications-list">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-item">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <p><small><?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?></small></p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="notification-item">No notifications available.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="profile-section">
                    <h2>Order History</h2>
                    <ul class="orders-list">
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <li class="order-item">
                                    <p><strong>Order #<?php echo $order['id']; ?></strong></p>
                                    <p>Date: <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                                    <p>Total: UGX <?php echo number_format($order['total_amount']); ?></p>
                                    <p class="status">Status: <?php echo htmlspecialchars($order['status']); ?></p>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="order-item">No orders found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="profile-right">
                <div class="profile-section">
                    <h2>Payment Options</h2>
                    <div class="payment-options">
                        <div class="payment-option">
                            <img src="images/mobile money.png" alt="Mobile Money">
                            <p>Mobile Money</p>
                        </div>
                        <div class="payment-option">
                            <img src="images/paypal.png" alt="Bank Card">
                            <p>Bank Card</p>
                        </div>
                        <div class="payment-option">
                            <img src="images/visa.png" alt="Bank Card">
                            <p>Bank Card</p>
                        </div>
                        <div class="payment-option">
                            <img src="images/pay on delivery.jpeg" alt="Cash on Delivery">
                            <p>Cash on Delivery</p>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h2>Help Center</h2>
                    <div class="help-center">
                        <p>Need assistance? Chat with our support bot or contact us via WhatsApp.</p>
                        <button class="chatbot-btn" id="chatbot-btn">💬 Chat with Us</button>
                        <a href="https://wa.me/+256755087665" target="_blank">📞 Contact Support via WhatsApp</a>
                    </div>
                </div>

                <div class="profile-section">
                    <h2>Account Settings</h2>
                    <div class="account-settings">
                        <a href="change_password.php">Change Password</a>
                        <a href="delete_account.php">Delete Account</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Bugema CampusShop</h3>
                    <p>Your official Bugema University online store, serving students with quality products and exceptional service.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="https://wa.me/+256755087665" target="_blank">Contact</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="Bottles.php">Bottles</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Connect</h3>
                    <ul>
                        <li><a href="#">📧 campusshop@bugemauniv.ac.ug</a></li>
                        <li><a href="https://wa.me/+256755087665" target="_blank">📞 +256 7550 87665</a></li>
                        <li><a href="#">📍 Bugema University</a></li>
                        <li><a href="#">🕒 Mon-Fri 8AM-6PM</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Bugema CampusShop - Bugema University. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.profile-section').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    const searchInput = document.querySelectorAll('.search-input');
    const searchResults = document.querySelectorAll('.search-results');
    let selectedIndex = -1;

    function fetchSuggestions(query, resultsContainer) {
        if (query.length >= 2) {
            fetch(`search.php?query=${encodeURIComponent(query)}&type=autocomplete`)
                .then(response => response.text())
                .then(data => {
                    resultsContainer.innerHTML = data;
                    resultsContainer.classList.add('active');
                    selectedIndex = -1;
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsContainer.innerHTML = '<div class="no-results">Error fetching suggestions</div>';
                    resultsContainer.classList.add('active');
                });
        } else {
            resultsContainer.innerHTML = '';
            resultsContainer.classList.remove('active');
        }
    }

    function fetchFullResults(query, resultsContainer) {
        fetch(`search.php?query=${encodeURIComponent(query)}&type=full`)
            .then(response => response.text())
            .then(data => {
                resultsContainer.innerHTML = data;
                resultsContainer.classList.add('active');
            })
            .catch(error => {
                console.error('Error:', error);
                resultsContainer.innerHTML = '<div class="no-results">Error fetching results</div>';
                resultsContainer.classList.add('active');
            });
    }

    searchInput.forEach(input => {
        input.addEventListener('input', function() {
            const resultsContainer = this.parentElement.querySelector('.search-results');
            fetchSuggestions(this.value.trim(), resultsContainer);
        });

        input.addEventListener('keydown', function(e) {
            const resultsContainer = this.parentElement.querySelector('.search-results');
            const suggestions = resultsContainer.querySelectorAll('.suggestion');
            if (suggestions.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
                updateSelection(suggestions);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(suggestions);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const selectedSuggestion = suggestions[selectedIndex].textContent;
                this.value = selectedSuggestion;
                fetchFullResults(selectedSuggestion, resultsContainer);
            }
        });
    });

    function updateSelection(suggestions) {
        suggestions.forEach((suggestion, index) => {
            suggestion.classList.toggle('selected', index === selectedIndex);
        });
        if (selectedIndex >= 0) {
            const activeInput = document.querySelector('.search-input:focus');
            if (activeInput) activeInput.value = suggestions[selectedIndex].textContent;
        }
    }

    searchResults.forEach(resultsContainer => {
        resultsContainer.addEventListener('click', function(e) {
            const suggestion = e.target.closest('.suggestion');
            if (suggestion) {
                const activeInput = document.querySelector('.search-input:focus') || document.querySelector('.search-input');
                activeInput.value = suggestion.textContent;
                fetchFullResults(suggestion.textContent, resultsContainer);
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-bar') && !e.target.closest('.search-results')) {
            searchResults.forEach(resultsContainer => {
                resultsContainer.classList.remove('active');
            });
        }
    });

    const feedbackBtn = document.getElementById('floating-feedback-btn');
    const mobileFeedbackBtn = document.getElementById('mobile-feedback-btn');
    const feedbackModal = document.getElementById('feedback-modal');
    const feedbackModalClose = document.getElementById('feedback-modal-close');
    const feedbackForm = document.getElementById('feedback-form');
    const feedbackMessage = document.getElementById('feedback-message');

    feedbackBtn.addEventListener('click', function() {
        feedbackModal.style.display = 'flex';
        feedbackMessage.style.display = 'none';
    });

    mobileFeedbackBtn.addEventListener('click', function() {
        feedbackModal.style.display = 'flex';
        feedbackMessage.style.display = 'none';
    });

    feedbackModalClose.addEventListener('click', function() {
        feedbackModal.style.display = 'none';
        feedbackForm.reset();
        feedbackMessage.style.display = 'none';
    });

    feedbackModal.addEventListener('click', function(e) {
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
        fetch('profile.php', { // Changed to profile.php
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
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
            console.error('Error details:', error);
            feedbackMessage.style.display = 'block';
            feedbackMessage.className = 'feedback-message error';
            feedbackMessage.textContent = 'An error occurred: ' + error.message;
        });
    });

    const menuIcon = document.querySelector('.menu-icon');
    const mobileMenu = document.querySelector('.mobile-menu');
    const closeIcon = document.querySelector('.close-icon');

    menuIcon.addEventListener('click', function() {
        mobileMenu.classList.add('active');
    });

    closeIcon.addEventListener('click', function() {
        mobileMenu.classList.remove('active');
    });

    mobileMenu.addEventListener('click', function(e) {
        if (e.target.classList.contains('mobile-nav') || e.target.tagName === 'A') {
            mobileMenu.classList.remove('active');
        }
    });

    // Chatbot functionality
    const chatbotBtn = document.getElementById('chatbot-btn');
    const chatbotModal = document.getElementById('chatbot-modal');
    const chatbotModalClose = document.getElementById('chatbot-modal-close');
    const chatbotForm = document.getElementById('chatbot-form');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');

    function openChatbotModal() {
        chatbotModal.style.display = 'flex';
        chatbotInput.focus();
        scrollToBottom();
    }

    function closeChatbotModal() {
        chatbotModal.style.display = 'none';
        chatbotInput.value = '';
    }

    function scrollToBottom() {
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function addMessage(content, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chatbot-message', sender);
        messageDiv.innerHTML = `<p>${content}</p>`;
        chatbotMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    const responses = {
        'hello': 'Hi! How can I assist you today?',
        'delivery': 'We offer fast campus delivery within 24 hours to your dorm or a campus pickup point. Would you like more details on delivery options?',
        'discount': 'Bugema University students with a valid student ID can enjoy exclusive discounts. Verify your ID at checkout to apply them!',
        'products': 'We offer textbooks, branded jumpers, pens, wall clocks, notebooks, T-shirts, and bottles. Browse categories via the navigation menu!',
        'contact': 'You can reach us at campusshop@bugemauniv.ac.ug or via WhatsApp at +256 7550 87665. Want to call now?',
        'help': 'I’m here to assist! Ask about delivery, discounts, products, or anything else.',
        'default': 'Sorry, I didn’t understand that. Try asking about delivery, discounts, products, or contact info!'
    };

    chatbotBtn.addEventListener('click', openChatbotModal);

    chatbotModalClose.addEventListener('click', closeChatbotModal);

    chatbotModal.addEventListener('click', function(e) {
        if (e.target === chatbotModal) {
            closeChatbotModal();
        }
    });

    chatbotForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = chatbotInput.value.trim();
        if (!message) return;

        // Add user message
        addMessage(message, 'user');

        // Get bot response
        const lowerMessage = message.toLowerCase();
        let response = responses['default'];
        for (const key in responses) {
            if (lowerMessage.includes(key)) {
                response = responses[key];
                break;
            }
        }

        // Add bot response
        setTimeout(() => {
            addMessage(response, 'bot');
        }, 500);

        chatbotInput.value = '';
        chatbotInput.focus();
    });

    chatbotInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatbotForm.dispatchEvent(new Event('submit'));
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (feedbackModal.style.display === 'flex') {
                feedbackModal.style.display = 'none';
                feedbackForm.reset();
                feedbackMessage.style.display = 'none';
            }
            if (chatbotModal.style.display === 'flex') {
                closeChatbotModal();
            }
            if (mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
            }
        }
    });
});
</script>
</body>
</html>

<?php
$conn->close();
?>