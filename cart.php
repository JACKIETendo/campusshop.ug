<?php
session_start();
include 'db_connect.php';

// Initialize guest cart if it doesn't exist
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

// Handle adding to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? trim($_POST['quantity']) : '';
    if (!ctype_digit($quantity) || (int)$quantity < 1) {
        $_SESSION['message'] = "Please enter a valid quantity (positive number).";
        header("Location: cart.php");
        exit();
    }
    $quantity = (int)$quantity;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->bind_param("iiii", $user_id, $product_id, $quantity, $quantity);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = "Item added to cart!";
    } else {
        if (!isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id] = $quantity;
        } else {
            $_SESSION['guest_cart'][$product_id] += $quantity;
        }
        $_SESSION['message'] = "Item added to cart! Please log in to checkout.";
    }
    header("Location: cart.php");
    exit();
}

// Handle removing from cart
if (isset($_POST['remove_from_cart'])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $product_id = (int)$_POST['product_id'];
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = "Item removed from cart.";
    } else {
        $product_id = (int)$_POST['product_id'];
        if (isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id]--;
            if ($_SESSION['guest_cart'][$product_id] <= 0) {
                unset($_SESSION['guest_cart'][$product_id]);
            }
            $_SESSION['message'] = "Item removed from cart. Please log in to checkout.";
        }
    }
    header("Location: cart.php");
    exit();
}

// Handle updating quantity
if (isset($_POST['update_quantity'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? trim($_POST['quantity']) : '';
    if (!ctype_digit($quantity) || (int)$quantity < 1) {
        $_SESSION['message'] = "Please enter a valid quantity (positive number).";
        header("Location: cart.php");
        exit();
    }
    $quantity = (int)$quantity;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = "Quantity updated.";
    } else {
        if (isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id] = $quantity;
            $_SESSION['message'] = "Quantity updated. Please log in to checkout.";
        }
    }
    header("Location: cart.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Bugema CampusShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
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
        .header-top .logo span {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-left: 10px;
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

        .cart-btn {
            position: relative;
        }

        .cart-count {
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

        .cart-container {
            padding: 3rem 0;
            background: var(--light-gray);
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-green);
            text-align: center;
            margin: 2rem 0;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .product-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
            animation: fadeIn 0.6s ease-out;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-card img {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: var(--light-gray);
        }

        .product-card h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .product-card p {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .quantity-control input {
            width: 80px;
            padding: 5px;
            border: 1px solid var(--text-gray);
            border-radius: 4px;
            text-align: center;
            font-size: 0.9rem;
        }

        .quantity-control input:invalid {
            border-color: var(--error-red);
            background: rgba(220, 38, 38, 0.1);
        }

        .quantity-control button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .quantity-control button:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .action-buttons button, .action-buttons a {
            width: 50%;
            padding: 8px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .remove-btn {
            background: var(--error-red);
            color: var(--white);
            margin-right: 3.5px;
        }

        .remove-btn:hover {
            background: var(--dark-gray);
            transform: translateY(-2px);
        }

        .checkout-btn {
            background: var(--accent-yellow);
            color: var(--dark-gray);
        }

        .checkout-btn:hover {
            background: var(--secondary-green);
            color: var(--white);
            transform: translateY(-2px);
        }

        .checkout-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            margin: 2rem auto;
        }

        .cart-total {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1rem;
        }

        .main-checkout-btn {
            display: inline-block;
            background: var(--primary-green);
            color: var(--white);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s ease, transform 0.3s ease;
            width: 50%;
        }

        .main-checkout-btn:hover {
            background: var(--secondary-green);
            transform: translateY(-2px);
        }

        .empty-cart {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            margin: 2rem auto;
        }

        .empty-cart p {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        .empty-cart a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }

        .empty-cart a:hover {
            text-decoration: underline;
            color: var(--secondary-green);
        }

        .message {
            background: var(--success-green);
            color: var(--white);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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

            .product-card img {
                max-height: 120px;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }

            h2 {
                font-size: 1.5rem;
            }

            .product-card h4 {
                font-size: 1rem;
            }

            .product-card p,
            .action-buttons button,
            .action-buttons a {
                font-size: 0.8rem;
            }

            .product-card img {
                max-height: 100px;
            }

            .quantity-control input {
                width: 60px;
            }

            .quantity-control button {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            .cart-total {
                font-size: 1.1rem;
            }

            .main-checkout-btn {
                font-size: 1rem;
                padding: 10px 20px;
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
                    <div class="logo-icon"><img style="height: 50px; width: 50px; border-radius:25px;" src="images/download.png" alt=""></div>
                    <span>Bugema CampusShop</span>
                </div>
                <div class="header-actions">
                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="username">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="index.php" class="header-btn">Home</a>
                        <a href="cart.php" class="header-btn cart-btn">
                            Cart
                            <span class="cart-count">
                                <?php
                                $cart_count = 0;
                                if (isset($_SESSION['user_id'])) {
                                    $user_id = $_SESSION['user_id'];
                                    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
                                    $stmt->bind_param("i", $user_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $cart_count = $result->fetch_assoc()['count'] ?? 0;
                                    $stmt->close();
                                } else {
                                    $cart_count = array_sum($_SESSION['guest_cart']);
                                }
                                echo $cart_count;
                                ?>
                            </span>
                        </a>
                        <a href="logout.php" class="header-btn">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="header-btn">Login</a>
                        <a href="cart.php" class="header-btn cart-btn">
                            Cart
                            <span class="cart-count"><?php echo array_sum($_SESSION['guest_cart']); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <section class="cart-container">
        <div class="container">
            <h2>Your Cart</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php
            $total = 0;
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT c.id AS cart_id, p.id, p.name, p.price, p.image_path, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo "<div class='product-grid'>";
                    while ($row = $result->fetch_assoc()) {
                        $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                        $subtotal = $row['price'] * $row['quantity'];
                        $total += $subtotal;
                        echo "<div class='product-card'>";
                        echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'>";
                        echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                        echo "<p>Price: UGX " . number_format($row['price']) . "</p>";
                        echo "<p>Subtotal: UGX " . number_format($subtotal) . "</p>";
                        echo "<form method='POST' class='quantity-control'>";
                        echo "<input type='hidden' name='product_id' value='" . $row['id'] . "'>";
                        echo "<input type='text' name='quantity' value='" . $row['quantity'] . "' placeholder='Enter quantity' pattern='[0-9]+' aria-label='Quantity' required>";
                        echo "<button type='submit' name='update_quantity' class='update-btn'>Update</button>";
                        echo "</form>";
                        echo "<div class='action-buttons'>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='product_id' value='" . $row['id'] . "'>";
                        echo "<button type='submit' name='remove_from_cart' class='remove-btn' aria-label='Remove item'>Remove</button>";
                        echo "<a href='payment.php?cart_id=" . $row['cart_id'] . "' class='checkout-btn' aria-label='Checkout this item'>Checkout</a>";
                        echo "</form>";
                        echo "</div>";
                        echo "</div>";
                    }
                    echo "</div>";
                    echo "<div class='checkout-container'>";
                    echo "<div class='cart-total'>Total: UGX " . number_format($total) . "</div>";
                    echo "<a href='payment.php' class='main-checkout-btn' aria-label='Proceed to checkout all items'>Proceed to Checkout</a>";
                    echo "</div>";
                } else {
                    echo "<div class='empty-cart'>";
                    echo "<p>Your cart is empty. <a href='index.php'>Continue shopping</a></p>";
                    echo "</div>";
                }
                $stmt->close();
            } else {
                if (!empty($_SESSION['guest_cart'])) {
                    $product_ids = array_keys($_SESSION['guest_cart']);
                    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
                    $stmt = $conn->prepare("SELECT id, name, price, image_path FROM products WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        echo "<div class='product-grid'>";
                        while ($row = $result->fetch_assoc()) {
                            $quantity = $_SESSION['guest_cart'][$row['id']];
                            $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                            $subtotal = $row['price'] * $quantity;
                            $total += $subtotal;
                            echo "<div class='product-card'>";
                            echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'>";
                            echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                            echo "<p>Price: UGX " . number_format($row['price']) . "</p>";
                            echo "<p>Subtotal: UGX " . number_format($subtotal) . "</p>";
                            echo "<form method='POST' class='quantity-control'>";
                            echo "<input type='hidden' name='product_id' value='" . $row['id'] . "'>";
                            echo "<input type='text' name='quantity' value='$quantity' placeholder='Enter quantity' pattern='[0-9]+' aria-label='Quantity' required>";
                            echo "<button type='submit' name='update_quantity' class='update-btn'>Update</button>";
                            echo "</form>";
                            echo "<div class='action-buttons'>";
                            echo "<form method='POST'>";
                            echo "<input type='hidden' name='product_id' value='" . $row['id'] . "'>";
                            echo "<button type='submit' name='remove_from_cart' class='remove-btn' aria-label='Remove item'>Remove</button>";
                            echo "</form>";
                            echo "<a href='login.php?redirect=payment.php' class='checkout-btn' aria-label='Login to checkout'>Login to Checkout</a>";
                            echo "</div>";
                            echo "</div>";
                        }
                        echo "</div>";
                        echo "<div class='checkout-container'>";
                        echo "<div class='cart-total'>Total: UGX " . number_format($total) . "</div>";
                        echo "<a href='login.php?redirect=payment.php' class='main-checkout-btn' aria-label='Login to checkout all items'>Login to Checkout</a>";
                        echo "</div>";
                    } else {
                        echo "<div class='empty-cart'>";
                        echo "<p>Your cart is empty. <a href='index.php'>Continue shopping</a></p>";
                        echo "</div>";
                    }
                    $stmt->close();
                } else {
                    echo "<div class='empty-cart'>";
                    echo "<p>Your cart is empty. <a href='index.php'>Continue shopping</a></p>";
                    echo "</div>";
                }
            }
            ?>
        </div>
    </section>
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
        document.querySelectorAll('.product-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        const cartBtn = document.querySelector('.cart-btn');
        cartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'cart.php';
        });

        // Auto-submit quantity form on blur if valid
        document.querySelectorAll('.quantity-control input[type="text"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (/^[0-9]+$/.test(this.value) && parseInt(this.value) >= 1) {
                    const form = this.closest('form');

                    // ✅ ensure update_quantity gets posted
                    if (!form.querySelector('input[name="update_quantity"]')) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'update_quantity';
                        hidden.value = '1';
                        form.appendChild(hidden);
                    }

                    form.submit();
                }
            });

            // Real-time validation feedback
            input.addEventListener('input', function() {
                if (!/^[0-9]+$/.test(this.value) || parseInt(this.value) < 1) {
                    this.setCustomValidity('Please enter a valid positive number');
                    this.reportValidity();
                } else {
                    this.setCustomValidity('');
                }
            });
        });

        // Prompt to update quantities before checkout
        document.querySelectorAll('.checkout-btn, .main-checkout-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const forms = document.querySelectorAll('.quantity-control');
                for (let form of forms) {
                    const input = form.querySelector('input[name="quantity"]');
                    if (input.value !== input.defaultValue && /^[0-9]+$/.test(input.value) && parseInt(input.value) >= 1) {
                        if (!confirm('You have unsaved quantity changes. Update quantities before proceeding?')) {
                            e.preventDefault();
                            return;
                        }
                        // ✅ also append hidden update_quantity on manual checkout prompt
                        if (!form.querySelector('input[name="update_quantity"]')) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'update_quantity';
                            hidden.value = '1';
                            form.appendChild(hidden);
                        }
                        form.submit();
                    }
                }
            });
        });
    });
</script>

</body>
</html>

<?php
$conn->close();
?>