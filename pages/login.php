<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Recipe Nest</title>
    <link rel="stylesheet" href="../public/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <h2 class="recipe-nest-title">Recipe Nest</h2>
        <form action="../api/auth/login.php" method="POST">
            <h1>Login</h1>
            <?php if (isset($_GET['error'])): ?>
            <p style="color:red;text-align:center;margin-bottom:10px;">
                <?php
                    $msgs = [
                        'missing_fields'      => 'Please fill in all fields.',
                        'invalid_credentials' => 'Incorrect email or password.',
                    ];
                    echo htmlspecialchars($msgs[$_GET['error']] ?? 'An error occurred.');
                ?>
            </p>
            <?php endif; ?>
            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required>
                <i class='bx bxs-user'></i>
            </div>
            <div class="input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class='bx bxs-lock-alt' ></i>
            </div>
            <div class="remember-forgot">
                <label><input type="checkbox"> Remember me</label>
                <a href="#">Forgot Password?</a>
            </div>
            <button type="submit" class="btn">Login</button>
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register</a></p>
                <p>Or</p>
                <p><a href="admin.html">Login as Admin</a></p>
            </div>
        </form>
    </div>
</body>
</html>