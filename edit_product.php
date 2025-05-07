<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int)($_GET['product_id']) : 0;

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
if ($product_id > 0) {
    $query = "SELECT product_id, product_name, category, description, in_stock, sold, price FROM products WHERE product_id = ? AND seller_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $product_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    header("Location: user.php?section=my-product");
    exit;
}

// Query existing images
$images = [];
$query = "SELECT image_id, image_path FROM product_images WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $in_stock = (int)($_POST['in_stock'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $update_at = date('Y-m-d H:i:s');
    $delete_images = isset($_POST['delete_images']) ? $_POST['delete_images'] : [];

    // Validate required fields and positive numbers
    if (empty($product_name) || empty($category) || empty($description) || empty($in_stock) || empty($price)) {
        $error_message = 'All fields are required.';
    } elseif ($in_stock <= 0) {
        $error_message = 'In Stock must be greater than 0.';
    } elseif ($price <= 0) {
        $error_message = 'Price must be greater than 0.';
    } else {
        // Handle image deletions
        foreach ($delete_images as $image_id) {
            $query = "SELECT image_path FROM product_images WHERE image_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ii", $image_id, $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $image = mysqli_fetch_assoc($result);
            if ($image && $image['image_path'] !== 'images/product/default_product_img.png') {
                @unlink(__DIR__ . '/' . $image['image_path']);
            }
            mysqli_stmt_close($stmt);

            $query = "DELETE FROM product_images WHERE image_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ii", $image_id, $product_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Handle new image uploads
        $image_paths = [];
        $upload_dir = __DIR__ . "/images/product/$user_id/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            foreach ($_FILES['product_images']['name'] as $key => $name) {
                if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['product_images']['tmp_name'][$key];
                    $file_name = basename($name);
                    $file_type = mime_content_type($file_tmp);
                    $file_size = $_FILES['product_images']['size'][$key];

                    if (!in_array($file_type, $allowed_types)) {
                        $error_message = 'Invalid file type for ' . $file_name . '. Only JPG, PNG, and GIF are allowed.';
                        break;
                    } elseif ($file_size > 5 * 1024 * 1024) {
                        $error_message = 'File size for ' . $file_name . ' exceeds 5MB limit.';
                        break;
                    } else {
                        $new_file_name = 'product_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $destination)) {
                            $image_paths[] = "images/product/$user_id/" . $new_file_name;
                        } else {
                            $error_message = 'Failed to upload file ' . $file_name . '.';
                            break;
                        }
                    }
                }
            }
        }

        // Update product
        if (empty($error_message)) {
            $query = "UPDATE products SET product_name = ?, category = ?, description = ?, in_stock = ?, price = ?, update_at = ? WHERE product_id = ? AND seller_id = ?";
            $update_stmt = mysqli_prepare($conn, $query);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "sssidssi", $product_name, $category, $description, $in_stock, $price, $update_at, $product_id, $user_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    // Insert new images
                    foreach ($image_paths as $image_path) {
                        $query = "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)";
                        $image_stmt = mysqli_prepare($conn, $query);
                        if ($image_stmt) {
                            mysqli_stmt_bind_param($image_stmt, "is", $product_id, $image_path);
                            mysqli_stmt_execute($image_stmt);
                            mysqli_stmt_close($image_stmt);
                        } else {
                            $error_message = 'Failed to insert image path: ' . mysqli_error($conn);
                            break;
                        }
                    }
                    if (empty($error_message)) {
                        $success_message = 'Product updated successfully!';
                        // Refresh product data
                        $query = "SELECT product_id, product_name, category, description, in_stock, sold, price FROM products WHERE product_id = ? AND seller_id = ?";
                        $refresh_stmt = mysqli_prepare($conn, $query);
                        if ($refresh_stmt) {
                            mysqli_stmt_bind_param($refresh_stmt, "ii", $product_id, $user_id);
                            mysqli_stmt_execute($refresh_stmt);
                            $result = mysqli_stmt_get_result($refresh_stmt);
                            $product = mysqli_fetch_assoc($result);
                            mysqli_stmt_close($refresh_stmt);
                        } else {
                            $error_message = 'Failed to refresh product data: ' . mysqli_error($conn);
                        }
                        // Refresh images
                        $images = [];
                        $query = "SELECT image_id, image_path FROM product_images WHERE product_id = ?";
                        $image_refresh_stmt = mysqli_prepare($conn, $query);
                        if ($image_refresh_stmt) {
                            mysqli_stmt_bind_param($image_refresh_stmt, "i", $product_id);
                            mysqli_stmt_execute($image_refresh_stmt);
                            $result = mysqli_stmt_get_result($image_refresh_stmt);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $images[] = $row;
                            }
                            mysqli_stmt_close($image_refresh_stmt);
                        } else {
                            $error_message = 'Failed to refresh images: ' . mysqli_error($conn);
                        }
                    }
                } else {
                    $error_message = 'Product update failed: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $error_message = 'Failed to prepare product update: ' . mysqli_error($conn);
            }
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
                        <select id="category" name="category" required>
                            <option value="" disabled>Select a category</option>
                            <option value="Electronics & Accessories" <?php echo $product['category'] === 'Electronics & Accessories' ? 'selected' : ''; ?>>Electronics & Accessories</option>
                            <option value="Home Appliances & Living Essentials" <?php echo $product['category'] === 'Home Appliances & Living Essentials' ? 'selected' : ''; ?>>Home Appliances & Living Essentials</option>
                            <option value="Clothing & Accessories" <?php echo $product['category'] === 'Clothing & Accessories' ? 'selected' : ''; ?>>Clothing & Accessories</option>
                            <option value="Beauty & Personal Care" <?php echo $product['category'] === 'Beauty & Personal Care' ? 'selected' : ''; ?>>Beauty & Personal Care</option>
                            <option value="Food & Beverages" <?php echo $product['category'] === 'Food & Beverages' ? 'selected' : ''; ?>>Food & Beverages</option>
                            <option value="Home & Furniture" <?php echo $product['category'] === 'Home & Furniture' ? 'selected' : ''; ?>>Home & Furniture</option>
                            <option value="Sports & Outdoor Equipment" <?php echo $product['category'] === 'Sports & Outdoor Equipment' ? 'selected' : ''; ?>>Sports & Outdoor Equipment</option>
                            <option value="Automotive & Motorcycle Accessories" <?php echo $product['category'] === 'Automotive & Motorcycle Accessories' ? 'selected' : ''; ?>>Automotive & Motorcycle Accessories</option>
                            <option value="Baby & Maternity Products" <?php echo $product['category'] === 'Baby & Maternity Products' ? 'selected' : ''; ?>>Baby & Maternity Products</option>
                            <option value="Books & Office Supplies" <?php echo $product['category'] === 'Books & Office Supplies' ? 'selected' : ''; ?>>Books & Office Supplies</option>
                            <option value="Other" <?php echo $product['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Enter product description" rows="5" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Current Images</label>
                        <div class="image-preview">
                            <?php if (empty($images)): ?>
                                <p>No images uploaded.</p>
                            <?php else: ?>
                                <?php foreach ($images as $image): ?>
                                    <div class="image-item">
                                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Product Image" style="max-width: 100px;">
                                        <label>
                                            <input type="checkbox" name="delete_images[]" value="<?php echo $image['image_id']; ?>">
                                            Delete this image
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="product_images">Add New Images (Select multiple)</label>
                        <input type="file" id="product_images" name="product_images[]" accept="image/jpeg,image/png,image/gif" multiple>
                    </div>
                    <div class="form-group">
                        <label for="in_stock">In Stock</label>
                        <input type="number" id="in_stock" name="in_stock" placeholder="In Stock" min="1" value="<?php echo htmlspecialchars($product['in_stock'] ?? 0); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" placeholder="Price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($product['price'] ?? 0); ?>" required>
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