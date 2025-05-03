<?php
session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['register_error'] = 'Invalid request method.';
    header("Location: ../register.php");
    exit;
}


$user_name = trim($_POST['name'] ?? '');
$account = trim($_POST['account'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm-password'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$required_fields = [$user_name, $account, $password, $fullname, $address, $phone];
if (in_array('', $required_fields)) {
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

// 檢查帳號是否已存在
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

// 處理圖片上傳
$upload_dir = __DIR__ . '/../images/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // 創建目錄若不存在
}
$user_picture = 'images/default_user.png';

if (isset($_FILES['user-photo']) && $_FILES['user-photo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['user-photo']['tmp_name'];
    $file_name = basename($_FILES['user-photo']['name']);
    $file_size = $_FILES['user-photo']['size'];
    $file_type = mime_content_type($file_tmp);

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['register_error'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        header("Location: ../register.php");
        exit;
    }

    $max_size = 10 * 1024 * 1024; // 7MB
    if ($file_size > $max_size) {
        $_SESSION['register_error'] = 'File size exceeds 5MB limit.';
        header("Location: ../register.php");
        exit;
    }

    $new_file_name = 'user_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
    $destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp, $destination)) {
        $user_picture = $destination;
    } else {
        $_SESSION['register_error'] = 'Failed to upload file. Error: ' . error_get_last()['message'];
        header("Location: ../register.php");
        exit;
    }
}

// 加密密碼
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 插入資料庫
$query = "INSERT INTO users (user_name, account, hash_password, user_picture, fullname, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?)";
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
exit;
?>