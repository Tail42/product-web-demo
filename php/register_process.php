<?php
session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['register_error'] = '無效的請求方法。';
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
    $_SESSION['register_error'] = '所有欄位均為必填。';
    header("Location: ../register.php");
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['register_error'] = '密碼不匹配。';
    header("Location: ../register.php");
    exit;
}

if (!filter_var($account, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = '電子郵件格式無效。';
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
    $_SESSION['register_error'] = '帳戶已存在。';
    header("Location: ../register.php");
    exit;
}
mysqli_stmt_close($stmt);

// 檢查用戶名是否已存在
$query = "SELECT COUNT(*) as count FROM users WHERE user_name = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $user_name);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
if ($row['count'] > 0) {
    $_SESSION['register_error'] = '用戶名已存在，請選擇其他用戶名。';
    header("Location: ../register.php");
    exit;
}
mysqli_stmt_close($stmt);

// 處理圖片上傳
$upload_dir = __DIR__ . '/../images/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$user_picture = 'images/default_user.png';

if (isset($_FILES['user-photo']) && $_FILES['user-photo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['user-photo']['tmp_name'];
    $file_name = basename($_FILES['user-photo']['name']);
    $file_size = $_FILES['user-photo']['size'];
    $file_type = mime_content_type($file_tmp);

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['register_error'] = '檔案類型無效，僅允許 JPG、PNG 和 GIF。';
        header("Location: ../register.php");
        exit;
    }

    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file_size > $max_size) {
        $_SESSION['register_error'] = '檔案大小超過 5MB 限制。';
        header("Location: ../register.php");
        exit;
    }

    $new_file_name = 'user_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
    $destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp, $destination)) {
        $user_picture = 'images/' . $new_file_name;
    } else {
        $_SESSION['register_error'] = '檔案上傳失敗：' . error_get_last()['message'];
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
    $_SESSION['register_success'] = '註冊成功！請登入。';
    header("Location: ../login.php");
} else {
    $_SESSION['register_error'] = '註冊失敗：' . mysqli_error($conn);
    header("Location: ../register.php");
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;
?>