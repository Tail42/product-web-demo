<?php
session_start();
include 'config.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT * FROM products WHERE product_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
mysqli_close($conn);

if (!$product) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - E-Shop</title>
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
                    <a href="user.php"><img src="<?php echo htmlspecialchars($_SESSION['user_photo'] ?? 'images/default_user.png'); ?>" alt="Profile" class="user-pic"></a>
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
        <section class="product-display" style="padding: 50px; text-align: center;">
            <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="max-width: 400px; margin: 20px auto;">
            <p><strong>Price:</strong> $<?php echo htmlspecialchars($product['price']); ?></p>
            <p><strong>Stock:</strong> <?php echo htmlspecialchars($product['in_stock']); ?></p>
            <?php if (isset($_SESSION['user_id'])) { ?>
                <form action="php/add_to_cart.php" method="POST">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <button type="submit" class="category-btn">Add to Cart</button>
                </form>
            <?php } else { ?>
                <p><a href="login.php" class="category-btn">Login to Add to Cart</a></p>
            <?php } ?>
            <p><a href="index.php" class="category-btn">Back to Products</a></p>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>