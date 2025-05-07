<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

// Debug logging
error_log("Adding to cart: product_id=$product_id, quantity=$quantity");

// Validate product exists and stock
$query = "SELECT in_stock, seller_id FROM products WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$product || $product['seller_id'] == $user_id) {
    header("Location: ../product.php?id=$product_id&error=invalid");
    exit;
}

if ($quantity > $product['in_stock'] || $quantity < 1) {
    header("Location: ../product.php?id=$product_id&error=stock");
    exit;
}

// Check if product is already in cart
$query = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_item = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($cart_item) {
    // Update quantity
    $new_quantity = $cart_item['quantity'] + $quantity;
    if ($new_quantity > $product['in_stock']) {
        header("Location: ../product.php?id=$product_id&error=stock");
        exit;
    }
    $query = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $new_quantity, $cart_item['cart_id']);
} else {
    // Insert new cart item
    $query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $quantity);
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: ../product.php?id=$product_id&added=1");
exit;
?>