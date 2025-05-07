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
                        <?php if (isset($_SESSION['user_id'])) { ?>
                            <form action="php/add_to_cart.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                <button type="submit" class="action-btn add-to-cart">Add to Cart</button>
                            </form>
                        <?php } else { ?>
                            <a href="login.php" class="action-btn login-to-cart">Login to Add to Cart</a>
                        <?php } ?>
                        <a href="index.php" class="action-btn back-to-products">Back to Products</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
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
    </script>
    <?php mysqli_close($conn); ?>
</body>
</html>