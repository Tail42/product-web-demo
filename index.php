<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config.php'; // Include database connection settings

$user_picture = 'images/default_user.png';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT user_picture FROM users WHERE user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $user_picture = htmlspecialchars($row['user_picture'] ?? 'images/default_user.png');
        }
        mysqli_stmt_close($stmt);
    }
}

// Initialize search, category, and pagination parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Build product query
$query = "SELECT p.product_id, p.product_name, p.price, p.in_stock, 
                 COALESCE(GROUP_CONCAT(pi.image_path ORDER BY pi.image_id), 'images/product/default_product_img.png') as image_paths
          FROM products p
          LEFT JOIN product_images pi ON p.product_id = pi.product_id
          WHERE 1=1
          GROUP BY p.product_id, p.product_name, p.price, p.in_stock";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($category !== 'all') {
    $query .= " AND p.category = ?";
    $params[] = $category;
    $types .= 's';
}

// Count total products for pagination
$count_query = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
$count_params = [];
$count_types = '';

if (!empty($search)) {
    $count_query .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= 'ss';
}

if ($category !== 'all') {
    $count_query .= " AND p.category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

$stmt = mysqli_prepare($conn, $count_query);
if ($stmt && !empty($count_params)) {
    mysqli_stmt_bind_param($stmt, $count_types, ...$count_params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_products = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

$total_pages = ceil($total_products / $limit);

$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
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
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
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
                <button class="category-btn <?php echo $category === 'all' ? 'active' : ''; ?>" data-category="all">All Categories</button>
                <button class="category-btn <?php echo $category === 'Electronics & Accessories' ? 'active' : ''; ?>" data-category="Electronics & Accessories">Electronics & Accessories</button>
                <button class="category-btn <?php echo $category === 'Home Appliances & Living Essentials' ? 'active' : ''; ?>" data-category="Home Appliances & Living Essentials">Home Appliances</button>
                <button class="category-btn <?php echo $category === 'Clothing & Accessories' ? 'active' : ''; ?>" data-category="Clothing & Accessories">Clothing & Accessories</button>
                <button class="category-btn <?php echo $category === 'Beauty & Personal Care' ? 'active' : ''; ?>" data-category="Beauty & Personal Care">Beauty & Personal Care</button>
                <button class="category-btn <?php echo $category === 'Food & Beverages' ? 'active' : ''; ?>" data-category="Food & Beverages">Food & Beverages</button>
                <button class="category-btn <?php echo $category === 'Home & Furniture' ? 'active' : ''; ?>" data-category="Home & Furniture">Home & Furniture</button>
                <button class="category-btn <?php echo $category === 'Sports & Outdoor Equipment' ? 'active' : ''; ?>" data-category="Sports & Outdoor Equipment">Sports & Outdoor</button>
                <button class="category-btn <?php echo $category === 'Automotive & Motorcycle Accessories' ? 'active' : ''; ?>" data-category="Automotive & Motorcycle Accessories">Automotive</button>
                <button class="category-btn <?php echo $category === 'Baby & Maternity Products' ? 'active' : ''; ?>" data-category="Baby & Maternity Products">Baby & Maternity</button>
                <button class="category-btn <?php echo $category === 'Books & Office Supplies' ? 'active' : ''; ?>" data-category="Books & Office Supplies">Books & Office</button>
                <button class="category-btn <?php echo $category === 'Other' ? 'active' : ''; ?>" data-category="Other">Other</button>
            </div>
        </section>
        <section class="product-display">
            <div class="product-grid">
                <?php
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Split image_paths into an array
                        $image_paths = $row['image_paths'] ? explode(',', $row['image_paths']) : ['images/product/default_product_img.png'];
                        $first_image = $image_paths[0];
                        echo '<div class="product-card">';
                        echo '<div class="image-carousel">';
                        echo '<button class="carousel-prev" data-product-id="' . htmlspecialchars($row['product_id']) . '"><</button>';
                        echo '<img src="' . htmlspecialchars($first_image) . '" alt="' . htmlspecialchars($row['product_name']) . '" class="carousel-image" data-product-id="' . htmlspecialchars($row['product_id']) . '">';
                        echo '<button class="carousel-next" data-product-id="' . htmlspecialchars($row['product_id']) . '">></button>';
                        echo '</div>';
                        echo '<h3>' . htmlspecialchars($row['product_name']) . '</h3>';
                        echo '<p>Price: $' . htmlspecialchars(number_format($row['price'], 2)) . '</p>';
                        echo '<p>Stock: ' . htmlspecialchars($row['in_stock']) . '</p>';
                        echo '<a href="product.php?id=' . htmlspecialchars($row['product_id']) . '">View Details</a>';
                        echo '</div>';
                        // Inline JavaScript for carousel
                        echo '<script>';
                        echo 'const images' . $row['product_id'] . ' = ' . json_encode($image_paths) . ';';
                        echo 'let currentIndex' . $row['product_id'] . ' = 0;';
                        echo 'const imgElement' . $row['product_id'] . ' = document.querySelector(\'.carousel-image[data-product-id="' . $row['product_id'] . '"]\');';
                        echo 'const prevButton' . $row['product_id'] . ' = document.querySelector(\'.carousel-prev[data-product-id="' . $row['product_id'] . '"]\');';
                        echo 'const nextButton' . $row['product_id'] . ' = document.querySelector(\'.carousel-next[data-product-id="' . $row['product_id'] . '"]\');';
                        echo 'function updateImage' . $row['product_id'] . '() {';
                        echo '  imgElement' . $row['product_id'] . '.src = images' . $row['product_id'] . '[currentIndex' . $row['product_id'] . '];';
                        echo '}';
                        echo 'prevButton' . $row['product_id'] . '.addEventListener("click", () => {';
                        echo '  currentIndex' . $row['product_id'] . ' = (currentIndex' . $row['product_id'] . ' - 1 + images' . $row['product_id'] . '.length) % images' . $row['product_id'] . '.length;';
                        echo '  updateImage' . $row['product_id'] . '();';
                        echo '});';
                        echo 'nextButton' . $row['product_id'] . '.addEventListener("click", () => {';
                        echo '  currentIndex' . $row['product_id'] . ' = (currentIndex' . $row['product_id'] . ' + 1) % images' . $row['product_id'] . '.length;';
                        echo '  updateImage' . $row['product_id'] . '();';
                        echo '});';
                        echo '</script>';
                    }
                } else {
                    echo '<p>No products found.</p>';
                }
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
                ?>
            </div>
            <div class="pagination">
                <?php
                $prev_page = $page > 1 ? $page - 1 : 1;
                $next_page = $page < $total_pages ? $page + 1 : $total_pages;
                ?>
                <a href="index.php?<?php echo http_build_query(['search' => $search, 'category' => $category, 'page' => $prev_page]); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">Previous</a>
                <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <a href="index.php?<?php echo http_build_query(['search' => $search, 'category' => $category, 'page' => $next_page]); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next</a>
            </div>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
        document.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                const url = new URL(window.location.href);
                url.searchParams.set('category', category);
                url.searchParams.set('page', '1'); // Reset to page 1 on category change
                window.location.href = url.toString();
            });
        });
    </script>
    <?php mysqli_close($conn); ?>
</body>
</html>