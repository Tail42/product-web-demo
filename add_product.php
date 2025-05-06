<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Query user info for header
$query = "SELECT user_name, account, fullname, address, phone, user_picture FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $seller_id = $user_id;
    $description = trim($_POST['description'] ?? '');
    $in_stock = (int)($_POST['in_stock'] ?? 0);
    $sold = (int)($_POST['sold'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $create_at = date('Y-m-d H:i:s');
    $update_at = $create_at;

    // Validate required fields
    if (empty($product_name) || empty($category) || empty($description) || empty($in_stock) || empty($price)) {
        $error_message = 'All fields are required (except sold).';
    } else {
        // Handle multiple image uploads
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
        } else {
            $image_paths[] = "images/product/default_product_img.png";
        }

        // Insert product
        if (empty($error_message)) {
            $query = "INSERT INTO products (product_name, category, seller_id, description, in_stock, sold, price, create_at, update_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssissiiss", $product_name, $category, $seller_id, $description, $in_stock, $sold, $price, $create_at, $update_at);
                if (mysqli_stmt_execute($stmt)) {
                    $product_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    // Insert image paths
                    $query = "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    if ($stmt) {
                        foreach ($image_paths as $image_path) {
                            mysqli_stmt_bind_param($stmt, "is", $product_id, $image_path);
                            if (!mysqli_stmt_execute($stmt)) {
                                $error_message = 'Failed to insert image path: ' . mysqli_error($conn);
                                break;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error_message = 'Failed to prepare image insertion: ' . mysqli_error($conn);
                    }

                    if (empty($error_message)) {
                        $success_message = 'Product added successfully!';
                    }
                } else {
                    $error_message = 'Product addition failed: ' . mysqli_error($conn);
                    mysqli_stmt_close($stmt);
                }
            } else {
                $error_message = 'Failed to prepare product insertion: ' . mysqli_error($conn);
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
    <title>Add Product - E-Shop</title>
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
            <h2>Add Product</h2>
            <?php if ($success_message): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <div class="form-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" placeholder="Product Name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="" disabled selected>Select a category</option>
                            <option value="Electronics & Accessories">Electronics & Accessories</option>
                            <option value="Home Appliances & Living Essentials">Home Appliances & Living Essentials</option>
                            <option value="Clothing & Accessories">Clothing & Accessories</option>
                            <option value="Beauty & Personal Care">Beauty & Personal Care</option>
                            <option value="Food & Beverages">Food & Beverages</option>
                            <option value="Home & Furniture">Home & Furniture</option>
                            <option value="Sports & Outdoor Equipment">Sports & Outdoor Equipment</option>
                            <option value="Automotive & Motorcycle Accessories">Automotive & Motorcycle Accessories</option>
                            <option value="Baby & Maternity Products">Baby & Maternity Products</option>
                            <option value="Books & Office Supplies">Books & Office Supplies</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" placeholder="Description" required>
                    </div>
                    <div class="form-group">
                        <label for="product_images">Product Images (Select multiple)</label>
                        <input type="file" id="product_images" name="product_images[]" accept="image/jpeg,image/png,image/gif" multiple>
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