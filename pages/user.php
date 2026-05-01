<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

require_once '../config/db.php';

$profile_id = (int)($_GET['id'] ?? 0);
if (!$profile_id) { header('Location: ../index.php'); exit; }

// Query 1: profile user
$stmt = $conn->prepare('SELECT UserID, Username, Name, Bio, ProfilePictureURL FROM users WHERE UserID = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$profile) { header('Location: ../index.php'); exit; }

// Query 2: follower count
$stmt = $conn->prepare('SELECT COUNT(*) FROM follows WHERE FollowingID = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$stmt->bind_result($follower_count);
$stmt->fetch();
$stmt->close();

// Query 3: following count
$stmt = $conn->prepare('SELECT COUNT(*) FROM follows WHERE FollowerID = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$stmt->bind_result($following_count);
$stmt->fetch();
$stmt->close();

$is_own_profile = $logged_in && (int)$_SESSION['user_id'] === $profile_id;
$viewer_follows = false;
if ($logged_in && !$is_own_profile) {
    $viewer_uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT 1 FROM follows WHERE FollowerID = ? AND FollowingID = ?');
    $stmt->bind_param('ii', $viewer_uid, $profile_id);
    $stmt->execute();
    $stmt->store_result();
    $viewer_follows = $stmt->num_rows > 0;
    $stmt->close();
}

// Query 5: this user's recipes
$stmt = $conn->prepare("
    SELECT r.RecipeID, r.Title, r.Difficulty, r.TotalCookTime,
           m.MediaURL,
           COUNT(DISTINCT l.UserID) AS LikeCount
    FROM recipes r
    LEFT JOIN media m ON m.RecipeID = r.RecipeID AND m.MediaType = 'image' AND m.OrderIndex = (
        SELECT MIN(m2.OrderIndex) FROM media m2 WHERE m2.RecipeID = r.RecipeID AND m2.MediaType = 'image'
    )
    LEFT JOIN likes l ON l.RecipeID = r.RecipeID
    WHERE r.UserID = ?
    GROUP BY r.RecipeID
    ORDER BY r.DateCreation DESC
    LIMIT 24
");
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function avatar_color($id) {
    $p = ['#7dbf77','#e6845a','#5a8fe6','#9b6de6','#e6b05a','#5ab8e6','#e6527a','#52b8a0'];
    return $p[(int)$id % count($p)];
}

function format_time_u($t) {
    if (!$t) return null;
    [$h, $m] = explode(':', $t);
    $h = (int)$h; $m = (int)$m;
    if ($h > 0 && $m > 0) return "{$h} hr {$m} min";
    if ($h > 0) return "{$h} hr";
    return "{$m} min";
}

function recipe_card_u($r) {
    $img   = $r['MediaURL'] ? '../' . htmlspecialchars($r['MediaURL']) : '../public/images/recipe_background.jpg';
    $title = htmlspecialchars($r['Title']);
    $id    = (int)$r['RecipeID'];
    $diff  = htmlspecialchars($r['Difficulty'] ?? '');
    $time  = format_time_u($r['TotalCookTime']);
    $likes = (int)$r['LikeCount'];
    echo '<div class="rn-card-wrap"><div class="card rn-card position-relative">';
    echo "<img src=\"{$img}\" class=\"rn-thumb card-img-top\" alt=\"{$title}\">";
    echo '<div class="card-body">';
    echo "<h3 class=\"rn-card-title\">{$title}</h3>";
    echo '<div class="rn-meta">';
    echo "<span class=\"rn-pill green\"><i class=\"bi bi-heart-fill me-1\" aria-hidden=\"true\"></i>{$likes}</span>";
    if ($time) echo "<span class=\"rn-pill\"><i class=\"bi bi-clock me-1\" aria-hidden=\"true\"></i>{$time}</span>";
    if ($diff) echo "<span class=\"rn-pill orange\">{$diff}</span>";
    echo '</div>';
    echo "<a href=\"recipe.php?id={$id}\" class=\"stretched-link\" aria-label=\"View {$title} recipe\"></a>";
    echo '</div></div></div>';
}

$display_name = htmlspecialchars($profile['Name'] ?: $profile['Username']);
$username     = htmlspecialchars($profile['Username']);
$initials     = strtoupper(mb_substr($display_name, 0, 1));
$av_color     = avatar_color($profile_id);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $display_name; ?> – RecipeNest</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../public/css/recipenest.css?v=<?php echo filemtime('../public/css/recipenest.css'); ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
</head>
<body>

  <header class="rn-header">
    <nav class="navbar navbar-expand-lg">
      <div class="container">
        <a href="../index.php" class="navbar-brand rn-brand-link d-flex align-items-center">
          <span class="rn-brand-text">RecipeNest</span>
        </a>
        <button class="navbar-toggler rn-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="search.php">Search</a></li>
            
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($logged_in): ?>
            <?php if ($_SESSION["user_role"] === "admin"): ?><li class="nav-item"><a class="nav-link rn-nav-link" href="../pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li><?php endif; ?>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="profile.php">Hi, <?php echo htmlspecialchars($user_name); ?></a></li>
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

          <!-- Profile header -->
          <div class="card rn-card mb-4">
            <div class="rn-profile-header">
              <div class="rn-profile-avatar" style="background:<?php echo $av_color; ?>">
                <span class="rn-profile-initials"><?php echo $initials; ?></span>
              </div>
              <h1 class="rn-profile-name"><?php echo $display_name; ?></h1>
              <p class="rn-profile-username">@<?php echo $username; ?></p>
              <?php if ($profile['Bio']): ?>
              <p class="rn-profile-bio"><?php echo htmlspecialchars($profile['Bio']); ?></p>
              <?php endif; ?>
              <div class="rn-profile-stats">
                <span><strong><?php echo $follower_count; ?></strong> followers</span>
                <span><strong><?php echo $following_count; ?></strong> following</span>
                <span><strong><?php echo count($recipes); ?></strong> recipes</span>
              </div>
              <?php if ($is_own_profile): ?>
              <a href="settings.php" class="btn btn-rn-outline">Edit Profile</a>
              <?php elseif ($logged_in): ?>
              <form method="POST" action="../api/follow.php">
                <input type="hidden" name="user_id" value="<?php echo $profile_id; ?>">
                <input type="hidden" name="redirect" value="../pages/user.php?id=<?php echo $profile_id; ?>">
                <button type="submit" class="btn <?php echo $viewer_follows ? 'btn-rn-primary' : 'btn-rn-outline'; ?>">
                  <i class="bi bi-person-<?php echo $viewer_follows ? 'check-fill' : 'plus'; ?> me-1"></i>
                  <?php echo $viewer_follows ? 'Following' : 'Follow'; ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recipe grid -->
          <h2 class="rn-row-title mb-3">
            Recipes
            <span class="rn-comments-count"><?php echo count($recipes); ?></span>
          </h2>
          <?php if (empty($recipes)): ?>
          <p class="text-muted">No recipes yet.</p>
          <?php else: ?>
          <div class="rn-profile-recipe-grid">
            <?php foreach ($recipes as $r) recipe_card_u($r); ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
