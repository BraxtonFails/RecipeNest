<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - Recipe Nest</title>
  <link rel="stylesheet" href="../style/login.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
  <div class="wrapper">
    <h2 class="recipe-nest-title">Recipe Nest</h2>
    <form action="../api/auth/register.php" method="POST">
      <h1>Create your account</h1>

      <?php if (isset($_GET['error'])): ?>
      <p style="color:red;text-align:center;margin-bottom:10px;">
        <?php
          $msgs = [
            'missing_fields'   => 'Please fill in all fields.',
            'invalid_email'    => 'Please enter a valid email address.',
            'password_mismatch'=> 'Passwords do not match.',
            'password_short'   => 'Password must be at least 6 characters.',
            'email_taken'      => 'An account with that email already exists.',
            'server_error'     => 'Something went wrong. Please try again.',
          ];
          echo htmlspecialchars($msgs[$_GET['error']] ?? 'An error occurred.');
        ?>
      </p>
      <?php endif; ?>

      <div class="form-row">
        <div class="input-box">
          <input type="text" name="first_name" placeholder="First Name" required>
          <i class="bx bxs-user"></i>
        </div>
        <div class="input-box">
          <input type="text" name="last_name" placeholder="Last Name" required>
          <i class="bx bxs-user"></i>
        </div>
      </div>

      <div class="input-box">
        <input type="tel" name="phone" placeholder="(123) 456-7890">
        <i class="bx bxs-phone"></i>
      </div>

      <div class="input-box">
        <input type="email" name="email" placeholder="name@example.com" required>
        <i class="bx bxs-envelope"></i>
      </div>

      <div class="input-box">
        <input type="password" name="password" placeholder="Password" required>
        <i class="bx bxs-lock-alt"></i>
      </div>

      <div class="input-box">
        <input type="password" name="confirm_password" placeholder="Confirm password" required>
        <i class="bx bxs-lock"></i>
      </div>

      <button type="submit" class="btn">Register</button>

      <div class="register-link">
        <p>Already have an account? <a href="login.php">Back to Login</a></p>
      </div>
    </form>
  </div>
</body>
</html>
