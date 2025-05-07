<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if cart table exists
$check_table_query = "SHOW TABLES LIKE 'cart'";
$result = mysqli_query($conn, $check_table_query);
if (mysqli_num_rows($result) == 0) {
    die("Error: The 'cart' table does not exist in the database. Please contact the administrator or create the table.");
}

// Handle remove item
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $cart_id = (int)$_GET['remove'];
    $query = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: cart.php");
    exit;
}

// Handle clear cart
if (isset($_GET['clear']) && $_GET['clear'] === 'true') {
    $query = "DELETE FROM cart WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: cart.php");
    exit;
}

// Fetch cart items with seller info
$query = "SELECT c.cart_id, c.product_id, c.quantity, p.product_name, p.price, 
                 COALESCE(pi.image_path, 'images/product/default_product_img.png') as product_image,
                 p.seller_id, COALESCE(u.user_name, u.fullname, 'Unknown') as seller_name
          FROM cart c
          JOIN products p ON c.product_id = p.product_id
          LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.image_id = (
              SELECT MIN(image_id) FROM product_images WHERE product_id = p.product_id
          )
          JOIN users u ON p.seller_id = u.user_id
          WHERE c.user_id = ?
          ORDER BY u.user_name, p.product_name";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Query preparation failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Group cart items by seller
$seller_groups = [];
foreach ($cart_items as $item) {
    $seller_id = $item['seller_id'];
    if (!isset($seller_groups[$seller_id])) {
        $seller_groups[$seller_id] = [
            'seller_name' => $item['seller_name'],
            'items' => []
        ];
    }
    $seller_groups[$seller_id]['items'][] = $item;
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - E-Shop</title>
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
        <section class="product-display">
            <h2>Your Cart</h2>
            <?php if (empty($seller_groups)) { ?>
                <p class="cart-empty">Your cart is empty.</p>
            <?php } else { ?>
                <?php foreach ($seller_groups as $seller_id => $group) { ?>
                    <div class="seller-group">
                        <h3 class="seller-title">Sold by: <?php echo htmlspecialchars($group['seller_name']); ?></h3>
                        <div class="cart-items">
                            <?php foreach ($group['items'] as $item) { ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <p>Price: $<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></p>
                                        <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                        <a href="?remove=<?php echo $item['cart_id']; ?>" class="remove-link">Remove</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="cart-actions">
                    <a href="checkout.php" class="action-btn checkout-btn">Proceed to Checkout</a>
                    <a href="?clear=true" class="action-btn clear-cart-btn" onclick="return confirm('Are you sure you want to clear your cart?');">Clear Cart</a>
                </div>
            <?php } ?>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
</body>
</html>