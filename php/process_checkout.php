<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// 這裡應添加訂單記錄邏輯，例如插入 orders 表
$query = "DELETE FROM cart WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$_SESSION['checkout_success'] = 'Order placed successfully!';
header("Location: ../index.php");
exit;

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>