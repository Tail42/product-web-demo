<?php
session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = $_POST['account'];
    $password = $_POST['password'];

    if (empty($account) || empty($password)) {
        $_SESSION['login_error'] = 'Account and password are required.';
        header("Location: ../login.php");
        exit;
    }

    $query = "SELECT * FROM users WHERE account = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $account);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        if (password_verify($password, $user['hash_password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['user_name'];
            $_SESSION['user_photo'] = $user['user_picture'] ?: 'images/default_user.png';
            header("Location: ../index.php");
        } else {
            $_SESSION['login_error'] = 'Incorrect password.';
            header("Location: ../login.php");
        }
    } else {
        $_SESSION['login_error'] = 'Account is not registered.';
        header("Location: ../login.php");
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>