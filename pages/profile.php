<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT Username, Email, Name, Bio, ProfilePictureURL, DateCreation FROM users WHERE UserID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $name, $bio, $profile_pic, $date_created);
$stmt->fetch();
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profile – RecipeNest</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../style/recipenest.css" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
</head>
<body>
  <header class="rn-header">
    <nav class="navbar navbar-expand-lg">
      <div class="container">
        <a href="../index.php" class="navbar-brand rn-brand-link d-flex align-items-center"><span class="rn-brand-text">RecipeNest</span></a>
        <button class="navbar-toggler rn-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link active" aria-current="page" href="profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($logged_in): ?>
            <?php if ($_SESSION["user_role"] === "admin"): ?><li class="nav-item"><a class="nav-link rn-nav-link" href="../pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li><?php endif; ?><li class="nav-item"><span class="nav-link rn-nav-link">Hi, <?php echo htmlspecialchars($user_name); ?></span></li>
            <li class="nav-item"><a class="nav-link rn-nav-link rn-nav-link-cta" href="../api/auth/logout.php">Logout</a></li>
            <?php else: ?>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="register.php">Register</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link rn-nav-link-cta" href="login.php">Login</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main class="py-5">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">

          <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success mb-4">Profile updated successfully.</div>
          <?php elseif (isset($_GET['error'])): ?>
          <div class="alert alert-danger mb-4">
            <?php
              $msgs = [
                'missing_fields'   => 'Please fill in all required fields.',
                'invalid_email'    => 'Please enter a valid email address.',
                'taken'            => 'That email or username is already in use.',
                'wrong_password'   => 'Current password is incorrect.',
                'password_mismatch'=> 'New passwords do not match.',
                'password_short'   => 'Password must be at least 6 characters.',
                'server_error'     => 'Something went wrong. Please try again.',
              ];
              echo htmlspecialchars($msgs[$_GET['error']] ?? 'An error occurred.');
            ?>
          </div>
          <?php endif; ?>

          <!-- Account Info -->
          <div class="card rn-card mb-4">
            <div class="card-body p-4">
              <h2 class="rn-upload-title mb-1">Account Info</h2>
              <p class="text-muted small mb-4">Member since <?php echo date('F j, Y', strtotime($date_created)); ?></p>
              <form action="../api/profile/update.php" method="POST">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="input-group">
                      <span class="input-group-text">@</span>
                      <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Bio</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Tell people a little about yourself..."><?php echo htmlspecialchars($bio ?? ''); ?></textarea>
                  </div>
                </div>
                <hr class="my-4">
                <button type="submit" class="btn btn-rn-primary">Save Changes</button>
              </form>
            </div>
          </div>

          <!-- Change Password -->
          <div class="card rn-card mb-4">
            <div class="card-body p-4">
              <h2 class="h5 fw-bold mb-3">Change Password</h2>
              <form action="../api/profile/change_password.php" method="POST">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label fw-semibold">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                  </div>
                </div>
                <hr class="my-4">
                <button type="submit" class="btn btn-rn-primary">Update Password</button>
              </form>
            </div>
          </div>

        </div>
      </div>
    </div>
  </main>

  <footer class="rn-footer py-4 mt-auto">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center small">
      <div class="rn-footer-copy">© 2026 RecipeNest</div>
      <div class="mt-2 mt-md-0">
        <a href="../index.php" class="rn-footer-link me-3">Home</a>
        <a href="profile.php" class="rn-footer-link">Profile</a>
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
