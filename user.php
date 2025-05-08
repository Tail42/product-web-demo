<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Set timezone to UTC+8
date_default_timezone_set('Asia/Taipei');

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
                            session_destroy();
                            echo "<script>alert('Password changed successfully! Please log in again.'); window.location.href='login.php';</script>";
                            exit;
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
                    $query = "SELECT COUNT(*) as count FROM products WHERE product_id = ? AND seller_id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ii", $product_id, $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    if ($row['count'] > 0) {
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

            case 'ship_order':
                $order_id = (int)($_POST['order_id'] ?? 0);
                if ($order_id > 0) {
                    $query = "UPDATE orders SET status = 'shipped' WHERE order_id = ? AND seller_id = ? AND status = 'pending'";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
                    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                        $success_message = 'Order marked as shipped!';
                    } else {
                        $error_message = 'Failed to mark order as shipped.';
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'complete_order':
                $order_id = (int)($_POST['order_id'] ?? 0);
                if ($order_id > 0) {
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    try {
                        // Get order products
                        $query = "SELECT order_products FROM orders WHERE order_id = ? AND buyer_id = ? AND status = 'shipped'";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        if ($row = mysqli_fetch_assoc($result)) {
                            $products = json_decode($row['order_products'], true) ?: [];
                            foreach ($products as $item) {
                                $product_id = (int)($item['product_id'] ?? 0);
                                $quantity = (int)($item['quantity'] ?? 0);
                                if ($product_id > 0 && $quantity > 0) {
                                    // Update in_stock
                                    $query = "UPDATE products SET in_stock = in_stock - ? WHERE product_id = ?";
                                    $stmt = mysqli_prepare($conn, $query);
                                    mysqli_stmt_bind_param($stmt, "ii", $quantity, $product_id);
                                    if (!mysqli_stmt_execute($stmt)) {
                                        throw new Exception('Failed to update product stock: ' . mysqli_error($conn));
                                    }
                                    mysqli_stmt_close($stmt);

                                    // Update sold
                                    $query = "UPDATE products SET sold = sold + ? WHERE product_id = ?";
                                    $stmt = mysqli_prepare($conn, $query);
                                    mysqli_stmt_bind_param($stmt, "ii", $quantity, $product_id);
                                    if (!mysqli_stmt_execute($stmt)) {
                                        throw new Exception('Failed to update product sold: ' . mysqli_error($conn));
                                    }
                                    mysqli_stmt_close($stmt);

                                    // Check if in_stock is 0
                                    $query = "SELECT in_stock FROM products WHERE product_id = ?";
                                    $stmt = mysqli_prepare($conn, $query);
                                    mysqli_stmt_bind_param($stmt, "i", $product_id);
                                    mysqli_stmt_execute($stmt);
                                    $result_stock = mysqli_stmt_get_result($stmt);
                                    $stock_row = mysqli_fetch_assoc($result_stock);
                                    mysqli_stmt_close($stmt);

                                    if ($stock_row['in_stock'] <= 0) {
                                        // Delete product images
                                        $query = "SELECT image_path FROM product_images WHERE product_id = ?";
                                        $stmt = mysqli_prepare($conn, $query);
                                        mysqli_stmt_bind_param($stmt, "i", $product_id);
                                        mysqli_stmt_execute($stmt);
                                        $result_images = mysqli_stmt_get_result($stmt);
                                        while ($image = mysqli_fetch_assoc($result_images)) {
                                            if ($image['image_path'] !== 'images/product/default_product_img.png') {
                                                @unlink(__DIR__ . '/' . $image['image_path']);
                                            }
                                        }
                                        mysqli_stmt_close($stmt);

                                        // Delete product_images records
                                        $query = "DELETE FROM product_images WHERE product_id = ?";
                                        $stmt = mysqli_prepare($conn, $query);
                                        mysqli_stmt_bind_param($stmt, "i", $product_id);
                                        if (!mysqli_stmt_execute($stmt)) {
                                            throw new Exception('Failed to delete product images: ' . mysqli_error($conn));
                                        }
                                        mysqli_stmt_close($stmt);

                                        // Delete product
                                        $query = "DELETE FROM products WHERE product_id = ?";
                                        $stmt = mysqli_prepare($conn, $query);
                                        mysqli_stmt_bind_param($stmt, "i", $product_id);
                                        if (!mysqli_stmt_execute($stmt)) {
                                            throw new Exception('Failed to delete product: ' . mysqli_error($conn));
                                        }
                                        mysqli_stmt_close($stmt);
                                    }
                                }
                            }

                            // Update order status
                            $query = "UPDATE orders SET status = 'completed', completed_at = NOW() WHERE order_id = ? AND buyer_id = ? AND status = 'shipped'";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
                            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) == 0) {
                                throw new Exception('Failed to complete order.');
                            }
                            mysqli_stmt_close($stmt);

                            // Commit transaction
                            mysqli_commit($conn);
                            $success_message = 'Order completed successfully!';
                        } else {
                            throw new Exception('Order not found or not eligible for completion.');
                        }
                    } catch (Exception $e) {
                        // Rollback transaction
                        mysqli_rollback($conn);
                        $error_message = $e->getMessage();
                    }
                } else {
                    $error_message = 'Invalid order ID.';
                }
                break;

            case 'logout':
                session_destroy();
                header("Location: index.php");
                exit;
                break;

            case 'delete_account':
                $confirm_delete = trim($_POST['confirm_delete'] ?? '');
                $expected_input = $user['user_name'] . '@delete';
                if ($confirm_delete !== $expected_input) {
                    $error_message = 'Incorrect input. Please enter your username followed by @delete (e.g., ' . htmlspecialchars($user['user_name']) . '@delete).';
                } else {
                    // Check for incomplete orders
                    $query = "SELECT COUNT(*) as count FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status IN ('pending', 'shipped')";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                    if ($row['count'] > 0) {
                        $error_message = 'Cannot delete account: You have incomplete orders (pending or shipped).';
                    } else {
                        mysqli_begin_transaction($conn);
                        try {
                            // Delete product images and products
                            $query = "SELECT product_id FROM products WHERE seller_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $product_id = $row['product_id'];
                                // Delete images
                                $query = "SELECT image_path FROM product_images WHERE product_id = ?";
                                $stmt_img = mysqli_prepare($conn, $query);
                                mysqli_stmt_bind_param($stmt_img, "i", $product_id);
                                mysqli_stmt_execute($stmt_img);
                                $result_img = mysqli_stmt_get_result($stmt_img);
                                while ($image = mysqli_fetch_assoc($result_img)) {
                                    if ($image['image_path'] !== 'images/product/default_product_img.png') {
                                        @unlink(__DIR__ . '/' . $image['image_path']);
                                    }
                                }
                                mysqli_stmt_close($stmt_img);
                                // Delete product_images
                                $query = "DELETE FROM product_images WHERE product_id = ?";
                                $stmt_img = mysqli_prepare($conn, $query);
                                mysqli_stmt_bind_param($stmt_img, "i", $product_id);
                                mysqli_stmt_execute($stmt_img);
                                mysqli_stmt_close($stmt_img);
                            }
                            mysqli_stmt_close($stmt);
                            // Delete products
                            $query = "DELETE FROM products WHERE seller_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            // Delete orders
                            $query = "DELETE FROM orders WHERE buyer_id = ? OR seller_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            // Delete user
                            $query = "DELETE FROM users WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            // Commit transaction
                            mysqli_commit($conn);
                            session_destroy();
                            echo "<script>alert('Account deleted successfully!'); window.location.href='index.php';</script>";
                            exit;
                        } catch (Exception $e) {
                            mysqli_rollback($conn);
                            $error_message = 'Account deletion failed: ' . $e->getMessage();
                        }
                    }
                }
                break;
        }
    }
}

// Query orders for My Purchase
$my_purchased_orders = [];
$my_sold_items = [];
$history_orders = [];
if ($active_section === 'my-purchase') {
    // My Purchased Orders (buyer, incomplete: pending or shipped)
    $query = "SELECT o.order_id, o.seller_id, o.pay_method, o.checkout_at, o.status, o.order_products,
                     COALESCE(u.user_name, u.fullname, 'Unknown') as seller_name
              FROM orders o
              JOIN users u ON o.seller_id = u.user_id
              WHERE o.buyer_id = ? AND o.status IN ('pending', 'shipped')";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $order = $row;
        $order['items'] = [];
        $products = json_decode($row['order_products'], true) ?: [];
        foreach ($products as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            if ($product_id > 0 && $quantity > 0) {
                $item_query = "SELECT p.product_name,
                               COALESCE(pi.image_path, 'images/product/default_product_img.png') as image_path
                               FROM products p
                               LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.image_id = (
                                   SELECT MIN(image_id) FROM product_images WHERE product_id = p.product_id
                               )
                               WHERE p.product_id = ?";
                $item_stmt = mysqli_prepare($conn, $item_query);
                mysqli_stmt_bind_param($item_stmt, "i", $product_id);
                mysqli_stmt_execute($item_stmt);
                $item_result = mysqli_stmt_get_result($item_stmt);
                if ($item_row = mysqli_fetch_assoc($item_result)) {
                    $order['items'][] = [
                        'product_id' => $product_id,
                        'product_name' => $item_row['product_name'],
                        'image_path' => $item_row['image_path'],
                        'quantity' => $quantity
                    ];
                }
                mysqli_stmt_close($item_stmt);
            }
        }
        $my_purchased_orders[] = $order;
    }
    mysqli_stmt_close($stmt);

    // My Sold Items (seller, incomplete: pending or shipped)
    $query = "SELECT o.order_id, o.buyer_id, o.pay_method, o.checkout_at, o.status, o.order_products,
                     COALESCE(u.user_name, u.fullname, 'Unknown') as buyer_name
              FROM orders o
              JOIN users u ON o.buyer_id = u.user_id
              WHERE o.seller_id = ? AND o.status IN ('pending', 'shipped')";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $order = $row;
        $order['items'] = [];
        $products = json_decode($row['order_products'], true) ?: [];
        foreach ($products as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            if ($product_id > 0 && $quantity > 0) {
                $item_query = "SELECT p.product_name,
                               COALESCE(pi.image_path, 'images/product/default_product_img.png') as image_path
                               FROM products p
                               LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.image_id = (
                                   SELECT MIN(image_id) FROM product_images WHERE product_id = p.product_id
                               )
                               WHERE p.product_id = ?";
                $item_stmt = mysqli_prepare($conn, $item_query);
                mysqli_stmt_bind_param($item_stmt, "i", $product_id);
                mysqli_stmt_execute($item_stmt);
                $item_result = mysqli_stmt_get_result($item_stmt);
                if ($item_row = mysqli_fetch_assoc($item_result)) {
                    $order['items'][] = [
                        'product_id' => $product_id,
                        'product_name' => $item_row['product_name'],
                        'image_path' => $item_row['image_path'],
                        'quantity' => $quantity
                    ];
                }
                mysqli_stmt_close($item_stmt);
            }
        }
        $my_sold_items[] = $order;
    }
    mysqli_stmt_close($stmt);

    // History Orders (completed, buyer or seller)
    $query = "SELECT o.order_id, o.buyer_id, o.seller_id, o.pay_method, o.checkout_at, o.completed_at, o.status, o.order_products,
                     COALESCE(ub.user_name, ub.fullname, 'Unknown') as buyer_name,
                     COALESCE(us.user_name, us.fullname, 'Unknown') as seller_name
              FROM orders o
              JOIN users ub ON o.buyer_id = ub.user_id
              JOIN users us ON o.seller_id = us.user_id
              WHERE (o.buyer_id = ? OR o.seller_id = ?) AND o.status = 'completed'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $order = $row;
        $order['items'] = [];
        $products = json_decode($row['order_products'], true) ?: [];
        foreach ($products as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            if ($product_id > 0 && $quantity > 0) {
                $item_query = "SELECT p.product_name,
                               COALESCE(pi.image_path, 'images/product/default_product_img.png') as image_path
                               FROM products p
                               LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.image_id = (
                                   SELECT MIN(image_id) FROM product_images WHERE product_id = p.product_id
                               )
                               WHERE p.product_id = ?";
                $item_stmt = mysqli_prepare($conn, $item_query);
                mysqli_stmt_bind_param($item_stmt, "i", $product_id);
                mysqli_stmt_execute($item_stmt);
                $item_result = mysqli_stmt_get_result($item_stmt);
                if ($item_row = mysqli_fetch_assoc($item_result)) {
                    $order['items'][] = [
                        'product_id' => $product_id,
                        'product_name' => $item_row['product_name'],
                        'image_path' => $item_row['image_path'],
                        'quantity' => $quantity
                    ];
                }
                mysqli_stmt_close($item_stmt);
            }
        }
        $history_orders[] = $order;
    }
    mysqli_stmt_close($stmt);
}

// Query user's products (my-product section)
$products = [];
if ($active_section === 'my-product') {
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $limit = 2;
    $offset = ($page - 1) * $limit;

    $count_query = "SELECT COUNT(*) as total FROM products WHERE seller_id = ?";
    $stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_products = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);

    $total_pages = ceil($total_products / $limit);

    $query = "SELECT product_id, product_name, category, description, in_stock, sold, price 
              FROM products 
              WHERE seller_id = ? 
              LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $product = $row;
        $product['images'] = ['images/product/default_product_img.png'];
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
                $product['images'] = $images;
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
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
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

            <?php elseif ($active_section === 'my-purchase'): ?>
                <h2>My Purchase</h2>
                <div class="form-section">
                    <div class="order-tabs">
                        <button class="tab-btn active" data-tab="purchased">My Purchased Orders</button>
                        <button class="tab-btn" data-tab="sold">My Sold Items</button>
                        <button class="tab-btn" data-tab="history">History Order</button>
                    </div>
                    <div class="tab-content" id="purchased">
                        <h3>My Purchased Orders</h3>
                        <?php if (empty($my_purchased_orders)): ?>
                            <p>No purchased orders.</p>
                        <?php else: ?>
                            <?php foreach ($my_purchased_orders as $order): ?>
                                <div class="order-card">
                                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($order['seller_name']); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['pay_method']))); ?></p>
                                    <p><strong>Checkout Time:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['checkout_at']))); ?></p>
                                    <div class="order-items">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="order-item">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                                <div class="order-item-details">
                                                    <p><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></p>
                                                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p><strong>Status:</strong> 
                                        <?php echo $order['status'] === 'pending' ? 'Waiting for seller to ship' : 'Order shipped, ready to complete'; ?>
                                    </p>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="complete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="action-btn complete-order-btn" 
                                                <?php echo $order['status'] === 'pending' ? 'disabled' : ''; ?>>
                                            Complete Order
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="tab-content" id="sold" style="display: none;">
                        <h3>My Sold Items</h3>
                        <?php if (empty($my_sold_items)): ?>
                            <p>No sold items.</p>
                        <?php else: ?>
                            <?php foreach ($my_sold_items as $order): ?>
                                <div class="order-card">
                                    <p><strong>Buyer:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['pay_method']))); ?></p>
                                    <p><strong>Checkout Time:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['checkout_at']))); ?></p>
                                    <div class="order-items">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="order-item">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                                <div class="order-item-details">
                                                    <p><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></p>
                                                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p><strong>Status:</strong> 
                                        <?php echo $order['status'] === 'pending' ? 'Ready to ship' : 'Order shipped'; ?>
                                    </p>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="ship_order">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="action-btn ship-order-btn" 
                                                <?php echo $order['status'] === 'shipped' ? 'disabled' : ''; ?>>
                                            Shipped Order
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="tab-content" id="history" style="display: none;">
                        <h3>History Order</h3>
                        <?php if (empty($history_orders)): ?>
                            <p>No completed orders.</p>
                        <?php else: ?>
                            <?php foreach ($history_orders as $order): ?>
                                <div class="order-card">
                                    <p><strong>Buyer:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($order['seller_name']); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['pay_method']))); ?></p>
                                    <p><strong>Checkout Time:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['checkout_at']))); ?></p>
                                    <p><strong>Completion Time:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['completed_at']))); ?></p>
                                    <div class="order-items">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="order-item">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                                <div class="order-item-details">
                                                    <p><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></p>
                                                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_section === 'my-product'): ?>
                <h2>My Product</h2>
                <div class="form-section my-product">
                    <a href="add_product.php" class="create-btn">Add Product</a>
                    <div class="product-grid">
                        <?php if (empty($products)): ?>
                            <p>No products available.</p>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <div class="product-card" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <div class="photo-display">
                                        <img src="<?php echo htmlspecialchars($product['images'][0]); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                             id="product-image-<?php echo htmlspecialchars($product['product_id']); ?>" 
                                             data-images='<?php echo htmlspecialchars(json_encode($product['images'])); ?>'
                                             data-current-index="0">
                                        <?php if (count($product['images']) > 1): ?>
                                            <button class="carousel-prev" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>">&lt;</button>
                                            <button class="carousel-next" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>">&gt;</button>
                                        <?php endif; ?>
                                        <?php if ($product['in_stock'] <= 0): ?>
                                            <span class="stock-badge">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                        <p class="category"><?php echo htmlspecialchars($product['category']); ?></p>
                                        <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                                        <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
                                        <p class="stock">Stock: <?php echo htmlspecialchars($product['in_stock']); ?></p>
                                        <div class="product-actions">
                                            <a href="edit_product.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>" class="edit-btn">Edit</a>
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this product?');">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($total_products > 0): ?>
                        <p class="product-count">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</p>
                        <div class="pagination">
                            <a href="?section=my-product&page=<?php echo max(1, $page - 1); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">Previous</a>
                            <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            <a href="?section=my-product&page=<?php echo min($total_pages, $page + 1); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($active_section === 'delete-account'): ?>
                <h2>Delete Account</h2>
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_account">
                        <div class="form-group">
                            <label for="confirm_delete">Confirm Account Deletion</label>
                            <input type="text" id="confirm_delete" name="confirm_delete" placeholder="Enter <?php echo htmlspecialchars($user['user_name']); ?>@delete to delete." required>
                        </div>
                        <button type="submit" class="create-btn" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">Delete Account</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
        <aside class="sidebar">
            <ul>
                <li><a href="?section=my-account" class="<?php echo $active_section === 'my-account' ? 'active' : ''; ?>">My Account</a></li>
                <li><a href="?section=change-password" class="<?php echo $active_section === 'change-password' ? 'active' : ''; ?>">Change Password</a></li>
                <li><a href="?section=my-purchase" class="<?php echo $active_section === 'my-purchase' ? 'active' : ''; ?>">My Purchase</a></li>
                <li><a href="?section=my-product" class="<?php echo $active_section === 'my-product' ? 'active' : ''; ?>">My Product</a></li>
                <li><a href="?section=delete-account" class="<?php echo $active_section === 'delete-account' ? 'active' : ''; ?>">Delete Account</a></li>
            </ul>
        </aside>
    </div>
    <footer>
        <p>© <?php echo date('Y'); ?> E-Shop System. All rights reserved.</p>
    </footer>
    <script>
        // Password toggle functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const img = button.querySelector('.password-toggle-img');
                if (input.type === 'password') {
                    input.type = 'text';
                    img.src = 'images/eye-open.png';
                } else {
                    input.type = 'password';
                    img.src = 'images/eye-close.png';
                }
            });
        });

        // Tab switching for My Purchase
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = content.id === tab ? 'block' : 'none';
                });
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-tab') === tab);
                });
            });
        });

        // Product image carousel functionality
        document.querySelectorAll('.product-card').forEach(card => {
            const productId = card.getAttribute('data-product-id');
            const imgElement = card.querySelector(`#product-image-${productId}`);
            const images = JSON.parse(imgElement.getAttribute('data-images'));
            let currentIndex = parseInt(imgElement.getAttribute('data-current-index')) || 0;

            const prevButton = card.querySelector(`.carousel-prev[data-product-id="${productId}"]`);
            const nextButton = card.querySelector(`.carousel-next[data-product-id="${productId}"]`);

            if (images.length <= 1) {
                if (prevButton) prevButton.style.display = 'none';
                if (nextButton) nextButton.style.display = 'none';
                return;
            }

            const updateImage = () => {
                imgElement.src = images[currentIndex];
                imgElement.setAttribute('data-current-index', currentIndex);
            };

            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    currentIndex = (currentIndex - 1 + images.length) % images.length;
                    updateImage();
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    currentIndex = (currentIndex + 1) % images.length;
                    updateImage();
                });
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>