<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 查詢用戶資訊
$query = "SELECT user_name, account, fullname, address, phone, user_picture FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// 處理表單提交
$success_message = '';
$error_message = '';
$active_section = isset($_GET['section']) ? $_GET['section'] : 'my-account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                $current_password = trim($_POST['current_password'] ?? '');
                $new_password = trim($_POST['new_password'] ?? '');
                $confirm_password = trim($_POST['confirm_password'] ?? '');

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } else {
                    $query = "SELECT hash_password FROM users WHERE user_id = ? LIMIT 1";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    if (password_verify($current_password, $row['hash_password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $query = "UPDATE users SET hash_password = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = 'Password changed successfully!';
                        } else {
                            $error_message = 'Password change failed: ' . mysqli_error($conn);
                        }
                    } else {
                        $error_message = 'Current password is incorrect.';
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'add_product':
                $product_id = trim($_POST['product_id'] ?? '');
                $product_name = trim($_POST['product_name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $seller_id = $user_id;
                $description = trim($_POST['description'] ?? '');
                $in_stock = (int)($_POST['in_stock'] ?? 0);
                $sold = (int)($_POST['sold'] ?? 0);
                $price = (int)($_POST['price'] ?? 0);
                $create_at = date('Y-m-d H:i:s');
                $update_at = $create_at;

                $product_image = 'images/default_product.png';
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_tmp = $_FILES['product_image']['tmp_name'];
                    $file_name = basename($_FILES['product_image']['name']);
                    $new_file_name = 'product_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        $product_image = 'images/' . $new_file_name;
                    }
                }

                $query = "INSERT INTO products (product_id, product_name, category, seller_id, description, product_image, in_stock, sold, price, create_at, update_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ssssssiiiss", $product_id, $product_name, $category, $seller_id, $description, $product_image, $in_stock, $sold, $price, $create_at, $update_at);
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Product added successfully!';
                } else {
                    $error_message = 'Product addition failed: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'logout':
                session_destroy();
                header("Location: index.php");
                exit;
                break;

            case 'delete_account':
                $confirm_account = trim($_POST['confirm_account'] ?? '');
                if ($confirm_account !== $user['account']) {
                    $error_message = 'Account name does not match.';
                } else {
                    $query = "DELETE FROM users WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        session_destroy();
                        header("Location: index.php");
                        exit;
                    } else {
                        $error_message = 'Account deletion failed: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - E-Shop</title>
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
                <a href="user.php"><img src="<?php echo htmlspecialchars($user['user_picture'] ?? 'images/default_user.png'); ?>" alt="Profile" class="user-pic"></a>
                <a href="cart.php">Cart</a>
                <a href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <?php if ($success_message): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <?php if ($active_section === 'my-account'): ?>
                <h2>My Account</h2>
                <div class="user-info">
                    <img src="<?php echo htmlspecialchars($user['user_picture'] ?? 'images/default_user.png'); ?>" alt="Profile Photo">
                    <p><strong>User Name:</strong> <?php echo htmlspecialchars($user['user_name'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['account'] ?? 'N/A'); ?></p>
                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fullname'] ?? 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
                </div>
                <div class="form-section">
                    <a href="edit_user_info.php" class="create-btn">Edit Profile</a>
                </div>

            <?php elseif ($active_section === 'change-password'): ?>
                <h2>Change Password</h2>
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group password-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                            <button type="button" class="toggle-password" data-target="current_password">
                                <img src="images/eye-close.png" alt="Toggle Password" class="password-toggle-img">
                            </button>
                        </div>
                        <div class="form-group password-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" required>
                            <button type="button" class="toggle-password" data-target="new_password">
                                <img src="images/eye-close.png" alt="Toggle Password" class="password-toggle-img">
                            </button>
                        </div>
                        <div class="form-group password-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                            <button type="button" class="toggle-password" data-target="confirm_password">
                                <img src="images/eye-close.png" alt="Toggle Password" class="password-toggle-img">
                            </button>
                        </div>
                        <button type="submit" class="create-btn">Change Password</button>
                    </form>
                </div>

            <?php elseif ($active_section === 'my-product'): ?>
                <h2>My Product</h2>
                <div class="form-section">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        <div class="form-group">
                            <label for="product_id">Product ID</label>
                            <input type="text" id="product_id" name="product_id" placeholder="Product ID" required>
                        </div>
                        <div class="form-group">
                            <label for="product_name">Product Name</label>
                            <input type="text" id="product_name" name="product_name" placeholder="Product Name" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" placeholder="Category" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description" placeholder="Description" required>
                        </div>
                        <div class="form-group">
                            <label for="product_image">Product Image</label>
                            <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="in_stock">In Stock</label>
                            <input type="number" id="in_stock" name="in_stock" placeholder="In Stock" required>
                        </div>
                        <div class="form-group">
                            <label for="sold">Sold</label>
                            <input type="number" id="sold" name="sold" placeholder="Sold" value="0">
                        </div>
                        <div class="form-group">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" placeholder="Price" step="0.01" required>
                        </div>
                        <button type="submit" class="create-btn">Add Product</button>
                    </form>
                </div>

            <?php elseif ($active_section === 'logout'): ?>
                <h2>Log Out</h2>
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="create-btn">Confirm Log Out</button>
                    </form>
                </div>

            <?php elseif ($active_section === 'delete-account'): ?>
                <h2>Delete Account</h2>
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_account">
                        <div class="form-group">
                            <label for="confirm_account">Confirm Account Name</label>
                            <input type="text" id="confirm_account" name="confirm_account" placeholder="Confirm Account Name" required>
                        </div>
                        <button type="submit" class="create-btn">Delete Account</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
        <aside class="sidebar">
            <ul>
                <li><a href="?section=my-account" class="<?php echo $active_section === 'my-account' ? 'active' : ''; ?>">My Account</a></li>
                <li><a href="?section=change-password" class="<?php echo $active_section === 'change-password' ? 'active' : ''; ?>">Change Password</a></li>
                <li><a href="?section=my-product" class="<?php echo $active_section === 'my-product' ? 'active' : ''; ?>">My Product</a></li>
                <li><a href="?section=logout" class="<?php echo $active_section === 'logout' ? 'active' : ''; ?>" onclick="document.querySelector('form[action=\"\"]').submit(); return false;">Log Out</a></li>
                <li><a href="?section=delete-account" class="<?php echo $active_section === 'delete-account' ? 'active' : ''; ?>">Delete Account</a></li>
            </ul>
            <form method="POST" action="" style="display: none;">
                <input type="hidden" name="action" value="logout">
            </form>
        </aside>
    </div>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
        // 顯示/隱藏密碼功能
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetInput = document.querySelector(`input[name="${this.getAttribute('data-target')}"]`);
                const img = this.querySelector('img');
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    img.src = 'images/eye-open.png';
                } else {
                    targetInput.type = 'password';
                    img.src = 'images/eye-close.png';
                }
            });
        });
    </script>
    <?php mysqli_close($conn); ?>
</body>
</html>