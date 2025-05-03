<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <header>
        <div class="main-banner">
            <a href="index.php" class="logo">E-Shop System</a>
            <div class="user-function">
                <a href="register.php">Register</a>
            </div>
        </div>
    </header>
    <main>
        <section class="login-form">
            <h2>Login</h2>
            <form action="php/login_process.php" method="POST">
                <div class="form-group">
                    <label for="account">Email</label>
                    <input type="email" id="account" name="account" required>
                </div>
                <div class="form-group password-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password">Show</button>
                </div>
                <button type="submit" class="submit-btn">Login</button>
                <?php if (isset($_SESSION['login_error'])) { ?>
                    <p class="error"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></p>
                <?php } ?>
                <p>Don't have an account? <a href="register.php">Register here</a>.</p>
            </form>
        </section>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>