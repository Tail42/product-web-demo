<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['product_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = (int)$_POST['product_id'];

$query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
mysqli_stmt_execute($stmt);

header("Location: ../product.php?id=" . $product_id);
exit;

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>