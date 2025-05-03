<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT c.*, p.product_name, p.product_image, p.price FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total = array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cart_items));
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - E-Shop</title>
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
                <a href="user.php"><img src="<?php echo htmlspecialchars($_SESSION['user_photo'] ?? 'images/default_user.png'); ?>" alt="Profile" class="user-pic"></a>
                <a href="cart.php">Cart</a>
                <a href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>
    <main>
        <section class="product-display" style="padding: 50px;">
            <h2>Checkout</h2>
            <?php if (empty($cart_items)) { ?>
                <p>Your cart is empty.</p>
            <?php } else { ?>
                <div style="margin-bottom: 20px;">
                    <?php foreach ($cart_items as $item) { ?>
                        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 100px; height: 100px; object-fit: cover;">
                            <div>
                                <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                <p>Price: $<?php echo htmlspecialchars($item['price']); ?> x <?php echo htmlspecialchars($item['quantity']); ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <p><strong>Total: $<?php echo number_format($total, 2); ?></strong></p>
                <form action="php/process_checkout.php" method="POST">
                    <button type="submit" class="category-btn" style="display: inline-block;">Confirm Order</button>
                </form>
            <?php } ?>
            <p><a href="cart.php" class="category-btn">Back to Cart</a></p>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>