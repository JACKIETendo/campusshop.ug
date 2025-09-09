<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php?redirect=payment.php&cart_id=" . urlencode($_GET['cart_id'] ?? ''));
    exit();
}

$user_id = intval($_SESSION['user_id']);
$cart_items = [];
$total = 0;
$is_single_item = isset($_GET['cart_id']) && is_numeric($_GET['cart_id']);

if ($is_single_item) {
    // Fetch specific cart item
    $cart_id = (int)$_GET['cart_id'];
    $stmt = $conn->prepare("SELECT c.id AS cart_id, p.name, p.price, p.image_path, c.quantity 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.id = ? AND c.user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        header("Location: cart.php");
        exit();
    }
    $cart_items[] = $result->fetch_assoc();
    $total = $cart_items[0]['price'] * $cart_items[0]['quantity'];
    $stmt->close();
} else {
    // Fetch all cart items for the user
    $stmt = $conn->prepare("SELECT c.id AS cart_id, p.name, p.price, p.image_path, c.quantity 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        header("Location: cart.php");
        exit();
    }
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }
    $stmt->close();
}

// Process payment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $conn->real_escape_string($_POST['phone']);
    $location = $conn->real_escape_string($_POST['location']);
    $username = $conn->real_escape_string($_SESSION['username']);
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

    // Validate phone number (Ugandan format: 07X-XXX-XXXX or +256XXX-XXXX)
    if (!preg_match("/^(07[0-9]{8}|\+256[0-9]{8})$/", $phone)) {
        $message = "Invalid phone number. Use format 07X-XXX-XXXX or +256XXX-XXXX.";
    } elseif (!in_array($payment_method, ['Mobile Money', 'Pay on Delivery'])) {
        $message = "Invalid payment method.";
    } elseif (empty($location) || strlen($location) > 255) {
        $message = "Location is required and must be 255 characters or less.";
    } else {
        $amount = $payment_method === 'Mobile Money' ? floatval($_POST['amount']) : NULL;

        // Insert into pending_deliveries for each cart item
        foreach ($cart_items as $item) {
            $cart_id = $item['cart_id'];
            $stmt = $conn->prepare("INSERT INTO pending_deliveries (user_id, username, phone, payment_method, amount, cart_id, location, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("isssdiss", $user_id, $username, $phone, $payment_method, $amount, $cart_id, $location);
            if ($stmt->execute() === false) {
                $message = "Failed to save delivery details: " . $stmt->error;
                error_log("Failed to save delivery: " . $stmt->error);
                $stmt->close();
                break;
            }
            $stmt->close();

            // Remove the item from cart
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        if (!isset($message)) {
            if ($payment_method === 'Mobile Money') {
                $message = "Payment of UGX " . number_format($amount) . " initiated via Mobile Money to " . htmlspecialchars($phone) . ". Please confirm on your phone.";
            } else {
                $message = "Pay on Delivery requested to " . htmlspecialchars($phone) . " at " . htmlspecialchars($location) . ". We will contact you to confirm delivery details.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Bugema CampusShop.ug</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path d="M0,10 Q25,0 50,10 T100,10" fill="none" stroke="%23ffffff" stroke-opacity=".1" stroke-width="2"/></svg>') repeat-x bottom;
            z-index: 0;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative;
            z-index: 1;
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

        .payment-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            margin: 2rem auto;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
            z-index: 1;
        }

        .payment-container h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1rem;
        }

        .product-details {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
            flex-wrap: wrap;
        }

        .product-details img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--light-gray);
        }

        .product-details .info {
            flex: 1;
            min-width: 200px;
        }

        .product-details h3 {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .product-details p {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .payment-container label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-gray);
            text-align: left;
            margin-bottom: 0.5rem;
        }

        .payment-container input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--text-gray);
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            transition: border-color 0.3s ease;
        }

        .payment-container input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 5px rgba(5, 150, 105, 0.3);
        }

        .payment-container button {
            width: 100%;
            padding: 10px;
            background: var(--accent-yellow);
            color: var(--dark-gray);
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            margin-bottom: 0.5rem;
        }

        .payment-container button:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: translateY(-2px);
        }

        .message {
            color: var(--success-green);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            background: rgba(16, 185, 129, 0.1);
            padding: 8px;
            border-radius: 8px;
        }

        .error {
            color: var(--error-red);
            background: rgba(220, 38, 38, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }

            .payment-container {
                max-width: 500px;
                padding: 1.5rem;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-end;
                flex-wrap: wrap;
            }

            .product-details {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .product-details img {
                width: 80px;
                height: 80px;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }

            .payment-container h2 {
                font-size: 1.5rem;
            }

            .product-details h3 {
                font-size: 1rem;
            }

            .product-details p {
                font-size: 0.8rem;
            }

            .product-details img {
                width: 60px;
                height: 60px;
            }

            .payment-container input,
            .payment-container button {
                font-size: 0.8rem;
            }

            .username {
                font-size: 0.9rem;
                padding: 8px 15px;
            }
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
                    <div class="logo-icon">🎓</div>
                    <span>Bugema CampusShop.ug</span>
                </div>
                <div class="header-actions">
                    <span class="username">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="index.php" class="header-btn">Home</a>
                    <a href="cart.php" class="header-btn">Cart</a>
                    <a href="logout.php" class="header-btn">Logout</a>
                </div>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="payment-container">
            <h2>Payment for <?php echo $is_single_item ? htmlspecialchars($cart_items[0]['name']) : 'All Cart Items'; ?></h2>
            <?php foreach ($cart_items as $item): ?>
                <div class="product-details">
                    <img src="<?php echo !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg=='; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="info">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p>Price: UGX <?php echo number_format($item['price']); ?> x <?php echo $item['quantity']; ?> = UGX <?php echo number_format($item['price'] * $item['quantity']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (isset($message)): ?>
                <p class="message <?php echo strpos($message, 'Failed') !== false || strpos($message, 'Invalid') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="payment_method" value="Mobile Money">
                <label for="phone">Mobile Money Number</label>
                <input type="text" id="phone" name="phone" placeholder="e.g., 0771234567 or +256771234567" required>
                <label for="location">Delivery Location</label>
                <input type="text" id="location" name="location" placeholder="e.g., Room 12, Hostel A, Bugema University" required>
                <label for="amount">Total Amount (UGX)</label>
                <input type="number" id="amount" name="amount" value="<?php echo number_format($total, 0, '', ''); ?>" readonly>
                <button type="submit">Pay with Mobile Money</button>
            </form>
            <form method="POST">
                <input type="hidden" name="payment_method" value="Pay on Delivery">
                <label for="phone_cod">Phone Number for Delivery</label>
                <input type="text" id="phone_cod" name="phone" placeholder="e.g., 0771234567 or +256771234567" required>
                <label for="location_cod">Delivery Location</label>
                <input type="text" id="location_cod" name="location" placeholder="e.g., Room 12, Hostel A, Bugema University" required>
                <button type="submit">Pay on Delivery</button>
            </form>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>