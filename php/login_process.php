<?php
session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['account'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Email and password are required.';
        header("Location: ../login.php");
        exit;
    }

    $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['user_photo'] = $user['user_photo'] ?: 'images/default_user.png';
        header("Location: ../index.php");
    } else {
        $_SESSION['login_error'] = 'Invalid email or password.';
        header("Location: ../login.php");
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>