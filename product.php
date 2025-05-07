<?php
session_start();
include 'config.php';

// Fetch user picture
$user_picture = 'images/default_user.png';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT user_picture FROM users WHERE user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $user_picture = htmlspecialchars($row['user_picture'] ?? 'images/default_user.png');
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch product details, images, and seller
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT p.product_id, p.product_name, p.price, p.in_stock, p.description, p.seller_id, 
                 COALESCE(u.user_name, u.fullname, 'Unknown') as seller_name,
                 COALESCE(GROUP_CONCAT(pi.image_path ORDER BY pi.image_id), 'images/product/default_product_img.png') as image_paths
          FROM products p
          LEFT JOIN product_images pi ON p.product_id = pi.product_id
          LEFT JOIN users u ON p.seller_id = u.user_id
          WHERE p.product_id = ?
          GROUP BY p.product_id, p.product_name, p.price, p.in_stock, p.description, p.seller_id, u.user_name, u.fullname";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    header("Location: index.php");
    exit;
}

// Split image_paths into an array
$image_paths = $product['image_paths'] ? explode(',', $product['image_paths']) : ['images/product/default_product_img.png'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - E-Shop</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div id="cart-notification" class="notification" style="display: none;" role="alert">
        Product added to cart!
    </div>
    <header>
        <div class="main-banner">
            <a href="index.php" class="logo">E-Shop System</a>
            <div class="search-area">
                <form action="index.php" method="GET">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            <div class="user-function">
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <a href="user.php"><img src="<?php echo $user_picture; ?>" alt="Profile" class="user-pic"></a>
                    <a href="cart.php">Cart</a>
                    <a href="php/logout.php">Logout</a>
                <?php } else { ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php } ?>
            </div>
        </div>
    </header>
    <main>
        <section class="product-display">
            <h1>Product Details</h1>
            <div class="navigation-actions">
                <a href="index.php" class="action-btn back-to-products">Back to Products</a>
            </div>
            <div class="product-details">
                <div class="product-image">
                    <div class="image-carousel">
                        <?php if ($product['in_stock'] < 5 && $product['in_stock'] > 0) { ?>
                            <span class="stock-badge">Low Stock</span>
                        <?php } elseif ($product['in_stock'] == 0) { ?>
                            <span class="stock-badge">Out of Stock</span>
                        <?php } ?>
                        <button class="carousel-prev" data-product-id="<?php echo $product_id; ?>">&lt;</button>
                        <img src="<?php echo htmlspecialchars($image_paths[0]); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="carousel-image" data-product-id="<?php echo $product_id; ?>">
                        <button class="carousel-next" data-product-id="<?php echo $product_id; ?>">&gt;</button>
                    </div>
                </div>
                <div class="product-info-box">
                    <h2 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h2>
                    <p class="product-seller">Sold by: <?php echo htmlspecialchars($product['seller_name']); ?></p>
                    <p class="product-price">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                    <p class="product-stock">In Stock: <?php echo htmlspecialchars($product['in_stock']); ?></p>
                    <p class="product-description"><?php echo htmlspecialchars($product['description'] ?? 'No description available.'); ?></p>
                    <div class="product-actions">
                        <?php if (isset($_SESSION['user_id'])) {
                            if ($_SESSION['user_id'] == $product['seller_id']) { ?>
                                <button type="button" class="action-btn" disabled>You cannot add your own product</button>
                            <?php } else { ?>
                                <form action="php/add_to_cart.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                    <div class="quantity-control">
                                        <button type="button" class="quantity-btn decrement" aria-label="Decrease quantity" onclick="decrementQuantity()">−</button>
                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['in_stock']; ?>" pattern="[0-9]*" required onchange="validateQuantity()" oninput="validateQuantity()" onblur="validateQuantity()">
                                        <button type="button" class="quantity-btn increment" aria-label="Increase quantity" onclick="incrementQuantity()">+</button>
                                    </div>
                                    <div class="error-message" id="quantity-error" style="display: none;" role="alert">Cannot exceed stock quantity!</div>
                                    <button type="submit" class="action-btn add-to-cart">Add to Cart</button>
                                </form>
                            <?php }
                        } else { ?>
                            <a href="login.php" class="action-btn login-to-cart">Login to Add to Cart</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
        // Carousel
        const images = <?php echo json_encode($image_paths); ?>;
        let currentIndex = 0;
        const imgElement = document.querySelector('.carousel-image[data-product-id="<?php echo $product_id; ?>"]');
        const prevButton = document.querySelector('.carousel-prev[data-product-id="<?php echo $product_id; ?>"]');
        const nextButton = document.querySelector('.carousel-next[data-product-id="<?php echo $product_id; ?>"]');

        function updateImage() {
            imgElement.src = images[currentIndex];
        }

        prevButton.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            updateImage();
        });

        nextButton.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % images.length;
            updateImage();
        });

        // Quantity Controls
        function updateButtons() {
            const input = document.querySelector('input[name="quantity"]');
            const value = parseInt(input.value) || 1;
            const max = parseInt(input.max);
            document.querySelector('.quantity-btn.decrement').disabled = value <= 1;
            document.querySelector('.quantity-btn.increment').disabled = value >= max;
        }

        function incrementQuantity() {
            const input = document.querySelector('input[name="quantity"]');
            const max = parseInt(input.max);
            let value = parseInt(input.value) || 1;
            if (value < max) {
                input.value = value + 1;
                hideError();
            } else {
                input.value = max;
                showError();
            }
            updateButtons();
        }

        function decrementQuantity() {
            const input = document.querySelector('input[name="quantity"]');
            let value = parseInt(input.value) || 1;
            if (value > 1) {
                input.value = value - 1;
                hideError();
            }
            updateButtons();
        }

        function validateQuantity() {
            const input = document.querySelector('input[name="quantity"]');
            const max = parseInt(input.max);
            let value = parseInt(input.value);
            if (isNaN(value) || value < 1) {
                input.value = 1;
                hideError();
            } else if (value > max) {
                input.value = max;
                showError();
            } else {
                input.value = value;
                hideError();
            }
            updateButtons();
        }

        function showError() {
            document.getElementById('quantity-error').style.display = 'block';
        }

        function hideError() {
            document.getElementById('quantity-error').style.display = 'none';
        }

        // Initialize
        validateQuantity();
        updateButtons();

        // Notification
        if (<?php echo isset($_GET['added']) && $_GET['added'] == '1' ? 'true' : 'false'; ?>) {
            const notification = document.getElementById('cart-notification');
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Debug form submission
        document.querySelector('form').addEventListener('submit', (e) => {
            validateQuantity();
            console.log('Quantity:', document.querySelector('input[name="quantity"]').value);
        });
    </script>
    <?php mysqli_close($conn); ?>
</body>
</html>