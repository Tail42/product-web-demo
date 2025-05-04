<?php
session_start();
include 'config.php'; // 包含資料庫連線設定

$user_picture = 'images/default_user.png';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT user_picture FROM users WHERE user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $user_picture = htmlspecialchars($row['user_picture'] ?? 'images/default_user.png');
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shop Main Page</title>
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
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <a href="user.php"><img src="<?php echo $user_picture; ?>" alt="Profile" class="user-pic"></a>
                    <a href="cart.php">Cart</a>
                    <a href="php/logout.php">Logout</a>
                <?php } else { ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php } ?>
            </div>
        </div>
    </header>
    <main>
        <section class="category-selection">
            <div class="category-grid">
                <button class="category-btn" data-category="all">All Categories</button>
                <button class="category-btn" data-category="Electronics & Accessories">Electronics & Accessories</button>
                <button class="category-btn" data-category="Home Appliances & Living Essentials">Home Appliances</button>
                <button class="category-btn" data-category="Clothing & Accessories">Clothing & Accessories</button>
                <button class="category-btn" data-category="Beauty & Personal Care">Beauty & Personal Care</button>
                <button class="category-btn" data-category="Food & Beverages">Food & Beverages</button>
                <button class="category-btn" data-category="Home & Furniture">Home & Furniture</button>
                <button class="category-btn" data-category="Sports & Outdoor Equipment">Sports & Outdoor</button>
                <button class="category-btn" data-category="Automotive & Motorcycle Accessories">Automotive</button>
                <button class="category-btn" data-category="Baby & Maternity Products">Baby & Maternity</button>
                <button class="category-btn" data-category="Books & Office Supplies">Books & Office</button>
                <button class="category-btn" data-category="Other">Other</button>
            </div>
        </section>
        <section class="product-display">
            <div class="product-grid">
                <?php
                // 簡單查詢產品（稍後可添加搜索和類別篩選邏輯）
                $query = "SELECT * FROM products LIMIT 8";
                $result = mysqli_query($conn, $query);
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo '<div class="product-card">';
                        echo '<img src="' . htmlspecialchars($row['product_image']) . '" alt="' . htmlspecialchars($row['product_name']) . '">';
                        echo '<h3>' . htmlspecialchars($row['product_name']) . '</h3>';
                        echo '<p>Price: $' . htmlspecialchars($row['price']) . '</p>';
                        echo '<p>Stock: ' . htmlspecialchars($row['in_stock']) . '</p>';
                        echo '<a href="product.php?id=' . htmlspecialchars($row['product_id']) . '">View Details</a>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No products found.</p>';
                }
                mysqli_close($conn);
                ?>
            </div>
            <div class="pagination">
                <button class="page-btn">Previous</button>
                <button class="page-btn">Next</button>
            </div>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>