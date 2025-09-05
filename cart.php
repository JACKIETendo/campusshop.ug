<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Add to cart
if (isset($_POST['add_to_cart'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $sql = "INSERT INTO cart (user_id, product_id) VALUES ('$user_id', '$product_id')";
    $conn->query($sql);
}

// Remove from cart
if (isset($_POST['remove_from_cart'])) {
    $cart_id = $conn->real_escape_string($_POST['cart_id']);
    $sql = "DELETE FROM cart WHERE id = '$cart_id' AND user_id = '{$_SESSION['user_id']}'";
    $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Bugema CampusShop.ug</title>
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
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
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
            max-width: 1000px;
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

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: white;
            text-align: center;
            margin: 2rem 0;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .action-buttons button, .action-buttons a {
            width: 100%;
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
            }

            .product-card img {
                max-height: 120px;
            }
        }

        @media (max-width: 480px) {
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
                    <a href="index.php" class="header-btn">Home</a>
                    <a href="logout.php" class="header-btn">Logout</a>
                </div>
            </div>
        </div>
    </header>
    <div class="container">
        <h2>Your Cart</h2>
        <?php
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT c.id, p.name, p.price, p.image_path FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = '$user_id'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            echo "<div class='product-grid'>";
            while ($row = $result->fetch_assoc()) {
                $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                echo "<div class='product-card'>";
                echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'>";
                echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                echo "<p>Price: UGX " . number_format($row['price']) . "</p>";
                echo "<div class='action-buttons'>";
                echo "<form method='POST'>";
                echo "<input type='hidden' name='cart_id' value='" . $row['id'] . "'>";
                echo "<button type='submit' name='remove_from_cart' class='remove-btn'>Remove</button>";
                echo "</form>";
                echo "<a href='payment.php?cart_id=" . $row['id'] . "' class='checkout-btn'>Proceed to Checkout</a>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<div class='empty-cart'>";
            echo "<p>Your cart is empty. <a href='index.php'>Continue shopping</a></p>";
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>