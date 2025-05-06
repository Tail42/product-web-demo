<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';

// Query user info for header
$query = "SELECT user_name, account, fullname, address, phone, user_picture FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Query product info
$product = null;
if (!empty($product_id)) {
    $query = "SELECT product_id, product_name, category, description, product_image, in_stock, sold, price FROM products WHERE product_id = ? AND seller_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $product_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    header("Location: user.php?section=my-product");
    exit;
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $in_stock = (int)($_POST['in_stock'] ?? 0);
    $sold = (int)($_POST['sold'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $update_at = date('Y-m-d H:i:s');

    // Validate required fields
    if (empty($product_name) || empty($category) || empty($description) || empty($in_stock) || empty($price)) {
        $error_message = 'All fields are required (except sold).';
    } else {
        // Handle image upload
        $product_image = $product['product_image'];
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_type = mime_content_type($file_tmp);
            $file_size = $_FILES['product_image']['size'];

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file_type, $allowed_types)) {
                $error_message = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            } elseif ($file_size > 5 * 1024 * 1024) {
                $error_message = 'File size exceeds 5MB limit.';
            } else {
                $new_file_name = 'product_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $product_image = 'images/' . $new_file_name;
                    // Optionally delete old image if not default
                    if ($product['product_image'] !== 'images/default_product.png') {
                        @unlink(__DIR__ . '/' . $product['product_image']);
                    }
                } else {
                    $error_message = 'Failed to upload file.';
                }
            }
        }

        // Update product
        if (empty($error_message)) {
            $query = "UPDATE products SET product_name = ?, category = ?, description = ?, product_image = ?, in_stock = ?, sold = ?, price = ?, update_at = ? WHERE product_id = ? AND seller_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssiiissi", $product_name, $category, $description, $product_image, $in_stock, $sold, $price, $update_at, $product_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Product updated successfully!';
                // Refresh product data
                $query = "SELECT product_id, product_name, category, description, product_image, in_stock, sold, price FROM products WHERE product_id = ? AND seller_id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "si", $product_id, $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($result);
            } else {
                $error_message = 'Product update failed: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - E-Shop</title>
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
            <h2>Edit Product</h2>
            <?php if ($success_message): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <div class="form-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_product">
                    <div class="form-group">
                        <label for="product_id">Product ID (Read-only)</label>
                        <input type="text" id="product_id" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" placeholder="Product Name" value="<?php echo htmlspecialchars($product['product_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" placeholder="Category" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" placeholder="Description" value="<?php echo htmlspecialchars($product['description'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="product_image">Product Image</label>
                        <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                        <p>Current Image: <img src="<?php echo htmlspecialchars($product['product_image'] ?? 'images/default_product.png'); ?>" alt="Current Image" style="max-width: 100px;"></p>
                    </div>
                    <div class="form-group">
                        <label for="in_stock">In Stock</label>
                        <input type="number" id="in_stock" name="in_stock" placeholder="In Stock" value="<?php echo htmlspecialchars($product['in_stock'] ?? 0); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="sold">Sold</label>
                        <input type="number" id="sold" name="sold" placeholder="Sold" value="<?php echo htmlspecialchars($product['sold'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" placeholder="Price" step="0.01" value="<?php echo htmlspecialchars($product['price'] ?? 0); ?>" required>
                    </div>
                    <button type="submit" class="create-btn">Update Product</button>
                </form>
                <a href="user.php?section=my-product" class="back-btn">Back to My Products</a>
            </div>
        </main>
    </div>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <?php mysqli_close($conn); ?>
</body>
</html>