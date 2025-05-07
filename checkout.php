<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;

// Fetch user picture and profile info
$user_picture = 'images/default_user.png';
$user_info = ['fullname' => '', 'address' => '', 'phone' => ''];
$query = "SELECT user_picture, fullname, address, phone FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $user_picture = htmlspecialchars($row['user_picture'] ?? 'images/default_user.png');
        $user_info['fullname'] = htmlspecialchars($row['fullname'] ?? '');
        $user_info['address'] = htmlspecialchars($row['address'] ?? '');
        $user_info['phone'] = htmlspecialchars($row['phone'] ?? '');
    }
    mysqli_stmt_close($stmt);
}

// Fetch cart items for the specific seller
$query = "SELECT c.cart_id, c.product_id, c.quantity, p.product_name, p.price,
                 COALESCE(pi.image_path, 'images/product/default_product_img.png') as product_image,
                 p.seller_id, COALESCE(u.user_name, u.fullname, 'Unknown') as seller_name
          FROM cart c
          JOIN products p ON c.product_id = p.product_id
          LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.image_id = (
              SELECT MIN(image_id) FROM product_images WHERE product_id = p.product_id
          )
          JOIN users u ON p.seller_id = u.user_id
          WHERE c.user_id = ? AND p.seller_id = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    error_log("Query preparation failed: " . mysqli_error($conn));
    die("Error: Unable to fetch cart items. Please try again later.");
}
mysqli_stmt_bind_param($stmt, "ii", $user_id, $seller_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Calculate total
$total = array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cart_items));

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
                <a href="user.php"><img src="<?php echo $user_picture; ?>" alt="Profile" class="user-pic"></a>
                <a href="cart.php">Cart</a>
                <a href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>
    <main>
        <section class="product-display">
            <h2>Checkout</h2>
            <?php if (empty($cart_items)) { ?>
                <p class="cart-empty">No items to checkout.</p>
                <a href="cart.php" class="action-btn back-to-cart">Back to Cart</a>
            <?php } else { ?>
                <!-- Order Product Details -->
                <div class="cart-items">
                    <h3>Order Product Details</h3>
                    <?php foreach ($cart_items as $item) { ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                            <div class="cart-item-details">
                                <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p>Price: $<?php echo htmlspecialchars(number_format($item['price'], 2)); ?> x <?php echo htmlspecialchars($item['quantity']); ?></p>
                                <p>Subtotal: $<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Order Total Amount -->
                <div class="order-total">
                    <h3>Order Total</h3>
                    <p><strong>Total: $<?php echo htmlspecialchars(number_format($total, 2)); ?></strong></p>
                </div>

                <!-- Order Information Form -->
                <div class="order-form">
                    <h3>Order Information</h3>
                    <form action="php/process_checkout.php" method="POST" onsubmit="return validateForm()">
                        <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                        <input type="hidden" name="buyer_id" value="<?php echo $user_id; ?>">
                        <div class="form-group radio-group">
                            <label>Payment Method</label>
                            <div class="radio-options">
                                <label><input type="radio" name="pay_method" value="credit_card" required> Credit Card</label>
                                <label><input type="radio" name="pay_method" value="cash_on_delivery"> Cash on Delivery</label>
                                <label><input type="radio" name="pay_method" value="e_wallet"> E-Wallet</label>
                                <label><input type="radio" name="pay_method" value="bank_transfer"> Bank Transfer</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="buyer_fullname">Full Name</label>
                            <input type="text" name="buyer_fullname" id="buyer_fullname" value="<?php echo $user_info['fullname']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="buyer_address">Delivery Address</label>
                            <textarea name="buyer_address" id="buyer_address" required><?php echo $user_info['address']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="buyer_phone">Phone Number</label>
                            <input type="tel" name="buyer_phone" id="buyer_phone" value="<?php echo $user_info['phone']; ?>" required pattern="[0-9]{10,15}">
                        </div>
                        <div class="error-message" id="form-error" style="display: none;" role="alert">
                            Please fill out all fields correctly.
                        </div>
                        <button type="submit" class="action-btn place-order-btn">Place Order</button>
                    </form>
                </div>
                <a href="cart.php" class="action-btn back-to-cart">Back to Cart</a>
            <?php } ?>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
        function validateForm() {
            const payment = document.querySelector('input[name="pay_method"]:checked');
            const fullName = document.querySelector('input[name="buyer_fullname"]').value.trim();
            const address = document.querySelector('textarea[name="buyer_address"]').value.trim();
            const phone = document.querySelector('input[name="buyer_phone"]').value.trim();
            const isValid = payment && fullName && address && phone && /^[0-9]{10,15}$/.test(phone);
            document.querySelector('.place-order-btn').disabled = !isValid;
            document.getElementById('form-error').style.display = isValid ? 'none' : 'block';
            return isValid;
        }

        // Add event listeners for real-time validation
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('input', validateForm);
            input.addEventListener('change', validateForm);
        });

        // Initial validation
        validateForm();
    </script>
</body>
</html>