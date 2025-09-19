<?php
session_start();
include 'db_connect.php';
$category = 'T-Shirts';

// Initialize guest cart and favorites if not set
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}
if (!isset($_SESSION['guest_favorites'])) {
    $_SESSION['guest_favorites'] = [];
}

// Handle adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id', '$product_id', '$quantity') ON DUPLICATE KEY UPDATE quantity = quantity + $quantity";
        $conn->query($sql);
    } else {
        if (!isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id] = $quantity;
        } else {
            $_SESSION['guest_cart'][$product_id] += $quantity;
        }
    }
    
    header("Location: T-Shirts.php");
    exit();
}

// Handle add/remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM favorites WHERE user_id = '$user_id' AND product_id = '$product_id'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $sql = "DELETE FROM favorites WHERE user_id = '$user_id' AND product_id = '$product_id'";
        } else {
            $sql = "INSERT INTO favorites (user_id, product_id) VALUES ('$user_id', '$product_id')";
        }
        $conn->query($sql);
    } else {
        if (in_array($product_id, $_SESSION['guest_favorites'])) {
            $_SESSION['guest_favorites'] = array_diff($_SESSION['guest_favorites'], [$product_id]);
        } else {
            $_SESSION['guest_favorites'][] = $product_id;
        }
        $_SESSION['guest_favorites'] = array_values($_SESSION['guest_favorites']);
    }
    
    header("Location: T-Shirts.php");
    exit();
}

// Get favorites count
$favorites_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as count FROM favorites WHERE user_id = '$user_id'";
    $result = $conn->query($sql);
    $favorites_count = $result->fetch_assoc()['count'];
} else {
    $favorites_count = count($_SESSION['guest_favorites']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugema CampusShop - T-Shirts</title>
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
            padding-bottom: 60px; /* Space for bottom bar on small screens */
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
            background: var(--white);
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
            background: var(--white);
            padding: 0.5rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .bottom-bar-actions {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .bottom-bar-actions a {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            position: relative;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .bottom-bar-actions a:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .category-section {
            padding: 3rem 0;
            background: var(--light-gray);
        }

        .category-section h2 {
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--primary-green);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .product-card {
            background: var(--white);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            min-height: 300px;
            animation: fadeInUp 0.6s ease-out;
        }

        .product-card:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .product-card img {
            width: 70%;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: var(--light-gray);
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

        .product-card .caption {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .product-card button, .product-card .login-link {
            background: var(--accent-yellow);
            color: var(--dark-gray);
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
            background: var(--secondary-green);
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

        .no-results {
            text-align: center;
            font-size: 1rem;
            color: var(--text-gray);
            padding: 1rem;
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

            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-card img {
                height: 150px;
                width: 100%;
            }

            .product-card {
                min-height: 250px;
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

            .product-card img {
                height: 120px;
                width: 100%;
            }

            .product-card .caption {
                font-size: 0.8rem;
            }

            .product-card {
                min-height: 220px;
            }

            .bottom-bar-actions a {
                padding: 6px 10px;
                font-size: 0.7rem;
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
                                $sql = "SELECT SUM(quantity) as count FROM cart WHERE user_id = '$user_id'";
                                $result = $conn->query($sql);
                                echo $result->fetch_assoc()['count'] ?? array_sum($_SESSION['guest_cart']);
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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="textbooks.php">Textbooks</a></li>
                    <li><a href="Branded Jumpers.php">Branded Jumpers</a></li>
                    <li><a href="Pens.php">Pens</a></li>
                    <li><a href="Wall Clocks.php">Wall Clocks</a></li>
                    <li><a href="Note Books.php">Note Books</a></li>
                    <li><a href="T-Shirts.php" class="active">T-Shirts</a></li>
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
                    <a href="index.php">Home</a>
                    <a href="textbooks.php">Textbooks</a>
                    <a href="Branded Jumpers.php">Branded Jumpers</a>
                    <a href="Pens.php">Pens</a>
                    <a href="Wall Clocks.php">Wall Clocks</a>
                    <a href="Note Books.php">Note Books</a>
                    <a href="T-Shirts.php" class="active">T-Shirts</a>
                    <a href="Bottles.php">Bottles</a>
                    <a href="favorites.php">Favorites</a>
                </div>
            </div>
        </div>
    </header>

    <div class="bottom-bar">
        <div class="bottom-bar-actions">
            <?php if (isset($_SESSION['username'])): ?>
                <a href="logout.php">Logout</a>
                <a href="favorites.php">❤️ Favorites <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
                <a href="cart.php">🛒 Cart <span class="cart-count">
                    <?php
                    $user_id = $_SESSION['user_id'] ?? 0;
                    $sql = "SELECT SUM(quantity) as count FROM cart WHERE user_id = '$user_id'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'] ?? array_sum($_SESSION['guest_cart']);
                    ?>
                </span></a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="favorites.php">❤️ Favorites <span class="favorites-count"><?php echo $favorites_count; ?></span></a>
                <a href="cart.php">🛒 Cart <span class="cart-count"><?php echo array_sum($_SESSION['guest_cart']); ?></span></a>
            <?php endif; ?>
        </div>
    </div>

    <section class="category-section">
        <div class="container">
            <h2><?php echo htmlspecialchars($category); ?></h2>
            <div class="product-grid">
                <?php
                $category = $conn->real_escape_string($category);
                $sql = "SELECT * FROM products WHERE category = '$category'";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $product_id = $row['id'];
                        $is_favorited = false;
                        if (isset($_SESSION['user_id'])) {
                            $user_id = $_SESSION['user_id'];
                            $fav_sql = "SELECT * FROM favorites WHERE user_id = '$user_id' AND product_id = '$product_id'";
                            $fav_result = $conn->query($fav_sql);
                            $is_favorited = $fav_result->num_rows > 0;
                        } else {
                            $is_favorited = in_array($product_id, $_SESSION['guest_favorites']);
                        }
                        $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                        echo "<div class='product-card'>";
                        echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'>";
                        echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                        echo "<p class='caption'>" . htmlspecialchars($row['caption'] ?? 'No description available') . "</p>";
                        echo "<p class='price'>Price: UGX " . number_format($row['price']) . "</p>";
                        echo "<form method='POST' action='T-Shirts.php'>";
                        echo "<input type='hidden' name='product_id' value='$product_id'>";
                        echo "<input type='number' name='quantity' class='quantity-input' value='1' min='1'>";
                        echo "<button type='submit' name='add_to_cart'>🛒</button>";
                        echo "</form>";
                        echo "<form method='POST' action='T-Shirts.php'>";
                        echo "<input type='hidden' name='product_id' value='$product_id'>";
                        echo "<button type='submit' name='toggle_favorite' class='favorite-btn " . ($is_favorited ? 'favorited' : '') . "'>❤️</button>";
                        echo "</form>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='no-results'>No products found in this category</div>";
                }
                ?>
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
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="Bottles.php">Student Bottles</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Connect</h3>
                    <ul>
                        <li><a href="#">📧 campusshop@bugemauniv.ac.ug</a></li>
                        <li><a href="#">📞 +256 7550 87665</a></li>
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

            document.querySelectorAll('.product-card').forEach(el => {
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
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>