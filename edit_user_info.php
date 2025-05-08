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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_account') {
    $new_name = trim($_POST['new_name'] ?? '');
    $new_fullname = trim($_POST['new_fullname'] ?? '');
    $new_address = trim($_POST['new_address'] ?? '');
    $new_phone = trim($_POST['new_phone'] ?? '');
    $user_picture = $user['user_picture'] ?? 'images/default_user.png';

    if (empty($new_name) || empty($new_fullname) || empty($new_address) || empty($new_phone)) {
        $error_message = 'All fields are required.';
    } else {
        // Handle profile picture upload
        if (isset($_FILES['user_picture']) && $_FILES['user_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_tmp = $_FILES['user_picture']['tmp_name'];
            $file_name = basename($_FILES['user_picture']['name']);
            $new_file_name = 'user_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $user_picture = 'images/' . $new_file_name;
            } else {
                $error_message = 'Failed to upload profile picture.';
            }
        }

        if (empty($error_message)) {
            $query = "UPDATE users SET user_name = ?, fullname = ?, address = ?, phone = ?, user_picture = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssssi", $new_name, $new_fullname, $new_address, $new_phone, $user_picture, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Account updated successfully!';
                // Update session user info
                $user['user_name'] = $new_name;
                $user['fullname'] = $new_fullname;
                $user['address'] = $new_address;
                $user['phone'] = $new_phone;
                $user['user_picture'] = $user_picture;
            } else {
                $error_message = 'Update failed: ' . mysqli_error($conn);
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
    <title>Edit Profile - E-Shop</title>
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
            <h2>Edit Profile</h2>
            <?php if ($success_message): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
                <script>
                    alert('<?php echo addslashes($success_message); ?>');
                </script>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <div class="form-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_account">
                    <div class="form-group">
                        <label for="user_picture">Profile Picture</label>
                        <div class="profile-picture-preview">
                            <img src="<?php echo htmlspecialchars($user['user_picture'] ?? 'images/default_user.png'); ?>" alt="Profile Preview" id="profile-preview">
                        </div>
                        <input type="file" id="user_picture" name="user_picture" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <div class="form-group">
                        <label for="new_name">User Name</label>
                        <input type="text" id="new_name" name="new_name" value="<?php echo htmlspecialchars($user['user_name'] ?? ''); ?>" placeholder="New User Name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_fullname">Full Name</label>
                        <input type="text" id="new_fullname" name="new_fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" placeholder="New Full Name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_address">Address</label>
                        <input type="text" id="new_address" name="new_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="New Address" required>
                    </div>
                    <div class="form-group">
                        <label for="new_phone">Phone</label>
                        <input type="tel" id="new_phone" name="new_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="New Phone" required>
                    </div>
                    <button type="submit" class="create-btn">Update Profile</button>
                </form>
                <a href="user.php?section=my-account" class="back-btn">Back to Profile</a>
            </div>
        </main>
    </div>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script>
        // Image preview functionality
        document.getElementById('user_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
    <?php mysqli_close($conn); ?>
</body>
</html>