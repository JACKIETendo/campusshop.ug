<?php
session_start();
include 'db_connect.php';
$category = 'Branded Jumpers';

// Initialize guest cart if not set
if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1; // Ensure quantity is at least 1
    
    if (isset($_SESSION['user_id'])) {
        // Logged-in user: Add to database cart
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES ('$user_id', '$product_id', '$quantity') ON DUPLICATE KEY UPDATE quantity = quantity + $quantity";
        $conn->query($sql);
    } else {
        // Guest user: Add to session cart
        if (!isset($_SESSION['guest_cart'][$product_id])) {
            $_SESSION['guest_cart'][$product_id] = $quantity;
        } else {
            $_SESSION['guest_cart'][$product_id] += $quantity;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: Branded Jumpers.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugema CampusShop.ug - Branded Jumpers</title>
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

        nav {
            margin-top: 0.5rem;
            background: var(--secondary-green);
            border-radius: 8px;
            padding: 0.5rem;
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
            min-height: 220px;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .product-card img {
            width: 80%;
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

        .product-card p {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .product-card button {
            background: var(--accent-yellow);
            color: var(--dark-gray);
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .product-card button:hover {
            background: var(--secondary-green);
            color: var(--white);
        }

        .product-card a {
            color: var(--primary-green);
            text-decoration: none;
        }

        .product-card a:hover {
            text-decoration: underline;
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

        .no-results {
            text-align: center;
            font-size: 1rem;
            color: var(--text-gray);
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }

            .header-top {
                flex-direction: row;
                gap: 0.5rem;
            }

            .search-bar {
                max-width: 200px;
                margin: 0 0.5rem;
            }

            .search-results {
                max-width: 200px;
                left: 0;
                right: 0;
            }

            .nav-links {
                gap: 0.5rem;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-card img {
                height: 80px;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-bar {
                max-width: none;
                margin: 0.5rem 0;
                width: 100%;
            }

            .search-results {
                max-width: none;
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .username {
                font-size: 0.9rem;
                padding: 8px 15px;
            }

            .product-card img {
                height: 60px;
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
                    <div class="logo-icon">🎓</div>
                    <span>Bugema CampusShop.ug</span>
                </div>
                
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search for textbooks, Branded Jumpers, Pens...">
                    <button class="search-btn">🔍</button>
                    <div class="search-results"></div>
                </div>
                
                <div class="header-actions">
                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="username">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="logout.php" class="header-btn">Logout</a>
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
                    <li><a href="Branded Jumpers.php" class="active">Branded Jumpers</a></li>
                    <li><a href="Pens.php">Pens</a></li>
                    <li><a href="Wall Clocks.php">Wall Clocks</a></li>
                    <li><a href="Note Books.php">Note Books</a></li>
                    <li><a href="T-Shirts.php">T-Shirts</a></li>
                    <li><a href="Bottles.php">Bottles</a></li>
                </ul>
            </nav>
        </div>
    </header>

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
                        $image_path = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+A8AAQAB3gB4cAAAAABJRU5ErkJggg==';
                        echo "<div class='product-card'>";
                        echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "'>";
                        echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                        echo "<p class='caption'>" . htmlspecialchars($row['caption'] ?? 'No description available') . "</p>";
                        echo "<p>Price: UGX " . number_format($row['price']) . "</p>";
                        echo "<form method='POST' action='Branded Jumpers.php'>";
                        echo "<input type='hidden' name='product_id' value='" . $row['id'] . "'>";
                        echo "<input type='number' name='quantity' class='quantity-input' value='1' min='1'>";
                        echo "<button type='submit' name='add_to_cart'>Add to Cart</button>";
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
                    <h3>Bugema CampusShop.ug</h3>
                    <p>Your official Bugema University online store, serving students with quality products and exceptional service.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="Bottles.php">Student Bottles</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Connect</h3>
                    <ul>
                        <li><a href="#">📧 shop@bugema.ac.ug</a></li>
                        <li><a href="#">📞 +256 414 540 822</a></li>
                        <li><a href="#">📍 Bugema University</a></li>
                        <li><a href="#">🕒 Mon-Fri 8AM-6PM</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Bugema CampusShop.ug - Bugema University. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements on scroll
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

            // Autocomplete and search functionality
            const searchInput = document.querySelector('.search-input');
            const searchResults = document.querySelector('.search-results');
            let selectedIndex = -1;

            function fetchSuggestions(query) {
                if (query.length >= 2) {
                    fetch(`search.php?query=${encodeURIComponent(query)}&type=autocomplete`)
                        .then(response => response.text())
                        .then(data => {
                            searchResults.innerHTML = data;
                            searchResults.classList.add('active');
                            selectedIndex = -1;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            searchResults.innerHTML = '<div class="no-results">Error fetching suggestions</div>';
                            searchResults.classList.add('active');
                        });
                } else {
                    searchResults.innerHTML = '';
                    searchResults.classList.remove('active');
                }
            }

            function fetchFullResults(query) {
                fetch(`search.php?query=${encodeURIComponent(query)}&type=full`)
                    .then(response => response.text())
                    .then(data => {
                        searchResults.innerHTML = data;
                        searchResults.classList.add('active');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        searchResults.innerHTML = '<div class="no-results">Error fetching results</div>';
                        searchResults.classList.add('active');
                    });
            }

            searchInput.addEventListener('input', function() {
                fetchSuggestions(this.value.trim());
            });

            searchInput.addEventListener('keydown', function(e) {
                const suggestions = searchResults.querySelectorAll('.suggestion');
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
                    searchInput.value = selectedSuggestion;
                    fetchFullResults(selectedSuggestion);
                }
            });

            function updateSelection(suggestions) {
                suggestions.forEach((suggestion, index) => {
                    suggestion.classList.toggle('selected', index === selectedIndex);
                });
                if (selectedIndex >= 0) {
                    searchInput.value = suggestions[selectedIndex].textContent;
                }
            }

            searchResults.addEventListener('click', function(e) {
                const suggestion = e.target.closest('.suggestion');
                if (suggestion) {
                    searchInput.value = suggestion.textContent;
                    fetchFullResults(suggestion.textContent);
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('active');
                }
            });

            // Cart interaction
            const cartBtn = document.querySelector('.cart-btn');
            cartBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'cart.php';
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>