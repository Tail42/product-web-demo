<?php
session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = trim($_POST['name']);
    $account = trim($_POST['account']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm-password']);
    $fullname = trim($_POST['fullname']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);

    // 基本驗證
    if (empty($user_name) || empty($account) || empty($password) || empty($address) || empty($phone)) {
        $_SESSION['register_error'] = 'All fields are required.';
        header("Location: ../register.php");
        exit;
    }

    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = 'Passwords do not match.';
        header("Location: ../register.php");
        exit;
    }

    if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = 'Invalid email format.';
        header("Location: ../register.php");
        exit;
    }

    // 檢查 account 是否已存在
    $query = "SELECT COUNT(*) as count FROM users WHERE account = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $account);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    if ($row['count'] > 0) {
        $_SESSION['register_error'] = 'Account already exists.';
        header("Location: ../register.php");
        exit;
    }
    mysqli_stmt_close($stmt);

    // 處理照片上傳
    $user_picture = 'images/default_user.png';
    if (isset($_FILES['user-photo']) && $_FILES['user-photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['user-photo']['type'], $allowed_types)) {
            $user_picture = 'images/' . uniqid() . '_' . basename($_FILES['user-photo']['name']);
            move_uploaded_file($_FILES['user-photo']['tmp_name'], $user_picture);
        } else {
            $_SESSION['register_error'] = 'Invalid file type. Only JPG, PNG, GIF allowed.';
            header("Location: ../register.php");
            exit;
        }
    }

    // 加密密碼
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 插入資料庫
    $query = "INSERT INTO users (user_name, account, hash_password, user_picture, fullname,address, phone) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssssss", $user_name, $account, $hashed_password, $user_picture, $fullname, $address, $phone);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['register_success'] = 'Registration successful! Please login.';
        header("Location: ../login.php");
    } else {
        $_SESSION['register_error'] = 'Registration failed: ' . mysqli_error($conn);
        header("Location: ../register.php");
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>