<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Query user info
$query = "SELECT user_name, account, fullname, address, phone, user_picture FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle form submissions
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

            case 'delete_product':
                $product_id = (int)($_POST['product_id'] ?? 0);
                if ($product_id > 0) {
                    // Verify the product belongs to the user
                    $query = "SELECT COUNT(*) as count FROM products WHERE product_id = ? AND seller_id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ii", $product_id, $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    if ($row['count'] > 0) {
                        // Delete associated images
                        $query = "SELECT image_path FROM product_images WHERE product_id = ?";
                        $stmt = mysqli_prepare($conn, $query);
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "i", $product_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            while ($image = mysqli_fetch_assoc($result)) {
                                if ($image['image_path'] !== 'images/product/default_product_img.png') {
                                    @unlink(__DIR__ . '/' . $image['image_path']);
                                }
                            }
                            mysqli_stmt_close($stmt);
                        }

                        // Delete the product (ON DELETE CASCADE handles product_images)
                        $query = "DELETE FROM products WHERE product_id = ? AND seller_id = ?";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "ii", $product_id, $user_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = 'Product deleted successfully!';
                        } else {
                            $error_message = 'Product deletion failed: ' . mysqli_error($conn);
                        }
                    } else {
                        $error_message = 'Product not found or you do not have permission to delete it.';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = 'Invalid product ID.';
                }
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

// Query user's products and images (only for my-product section)
$products = [];
if ($active_section === 'my-product') {
    $query = "SELECT product_id, product_name, category, description, in_stock, sold, price FROM products WHERE seller_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $product = $row;
        // Fetch images for this product
        $product['images'] = ['images/product/default_product_img.png']; // Default image
        $query_images = "SELECT image_path FROM product_images WHERE product_id = ?";
        $stmt_images = mysqli_prepare($conn, $query_images);
        if ($stmt_images) {
            mysqli_stmt_bind_param($stmt_images, "i", $row['product_id']);
            mysqli_stmt_execute($stmt_images);
            $result_images = mysqli_stmt_get_result($stmt_images);
            $images = [];
            while ($image_row = mysqli_fetch_assoc($result_images)) {
                $images[] = $image_row['image_path'];
            }
            if (!empty($images)) {
                $product['images'] = $images; // Override default if images exist
            }
            mysqli_stmt_close($stmt_images);
        }
        $products[] = $product;
    }
    mysqli_stmt_close($stmt);
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
                    <a href="add_product.php" class="create-btn">Add Product</a>
                    <?php if (empty($products)): ?>
                        <p>You haven't uploaded any products yet.</p>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <div class="photo-display">
                                        <?php
                                        // Stock badge
                                        if ($product['in_stock'] < 5 && $product['in_stock'] > 0) {
                                            echo '<span class="stock-badge">Low Stock</span>';
                                        } elseif ($product['in_stock'] == 0) {
                                            echo '<span class="stock-badge">Out of Stock</span>';
                                        }
                                        ?>
                                        <button class="carousel-prev" data-product-id="<?php echo $product['product_id']; ?>">&lt;</button>
                                        <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" alt="Product Image" class="carousel-image" data-product-id="<?php echo $product['product_id']; ?>">
                                        <button class="carousel-next" data-product-id="<?php echo $product['product_id']; ?>">&gt;</button>
                                        <script>
                                            const images<?php echo $product['product_id']; ?> = <?php echo json_encode($product['images']); ?>;
                                            let currentIndex<?php echo $product['product_id']; ?> = 0;
                                            const imgElement<?php echo $product['product_id']; ?> = document.querySelector('.carousel-image[data-product-id="<?php echo $product['product_id']; ?>"]');
                                            const prevButton<?php echo $product['product_id']; ?> = document.querySelector('.carousel-prev[data-product-id="<?php echo $product['product_id']; ?>"]');
                                            const nextButton<?php echo $product['product_id']; ?> = document.querySelector('.carousel-next[data-product-id="<?php echo $product['product_id']; ?>"]');

                                            function updateImage<?php echo $product['product_id']; ?>() {
                                                imgElement<?php echo $product['product_id']; ?>.src = images<?php echo $product['product_id']; ?>[currentIndex<?php echo $product['product_id']; ?>];
                                            }

                                            prevButton<?php echo $product['product_id']; ?>.addEventListener('click', () => {
                                                currentIndex<?php echo $product['product_id']; ?> = (currentIndex<?php echo $product['product_id']; ?> - 1 + images<?php echo $product['product_id']; ?>.length) % images<?php echo $product['product_id']; ?>.length;
                                                updateImage<?php echo $product['product_id']; ?>();
                                            });

                                            nextButton<?php echo $product['product_id']; ?>.addEventListener('click', () => {
                                                currentIndex<?php echo $product['product_id']; ?> = (currentIndex<?php echo $product['product_id']; ?> + 1) % images<?php echo $product['product_id']; ?>.length;
                                                updateImage<?php echo $product['product_id']; ?>();
                                            });
                                        </script>
                                    </div>
                                    <div class="product-info">
                                        <h3><?php echo htmlspecialchars($product['product_name'] ?? 'No Name'); ?></h3>
                                        <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></p>
                                        <p class="stock"><strong>In Stock:</strong> <?php echo htmlspecialchars($product['in_stock'] ?? 0); ?></p>
                                        <p><strong>Sold:</strong> <?php echo htmlspecialchars($product['sold'] ?? 0); ?></p>
                                        <p class="price"><strong>Price:</strong> $<?php echo htmlspecialchars(number_format($product['price'], 2) ?? '0.00'); ?></p>
                                        <div class="product-actions">
                                            <a href="edit_product.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>" class="edit-btn">Edit Product</a>
                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                                <button type="submit" class="delete-btn">Delete Product</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        // Show/hide password functionality
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