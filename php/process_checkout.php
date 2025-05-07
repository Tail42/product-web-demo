<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$seller_id = isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0;
$pay_method = $_POST['pay_method'] ?? '';
$buyer_fullname = $_POST['buyer_fullname'] ?? '';
$buyer_address = $_POST['buyer_address'] ?? '';
$buyer_phone = $_POST['buyer_phone'] ?? '';

// Validate input
$valid_payment_methods = ['credit_card', 'cash_on_delivery', 'e_wallet', 'bank_transfer'];
if (!in_array($pay_method, $valid_payment_methods) || empty($buyer_fullname) || empty($buyer_address) || !preg_match('/^[0-9]{10,15}$/', $buyer_phone)) {
    error_log("Invalid checkout data: pay_method=$pay_method, buyer_fullname=$buyer_fullname, buyer_address=$buyer_address, buyer_phone=$buyer_phone");
    header("Location: ../checkout.php?seller_id=$seller_id&error=invalid");
    exit;
}

// Fetch cart items for the seller
$query = "SELECT c.product_id, c.quantity, p.price
          FROM cart c
          JOIN products p ON c.product_id = p.product_id
          WHERE c.user_id = ? AND p.seller_id = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    error_log("Query preparation failed: " . mysqli_error($conn));
    header("Location: ../checkout.php?seller_id=$seller_id&error=server");
    exit;
}
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $seller_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (empty($cart_items)) {
    header("Location: ../cart.php?error=empty");
    exit;
}

// Calculate total
$order_price = array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cart_items));

// Prepare order_products JSON
$order_products = json_encode(array_map(function($item) {
    return [
        'product_id' => $item['product_id'],
        'quantity' => $item['quantity'],
        'price' => $item['price']
    ];
}, $cart_items));

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Insert order
    $query = "INSERT INTO orders (buyer_id, seller_id, order_price, pay_method, buyer_fullname, buyer_address, buyer_phone, order_products, status, checkout_at, completed_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, NULL)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "iidsssss", $buyer_id, $seller_id, $order_price, $pay_method, $buyer_fullname, $buyer_address, $buyer_phone, $order_products);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Order insertion failed: " . mysqli_stmt_error($stmt));
    }
    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Clear cart for this seller
    $query = "DELETE FROM cart WHERE user_id = ? AND product_id IN (SELECT product_id FROM products WHERE seller_id = ?)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $seller_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Cart deletion failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    // Commit transaction
    mysqli_commit($conn);
    error_log("Order created: order_id=$order_id, buyer_id=$buyer_id, seller_id=$seller_id, order_price=$order_price");
    header("Location: ../cart.php?success=order_placed");
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    error_log("Checkout error: " . $e->getMessage());
    header("Location: ../checkout.php?seller_id=$seller_id&error=server");
}
mysqli_close($conn);
exit;
?>