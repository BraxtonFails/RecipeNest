<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

require_once 'config/db.php';

// Helper: format TIME column (HH:MM:SS) → "1 hr 5 min" / "35 min"
function format_time($t) {
    if (!$t) return null;
    [$h, $m] = explode(':', $t);
    $h = (int)$h; $m = (int)$m;
    if ($h > 0 && $m > 0) return "{$h} hr {$m} min";
    if ($h > 0) return "{$h} hr";
    return "{$m} min";
}

// Helper: render a recipe card
function recipe_card($r) {
    $img   = $r['MediaURL'] ? htmlspecialchars($r['MediaURL']) : 'public/images/recipe_background.jpg';
    $title = htmlspecialchars($r['Title']);
    $id    = (int)$r['RecipeID'];
    $diff  = htmlspecialchars($r['Difficulty'] ?? '');
    $time  = format_time($r['TotalCookTime']);
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
    echo "<a href=\"pages/recipe.php?id={$id}\" class=\"stretched-link\" aria-label=\"View {$title} recipe\"></a>";
    echo '</div></div></div>';
}

// Helper: run a recipe query and return rows
function fetch_recipes($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$base_select = "
    SELECT r.RecipeID, r.Title, r.Difficulty, r.TotalCookTime,
           m.MediaURL,
           COUNT(DISTINCT l.UserID) AS LikeCount
    FROM recipes r
    LEFT JOIN media m ON m.RecipeID = r.RecipeID AND m.MediaType = 'image' AND m.OrderIndex = (
        SELECT MIN(m2.OrderIndex) FROM media m2 WHERE m2.RecipeID = r.RecipeID AND m2.MediaType = 'image'
    )
    LEFT JOIN likes l ON l.RecipeID = r.RecipeID
";

// Featured: newest
$featured = fetch_recipes($conn, $base_select . " GROUP BY r.RecipeID ORDER BY r.DateCreation DESC LIMIT 12");

// People's Choice: most liked
$popular = fetch_recipes($conn, $base_select . " GROUP BY r.RecipeID ORDER BY LikeCount DESC, r.DateCreation DESC LIMIT 12");

// Liked by current user
$liked = [];
if ($logged_in) {
    $liked_uid = $_SESSION['user_id'];
    $liked = fetch_recipes($conn, $base_select . "
        INNER JOIN likes lk ON lk.RecipeID = r.RecipeID AND lk.UserID = ?
        GROUP BY r.RecipeID ORDER BY r.DateCreation DESC LIMIT 12
    ", 'i', [$liked_uid]);
}

// Vegan: tagged with 'vegan'
$vegan = fetch_recipes($conn, $base_select . "
    INNER JOIN recipehashtags rh ON rh.RecipeID = r.RecipeID
    INNER JOIN hashtags h ON h.HashtagID = rh.HashtagID AND LOWER(h.TagName) = 'vegan'
    GROUP BY r.RecipeID ORDER BY r.DateCreation DESC LIMIT 12
");
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Discover, create, and share your favorite recipes in one place." />
    <title>RecipeNest – Discover &amp; Share Recipes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="public/css/recipenest.css" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet" />
    <meta name="theme-color" content="#7dbf77" />
  </head>
  <body>
    <!-- Page header and navbar -->
    <header class="rn-header">
      <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
          <a href="index.php" class="navbar-brand rn-brand-link d-flex align-items-center">
            <span class="rn-brand-text">RecipeNest</span>
          </a>
          <button
            class="navbar-toggler rn-nav-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
          >
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
              <li class="nav-item">
                <a class="nav-link rn-nav-link active" aria-current="page" href="index.php">Home</a>
              </li>
              <li class="nav-item">
                <a class="nav-link rn-nav-link" href="pages/profile.php">Profile</a>
              </li>
              <li class="nav-item">
                <a class="nav-link rn-nav-link" href="pages/upload.php">Upload</a>
              </li>
              <?php if ($logged_in): ?>
              <?php if ($_SESSION['user_role'] === 'admin'): ?>
              <li class="nav-item">
                <a class="nav-link rn-nav-link" href="pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <span class="nav-link rn-nav-link">Hi, <?php echo htmlspecialchars($user_name); ?></span>
              </li>
              <li class="nav-item">
                <a class="nav-link rn-nav-link rn-nav-link-cta" href="api/auth/logout.php">Logout</a>
              </li>
              <?php else: ?>
              <li class="nav-item">
                <a class="nav-link rn-nav-link" href="pages/register.php">Register</a>
              </li>
              <li class="nav-item">
                <a class="nav-link rn-nav-link rn-nav-link-cta" href="pages/login.php">Login</a>
              </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </nav>
    </header>

    <main>
      <!-- Hero -->
      <section class="hero text-center">
        <div class="container">
          <h1 class="hero-title">RecipeNest</h1>
          <p class="hero-sub">Discover, create, and share your favorite recipes in one place.</p>
        </div>
      </section>

      <!-- Recipe grid: 3 rows, each row scrollable with arrows -->
      <section class="rn-recipes py-5">
        <div class="container">
          <div class="rn-recipes-rows">
            <!-- Liked recipes (logged in users only) -->
            <?php if ($logged_in): ?>
            <h2 class="rn-row-title">Your liked recipes</h2>
            <div class="rn-row-wrapper">
              <button type="button" class="rn-row-arrow rn-row-arrow-left" aria-label="Scroll row left"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <div class="rn-row-scroll" tabindex="0">
                <?php if ($liked) { foreach ($liked as $r) recipe_card($r); }
                else { echo '<p class="text-muted p-3">You haven\'t liked any recipes yet.</p>'; } ?>
              </div>
              <button type="button" class="rn-row-arrow rn-row-arrow-right" aria-label="Scroll row right"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            <?php endif; ?>
            <!-- Row 1: Featured -->
            <h2 class="rn-row-title">Featured recipes</h2>
            <div class="rn-row-wrapper">
              <button type="button" class="rn-row-arrow rn-row-arrow-left" aria-label="Scroll row left"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <div class="rn-row-scroll" tabindex="0">
                <?php if ($featured) { foreach ($featured as $r) recipe_card($r); }
                else { echo '<p class="text-muted p-3">No recipes yet.</p>'; } ?>
              </div>
              <button type="button" class="rn-row-arrow rn-row-arrow-right" aria-label="Scroll row right"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            <!-- Row 2: People's Choice -->
            <h2 class="rn-row-title">People's choice</h2>
            <div class="rn-row-wrapper">
              <button type="button" class="rn-row-arrow rn-row-arrow-left" aria-label="Scroll row left"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <div class="rn-row-scroll" tabindex="0">
                <?php if ($popular) { foreach ($popular as $r) recipe_card($r); }
                else { echo '<p class="text-muted p-3">No recipes yet.</p>'; } ?>
              </div>
              <button type="button" class="rn-row-arrow rn-row-arrow-right" aria-label="Scroll row right"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            <!-- Row 3: Vegan -->
            <h2 class="rn-row-title">Vegan choices</h2>
            <div class="rn-row-wrapper">
              <button type="button" class="rn-row-arrow rn-row-arrow-left" aria-label="Scroll row left"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <div class="rn-row-scroll" tabindex="0">
                <?php if ($vegan) { foreach ($vegan as $r) recipe_card($r); }
                else { echo '<p class="text-muted p-3">No vegan recipes yet.</p>'; } ?>
              </div>
              <button type="button" class="rn-row-arrow rn-row-arrow-right" aria-label="Scroll row right"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="rn-footer py-4 mt-auto">
      <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center small">
        <div class="rn-footer-copy">© 2026 RecipeNest</div>
        <div class="mt-2 mt-md-0">
          <a href="pages/profile.php" class="rn-footer-link me-3">Profile</a>
          <a href="#" class="rn-footer-link">Contact</a>
        </div>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.querySelectorAll('.rn-row-wrapper').forEach(function(wrapper) {
        var scrollEl = wrapper.querySelector('.rn-row-scroll');
        var leftBtn = wrapper.querySelector('.rn-row-arrow-left');
        var rightBtn = wrapper.querySelector('.rn-row-arrow-right');
        if (!scrollEl || !leftBtn || !rightBtn) return;
        var cardWrap = scrollEl.querySelector('.rn-card-wrap');
        var step = cardWrap ? cardWrap.offsetWidth + 16 : 280;
        leftBtn.addEventListener('click', function() {
          scrollEl.scrollBy({ left: -step, behavior: 'smooth' });
        });
        rightBtn.addEventListener('click', function() {
          scrollEl.scrollBy({ left: step, behavior: 'smooth' });
        });
      });
    </script>
  </body>
</html>
