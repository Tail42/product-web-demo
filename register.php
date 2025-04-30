<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <header>
        <div class="main-banner">
            <a href="index.php" class="logo">E-shop System</a>
            <div>
                <a href="login.php">Login</a>
            </div>
        </div>
    </header>
    <main>
        <h2>Create an account</h2>
        <div class="form-group">
            <label>Input your name</label>
            <input type="content" placeholder="name" required>

            <label>Input your email (account)</label>
            <input type="email" placeholder="email" required>
        </div>
        <div class="password-group form-group">
            <label>Input your password</label>
            <input type="password" placeholder="Password" id="myInput" required>
            <img src="images/eye-close.png">
        </div>
        

        <label>Confirm your password</label>
        <input type="password" required>
        <button type="button">show password</button>

        <label>Upload your user photo</label>
        <input type="file">

        <button type="button" class="create-btn">CREATE ACCOUNT!</button>
    </main>
    <footer>
        <p>© 2025 E-Shop System</p>
    </footer>
    <script src="js/script.js"></script>
</body>