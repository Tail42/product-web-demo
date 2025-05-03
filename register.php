<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
</head>

<body>
    <header>
        <div class="main-banner">
            <a href="index.php" class="logo">E-Shop System</a>
            <div class="user-function">
                <a href="login.php">Login</a>
            </div>
        </div>
    </header>
    <main>
        <section class="register-form">
            <h2>Create an Account</h2>
            <form action="php/register_process.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label for="account">Email</label>
                    <input type="email" id="account" name="account" placeholder="Enter your email" required>
                </div>
                <div class="form-group password-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password">
                        <img src="images/eye-close.png" alt="Toggle Password" class="password-toggle-img">
                    </button>
                </div>
                <div class="form-group password-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                    <button type="button" class="toggle-password">
                        <img src="images/eye-close.png" alt="Toggle Password" class="password-toggle-img">
                    </button>
                </div>
                <div class="form-group">
                    <label for="user-photo">Profile Photo</label>
                    <input type="file" id="user-photo" name="user-photo" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" placeholder="Enter your address" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone number</label>
                    <input type="text" id="phone" name="phone" placeholder="Enter your phone number" required>
                </div>
                <button type="submit" class="create-btn">Create Account</button>
            </form>
        </section>
        <?php if (isset($_SESSION['register_error'])) { ?>
            <p class="error"><?php echo $_SESSION['register_error'];
            unset($_SESSION['register_error']); ?></p>
        <?php } ?>
        <?php if (isset($_SESSION['register_success'])) { ?>
            <p class="success"><?php echo $_SESSION['register_success'];
            unset($_SESSION['register_success']); ?></p>
        <?php } ?>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>

</html>