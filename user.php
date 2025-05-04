<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
mysqli_close($conn);
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
                    <input type="text" name="search" placeholder="Search products..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            <div class="user-function">
                <a href="user.php"><img
                        src="<?php echo htmlspecialchars($user['user_picture'] ?? 'images/default_user.png'); ?>"
                        alt="Profile" class="user-pic"></a>
                <a href="cart.php">Cart</a>
                <a href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>
    <main>
        <section class="product-display" style="text-align: center; padding: 50px;">
            <h2>User Profile</h2>
            <p><strong>User Name:</strong> <?php echo htmlspecialchars($user['user_name'] ?? 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['account'] ?? 'N/A'); ?></p>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fullname'] ?? 'N/A'); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
            <img src="<?php echo htmlspecialchars($user['user_picture'] ?? 'images/default_user.png'); ?>"
                alt="Profile Photo" style="max-width: 200px; border-radius: 50%; margin-top: 20px;">
            <p><a href="index.php">Back to Home</a></p>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>

</html>