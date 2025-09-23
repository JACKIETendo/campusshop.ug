<?php
session_start();
include 'db_connect.php';

// Enable error reporting for debugging (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Initialize guest cart and favorites if not set
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}
if (!isset($_SESSION['guest_favorites'])) {
    $_SESSION['guest_favorites'] = [];
}

// Sync guest favorites to database upon login
if (isset($_SESSION['user_id']) && !empty($_SESSION['guest_favorites'])) {
    $user_id = $_SESSION['user_id'];
    foreach ($_SESSION['guest_favorites'] as $product_id) {
        $product_id = (int)$product_id; // Ensure integer
        $stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt_insert = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $user_id, $product_id);
            if (!$stmt_insert->execute()) {
                error_log("Failed to sync guest favorite for product_id $product_id: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
    // Clear guest favorites after syncing
    $_SESSION['guest_favorites'] = [];
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->bind_param("iiii", $user_id, $product_id, $quantity, $quantity);
        if (!$stmt->execute()) {
            error_log("Failed to add to cart for user_id $user_id, product_id $product_id: " . $stmt->error);
        }
        $stmt->close();
    } else {
        if (!isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id] = $quantity;
        } else {
            $_SESSION['guest_cart'][$product_id] += $quantity;
        }
    }
    
    header("Location: index.php");
    exit();
}

// Handle add/remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    $product_id = (int)$_POST['product_id'];
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt_delete = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
            $stmt_delete->bind_param("ii", $user_id, $product_id);
            if (!$stmt_delete->execute()) {
                error_log("Failed to remove favorite for user_id $user_id, product_id $product_id: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $user_id, $product_id);
            if (!$stmt_insert->execute()) {
                error_log("Failed to add favorite for user_id $user_id, product_id $product_id: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
        $stmt->close();
    } else {
        if (in_array($product_id, $_SESSION['guest_favorites'])) {
            $_SESSION['guest_favorites'] = array_diff($_SESSION['guest_favorites'], [$product_id]);
        } else {
            $_SESSION['guest_favorites'][] = $product_id;
        }
        $_SESSION['guest_favorites'] = array_values($_SESSION['guest_favorites']);
    }
    
    header("Location: index.php");
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Ensure database connection is valid
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

    // Basic validation
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
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get favorites count
$favorites_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $favorites_count = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $favorites_count = count($_SESSION['guest_favorites']);
}

// Set user_email to empty string (no email column in users table)
$user_email = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugema CampusShop - Bugema University Official Store</title>
    <link rel="stylesheet" href="styles.css">
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
            line-height: 1.6;
            color: var(--dark-gray);
            background: var(--light-gray);
            padding-bottom: 60px;
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

        .mobile-nav a:hover, .mobile-nav a.active {
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

        .search-results .product-card {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--light-gray);
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

        .username {
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            transition: color 0.3s ease;
        }

        .username:hover {
            color: var(--accent-yellow);
            text-decoration: underline;
        }

        .cart-btn, .favorites-btn {
            position: relative;
        }

        .cart-count, .favorites-count {
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

        .favorite-btn {
            background: none;
            border: none;
            color: var(--accent-yellow);
            font-size: 1.2rem;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .favorite-btn:hover {
            color: var(--secondary-green);
            transform: scale(1.2);
        }

        .favorite-btn.favorited {
            color: var(--error-red);
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

        .nav-links a:hover, .nav-links a.active {
            background: var(--primary-green);
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

        .bottom-bar-actions a, .bottom-bar-actions button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 8px;
            border-radius: 50%;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: background 0.3s ease, color 0.3s ease;
            position: relative;
            border: none;
        }

        .bottom-bar-actions a:hover, .bottom-bar-actions button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .bottom-bar-actions a::after, .bottom-bar-actions button::after {
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

        .bottom-bar-actions a:hover::after, .bottom-bar-actions button:hover::after {
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

        .hero {
            background: var(--primary-green);
            padding: 3rem 0;
            text-align: left;
            color: var(--white);
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path d="M0,10 Q25,0 50,10 T100,10" fill="none" stroke="%23ffffff" stroke-opacity=".1" stroke-width="2"/></svg>') repeat-x bottom;
        }

        .hero-content {
            position: relative;
            max-width: 600px;
        }

        .hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }

        .hero p {
            font-size: 1rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 0.8rem;
        }

        .cta-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cta-primary {
            background: var(--accent-yellow);
            color: var(--dark-gray);
        }

        .cta-secondary {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--white);
        }

        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .features {
            padding: 3rem 0;
            background: var(--white);
        }

        .features h2 {
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--primary-green);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .feature-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--secondary-green);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }

        .feature-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-green);
        }

        .feature-card p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .best-sellers {
            padding: 3rem 0;
            background: var(--light-gray);
        }

        .best-sellers h2 {
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--primary-green);
        }

        .slider-container {
            position: relative;
            overflow: hidden;
            max-width: 100%;
            margin: 0 auto;
        }

        .slider-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
            animation: slideInFromRight 1s ease-out forwards;
        }

        .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: var(--white);
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 1.5rem;
            z-index: 10;
            transition: background 0.3s ease;
            border-radius: 50%;
        }

        .slider-arrow:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .slider-arrow.left {
            left: 10px;
        }

        .slider-arrow.right {
            right: 10px;
        }

        /* Update product-grid to support larger cards on larger screens */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        /* Ensure product cards have equal height and width */
        .product-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 400px;
            width: 100%;
            max-width: 250px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
            flex-shrink: 0; /* For slider */
        }

        /* Ensure images scale properly */
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            background: var(--light-gray);
            cursor: pointer;
        }

        /* Hide caption in product cards */
        .product-card .caption {
            display: none;
        }

        .product-card h4 {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: black;
        }

        .product-card .price {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .product-card button, .product-card .login-link {
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .product-card button:hover, .product-card .login-link:hover {
            background: var(--accent-yellow);
            color: var(--white);
        }

        .quantity-input {
            width: 60px;
            padding: 5px;
            margin: 0.5rem 0;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
            margin-right: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Modal caption visibility */
        .image-modal-content .caption {
            display: block;
            font-size: 1.1rem;
            color: var(--text-gray);
            margin-bottom: 0.75rem;
        }

        .no-results {
            text-align: center;
            font-size: 1.1rem;
            color: var(--text-gray);
            padding: 2rem;
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

        .image-modal-content {
            background: var(--white);
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 90%;
            width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: fadeIn 0.3s ease-out;
            text-align: center;
        }

        .image-modal-content img {
            max-width: 100%;
            height: auto;
            max-height: 450px;
            object-fit: contain;
            border-radius: 8px;
            margin-bottom: 1rem;
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

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .category-item {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .category-item:hover {
            background: var(--secondary-green);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .category-item a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
        }

        .category-item:hover a {
            color: var(--white);
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

        /* Responsive adjustments for small screens */
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

            .search-bar, .header-actions, nav {
                display: none;
            }

            .bottom-bar {
                display: block;
            }

            .floating-buttons {
                display: none;
            }

            .hero h1 {
                font-size: 1.8rem;
            }

            .hero p {
                font-size: 0.9rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: flex-start;
            }

            .features-grid,
            .category-grid {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }

            .product-card {
                max-width: 100%;
                height: 300px;
                padding: 0.5rem;
            }

            .product-card img {
                height: 150px;
            }

            .product-card h4 {
                font-size: 0.9rem;
            }

            .product-card .price {
                font-size: 0.8rem;
            }

            .product-card button, .product-card .login-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .quantity-input {
                width: 50px;
                padding: 4px;
                font-size: 0.8rem;
            }

            .image-modal-content {
                width: 95%;
                padding: 1rem;
            }

            .image-modal-content img {
                max-height: 300px;
            }

            .image-modal-content h4 {
                font-size: 1rem;
            }

            .image-modal-content .caption {
                font-size: 0.9rem;
            }

            .image-modal-content .price {
                font-size: 1rem;
            }

            .slider-arrow {
                padding: 8px 12px;
                font-size: 1.2rem;
            }
        }

        @media (min-width: 769px) {
            .menu-icon, .mobile-menu, .bottom-bar {
                display: none;
            }

            .search-bar, .header-actions, nav {
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

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }

            .product-card {
                height: 280px;
                padding: 0.5rem;
            }

            .product-card img {
                height: 140px;
            }

            .product-card h4 {
                font-size: 0.9rem;
            }

            .product-card .price {
                font-size: 0.8rem;
            }

            .product-card button, .product-card .login-link {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .quantity-input {
                width: 50px;
                padding: 4px;
                font-size: 0.8rem;
            }

            .hero h1 {
                font-size: 1.5rem;
            }

            .hero p {
                font-size: 0.8rem;
            }

            .bottom-bar-actions a, .bottom-bar-actions button {
                padding: 6px;
                font-size: 0.8rem;
                width: 36px;
                height: 36px;
            }

            .image-modal-content {
                width: 95%;
                padding: 1rem;
            }

            .image-modal-content img {
                max-height: 300px;
            }

            .image-modal-content h4 {
                font-size: 1rem;
            }

            .image-modal-content .caption {
                font-size: 0.9rem;
            }

            .image-modal-content .price {
                font-size: 1rem;
            }

            .slider-arrow {
                padding: 6px 10px;
                font-size: 1rem;
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

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInFromRight {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
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
                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="username">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="logout.php" class="header-btn">Logout</a>
                        <a href="favorites.php" class="header-btn favorites-btn">
                            ❤️ Favorites
                            <span class="favorites-count"><?php echo $favorites_count; ?></span>
                        </a>
                        <a href="cart.php" class="header-btn cart-btn">
                            🛒 Cart
                            <span class="cart-count">
                                <?php
                                $user_id = $_SESSION['user_id'] ?? 0;
                                $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                echo $result->fetch_assoc()['count'] ?? array_sum($_SESSION['guest_cart']);
                                $stmt->close();
                                ?>
                            </span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="header-btn">Login</a>
                        <a href="favorites.php" class="header-btn favorites-btn">
                            ❤️ Favorites
                            <span class="favorites-count"><?php echo $favorites_count; ?></span>
                        </a>
                        <a href="cart.php" class="header-btn cart-btn">
                            🛒 Cart
                            <span class="cart-count"><?php echo array_sum($_SESSION['guest_cart']); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Home</a></li>
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
                <?php if (isset($_SESSION['username'])): ?>
                    <span class="mobile-username">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <?php endif; ?>
                <div class="mobile-search-bar">
                    <input type="text" class="search-input" placeholder="Search for Textbooks, Branded Jumpers, Pens...">
                    <button class="search-btn">🔍</button>
                    <div class="search-results"></div>
                </div>
                <div class="mobile-nav">
                    <a href="index.php" class="active">Home</a>
                    <a href="textbooks.php">Textbooks</a>
                    <a href="Branded Jumpers.php">Branded Jumpers</a>
                    <a href="Pens.php">Pens</a>
                    <a href="Wall Clocks.php">Wall Clocks</a>
                    <a href="Note Books.php">Note Books</a>
                    <a href="T-Shirts.php">T-Shirts</a>
                    <a href="Bottles.php">Bottles</a>
                    <a href="favorites.php">Favorites</a>
                    <a href="logout.php">logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="bottom-bar">
        <div class="bottom-bar-actions">
            <?php if (isset($_SESSION['username'])): ?>
                <a href="favorites.php" data-tooltip="Favorites">❤️ <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
                <a href="cart.php" data-tooltip="Cart">🛒 <span class="cart-count">
                    <?php
                    $user_id = $_SESSION['user_id'] ?? 0;
                    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    echo $result->fetch_assoc()['count'] ?? array_sum($_SESSION['guest_cart']);
                    $stmt->close();
                    ?>
                </span></a>
                <button class="feedback-btn" id="mobile-feedback-btn" data-tooltip="Feedback">💬</button>
                <a href="https://wa.me/+256755087665" target="_blank" data-tooltip="Help">📞</a>
            <?php else: ?>
                <a href="login.php" data-tooltip="Login">🔑</a>
                <a href="favorites.php" data-tooltip="Favorites">❤️ <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
                <a href="cart.php" data-tooltip="Cart">🛒 <span class="cart-count"><?php echo array_sum($_SESSION['guest_cart']); ?></span></a>
                <button class="feedback-btn" id="mobile-feedback-btn" data-tooltip="Feedback">💬</button>
                <a href="https://wa.me/+256755087665" target="_blank" data-tooltip="Help">📞</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="floating-buttons">
        <button class="floating-btn feedback-btn" id="floating-feedback-btn" data-tooltip="Feedback">💬</button>
        <a href="https://wa.me/+256755087665" class="floating-btn" target="_blank" data-tooltip="Help">📞</a>
    </div>

    <section class="hero">
        <div class="container">
            <div class="hero-content fade-in">
                <h1>Your Campus, Your Store</h1>
                <p>Everything you need for academic success and campus life at Bugema University</p>
                <div class="cta-buttons">
                    <a href="cart.php" class="cta-btn cta-primary">Shop Now</a>
                    <button class="cta-btn cta-secondary" id="browse-categories-btn">Browse Categories</button>
                </div>
            </div>
        </div>
    </section>

    <div class="modal" id="categories-modal">
        <div class="modal-content">
            <button class="modal-close" id="modal-close-btn">&times;</button>
            <h2>Browse Categories</h2>
            <div class="category-grid">
                <?php
                $categories = ['Textbooks', 'Bottles', 'Branded Jumpers', 'Wall Clocks', 'Note Books', 'T-Shirts', 'Pens'];
                foreach ($categories as $category):
                    $category_link = str_replace(' ', '%20', $category) . '.php';
                    ?>
                    <div class="category-item">
                        <a href="<?php echo $category_link; ?>"><?php echo htmlspecialchars($category); ?></a>
                    </div>
                    <?php
                endforeach;
                ?>
            </div>
        </div>
    </div>

    <div class="modal" id="feedback-modal">
        <div class="modal-content">
            <button class="modal-close" id="feedback-modal-close">&times;</button>
            <h2>Leave Your Feedback</h2>
            <div class="feedback-message" id="feedback-message" style="display: none;"></div>
            <form id="feedback-form" class="feedback-form">
                <label for="feedback_name">Name</label>
                <input type="text" id="feedback_name" name="feedback_name" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" required>
                <label for="feedback_email">Email</label>
                <input type="email" id="feedback_email" name="feedback_email" placeholder="Enter your email" required>
                <label for="feedback_message">Message</label>
                <textarea id="feedback_message" name="feedback_message" required></textarea>
                <button type="submit" name="submit_feedback">Submit Feedback</button>
            </form>
        </div>
    </div>

    <div class="modal" id="image-modal">
        <div class="modal-content image-modal-content">
            <button class="modal-close" id="image-modal-close">&times;</button>
            <img id="modal-image" src="" alt="Product Image">
            <h4 id="modal-title"></h4>
            <p class="caption" id="modal-caption"></p>
            <p class="price" id="modal-price"></p>
            <form method="POST" action="index.php" id="modal-form">
                <input type="hidden" name="product_id" id="modal-product-id">
                <input type="number" name="quantity" class="quantity-input" value="1" min="1">
                <button type="submit" name="add_to_cart">🛒 Add to Cart</button>
                <button type="submit" name="toggle_favorite" class="favorite-btn" id="modal-favorite-btn">❤️</button>
            </form>
        </div>
    </div>

    <section class="features">
        <div class="container">
            <h2>Why Choose CampusShop?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🚚</div>
                    <h3>Fast Campus Delivery</h3>
                    <p>Get your orders delivered right to your dorm or pickup point within 24 hours</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💸</div>
                    <h3>Student Discounts</h3>
                    <p>Exclusive discounts for Bugema University students with valid student ID</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Academic Resources</h3>
                    <p>All your textbooks, Branded Jumpers, and study materials in one convenient place</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎓</div>
                    <h3>Campus Exclusive</h3>
                    <p>Official Bugema University Pens and branded items</p>
                </div>
            </div>
        </div>
    </section>

    <section class="best-sellers">
        <div class="container">
            <h2>Best Sellers</h2>
            <div class="slider-container">
                <button class="slider-arrow left">◀</button>
                <div class="slider-track">
                    <?php
                    // Fetch 10 random products as "best sellers"
                    $stmt = $conn->prepare("SELECT * FROM products ORDER BY RAND() LIMIT 10");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $product_id = $row['id'];
                            $is_favorited = false;
                            if (isset($_SESSION['user_id'])) {
                                $user_id = $_SESSION['user_id'];
                                $fav_stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND product_id = ?");
                                $fav_stmt->bind_param("ii", $user_id, $product_id);
                                $fav_stmt->execute();
                                $fav_result = $fav_stmt->get_result();
                                $is_favorited = $fav_result->num_rows > 0;
                                $fav_stmt->close();
                            } else {
                                $is_favorited = in_array($product_id, $_SESSION['guest_favorites']);
                            }
                            $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                            ?>
                            <div class="product-card">
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" data-product-id="<?php echo $product_id; ?>" data-title="<?php echo htmlspecialchars($row['name']); ?>" data-caption="<?php echo htmlspecialchars($row['caption'] ?? 'No description available'); ?>" data-price="UGX <?php echo number_format($row['price']); ?>" data-favorited="<?php echo $is_favorited ? 'true' : 'false'; ?>">
                                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                                <p class="caption"><?php echo htmlspecialchars($row['caption'] ?? 'No description available'); ?></p>
                                <p class="price">Price: UGX <?php echo number_format($row['price']); ?></p>
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                    <input type="number" name="quantity" class="quantity-input" value="1" min="1">
                                    <button type="submit" name="add_to_cart">🛒</button>
                                    <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo $is_favorited ? 'favorited' : ''; ?>">❤️</button>
                                </form>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='no-results'>No best sellers available</div>";
                    }
                    $stmt->close();
                    ?>
                </div>
                <button class="slider-arrow right">▶</button>
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

            document.querySelectorAll('.feature-card, .product-card').forEach(el => {
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

            const cartBtn = document.querySelectorAll('.cart-btn');
            cartBtn.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'cart.php';
                });
            });

            const browseCategoriesBtn = document.getElementById('browse-categories-btn');
            const categoriesModal = document.getElementById('categories-modal');
            const modalCloseBtn = document.getElementById('modal-close-btn');
            const feedbackBtn = document.getElementById('floating-feedback-btn');
            const mobileFeedbackBtn = document.getElementById('mobile-feedback-btn');
            const feedbackModal = document.getElementById('feedback-modal');
            const feedbackModalClose = document.getElementById('feedback-modal-close');
            const feedbackForm = document.getElementById('feedback-form');
            const feedbackMessage = document.getElementById('feedback-message');
            const imageModal = document.getElementById('image-modal');
            const imageModalClose = document.getElementById('image-modal-close');
            const modalImage = document.getElementById('modal-image');
            const modalTitle = document.getElementById('modal-title');
            const modalCaption = document.getElementById('modal-caption');
            const modalPrice = document.getElementById('modal-price');
            const modalProductId = document.getElementById('modal-product-id');
            const modalFavoriteBtn = document.getElementById('modal-favorite-btn');

            browseCategoriesBtn.addEventListener('click', function() {
                categoriesModal.style.display = 'flex';
            });

            modalCloseBtn.addEventListener('click', function() {
                categoriesModal.style.display = 'none';
            });

            categoriesModal.addEventListener('click', function(e) {
                if (e.target === categoriesModal) {
                    categoriesModal.style.display = 'none';
                }
            });

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

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (categoriesModal.style.display === 'flex') {
                        categoriesModal.style.display = 'none';
                    }
                    if (feedbackModal.style.display === 'flex') {
                        feedbackModal.style.display = 'none';
                        feedbackForm.reset();
                        feedbackMessage.style.display = 'none';
                    }
                    if (imageModal.style.display === 'flex') {
                        imageModal.style.display = 'none';
                    }
                }
            });

            feedbackForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(feedbackForm);
                formData.append('submit_feedback', 'true');
                fetch('index.php', {
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

            document.querySelectorAll('.product-card img').forEach(img => {
                img.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    const title = this.getAttribute('data-title');
                    const caption = this.getAttribute('data-caption');
                    const price = this.getAttribute('data-price');
                    const favorited = this.getAttribute('data-favorited') === 'true';

                    modalImage.src = this.src;
                    modalImage.alt = title;
                    modalTitle.textContent = title;
                    modalCaption.textContent = caption;
                    modalPrice.textContent = price;
                    modalProductId.value = productId;
                    modalFavoriteBtn.classList.toggle('favorited', favorited);

                    imageModal.style.display = 'flex';
                });
            });

            imageModalClose.addEventListener('click', function() {
                imageModal.style.display = 'none';
            });

            imageModal.addEventListener('click', function(e) {
                if (e.target === imageModal) {
                    imageModal.style.display = 'none';
                }
            });

            // Slider functionality
            const sliderContainer = document.querySelector('.slider-container');
            if (sliderContainer) {
                const sliderTrack = sliderContainer.querySelector('.slider-track');
                const leftArrow = sliderContainer.querySelector('.left');
                const rightArrow = sliderContainer.querySelector('.right');
                const slides = sliderTrack.querySelectorAll('.product-card');
                let currentIndex = 0;
                let visibleSlides = getVisibleSlides();

                function getVisibleSlides() {
                    return window.innerWidth <= 768 ? 2 : 4;
                }

                function updateSlider() {
                    const slideWidth = slides[0].offsetWidth + parseInt(getComputedStyle(slides[0]).marginRight);
                    sliderTrack.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
                }

                leftArrow.addEventListener('click', () => {
                    currentIndex = Math.max(currentIndex - 1, 0);
                    updateSlider();
                });

                rightArrow.addEventListener('click', () => {
                    currentIndex = Math.min(currentIndex + 1, slides.length - visibleSlides);
                    updateSlider();
                });

                window.addEventListener('resize', () => {
                    visibleSlides = getVisibleSlides();
                    updateSlider();
                });

                // Add auto-sliding every 20 seconds
                setInterval(() => {
                    currentIndex = (currentIndex + 1) % (slides.length - visibleSlides + 1);
                    updateSlider();
                }, 20000); // 20 seconds
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>