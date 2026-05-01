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

function avatar_color($id) {
    $p = ['#7dbf77','#e6845a','#5a8fe6','#9b6de6','#e6b05a','#5ab8e6','#e6527a','#52b8a0'];
    return $p[(int)$id % count($p)];
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT Username, Name, Bio, DateCreation FROM users WHERE UserID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $name, $bio, $date_created);
$stmt->fetch();
$stmt->close();

// Follower count
$stmt = $conn->prepare('SELECT COUNT(*) FROM follows WHERE FollowingID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($follower_count);
$stmt->fetch();
$stmt->close();

// Following count
$stmt = $conn->prepare('SELECT COUNT(*) FROM follows WHERE FollowerID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($following_count);
$stmt->fetch();
$stmt->close();

// Recipe count + grid
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
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$display_name = $name ?: $username;
$initial      = strtoupper(mb_substr($display_name, 0, 1));
$av_color     = avatar_color($user_id);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($display_name); ?> – RecipeNest</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../public/css/recipenest.css?v=<?php echo filemtime('../public/css/recipenest.css'); ?>" />
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
            <li class="nav-item"><a class="nav-link rn-nav-link" href="search.php">Search</a></li>
            
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($_SESSION["user_role"] === "admin"): ?><li class="nav-item"><a class="nav-link rn-nav-link" href="../pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li><?php endif; ?>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="profile.php">Hi, <?php echo htmlspecialchars($user_name); ?></a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link rn-nav-link-cta" href="../api/auth/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main class="py-4">
    <div class="container">

      <!-- Profile header — constrained width -->
      <div class="row justify-content-center mb-3">
        <div class="col-lg-7 col-xl-6">
          <div class="rn-ig-header">
            <div class="rn-ig-avatar" style="background:<?php echo $av_color; ?>">
              <span class="rn-ig-initial"><?php echo $initial; ?></span>
            </div>
            <div class="rn-ig-info">
              <div class="rn-ig-name-row">
                <h1 class="rn-ig-name"><?php echo htmlspecialchars($display_name); ?></h1>
                <a href="settings.php" class="btn btn-rn-outline btn-sm rn-ig-edit-btn">
                  <i class="bi bi-pencil me-1"></i>Edit profile
                </a>
              </div>
              <p class="rn-ig-username">@<?php echo htmlspecialchars($username); ?></p>
              <?php if ($bio): ?>
              <p class="rn-ig-bio"><?php echo htmlspecialchars($bio); ?></p>
              <?php endif; ?>
              <div class="rn-ig-stats">
                <span><strong><?php echo count($recipes); ?></strong> recipes</span>
                <span><strong><?php echo $follower_count; ?></strong> followers</span>
                <span><strong><?php echo $following_count; ?></strong> following</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <hr class="rn-ig-divider">

      <!-- 3-col recipe grid — wider column so cells are genuinely small -->
      <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8 px-0 px-sm-3">
          <?php if (empty($recipes)): ?>
          <p class="text-muted text-center py-5">No recipes yet. <a href="upload.php" class="rn-author-link">Upload your first one!</a></p>
          <?php else: ?>
          <div class="rn-ig-grid">
            <?php foreach ($recipes as $r):
              $img   = $r['MediaURL'] ? '../' . htmlspecialchars($r['MediaURL']) : '../public/images/recipe_background.jpg';
              $title = htmlspecialchars($r['Title']);
              $likes = (int)$r['LikeCount'];
            ?>
            <a href="recipe.php?id=<?php echo (int)$r['RecipeID']; ?>" class="rn-ig-cell" title="<?php echo $title; ?>">
              <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" />
              <div class="rn-ig-cell-overlay">
                <span class="rn-ig-cell-title"><?php echo $title; ?></span>
                <span class="rn-ig-cell-likes"><i class="bi bi-heart-fill me-1"></i><?php echo $likes; ?></span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
