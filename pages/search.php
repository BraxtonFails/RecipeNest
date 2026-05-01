<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

require_once '../config/db.php';

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'recipes';
if (!in_array($type, ['recipes', 'hashtags', 'users'])) $type = 'recipes';

$results         = [];
$hashtag_recipes = [];
$user_recipes    = []; // keyed by UserID
$suggestions     = []; // "maybe you were looking for" cross-category

$recipe_media_sql = "
    SELECT r.RecipeID, r.Title, r.Difficulty, r.TotalCookTime,
           m.MediaURL, COUNT(DISTINCT l.UserID) AS LikeCount
    FROM recipes r
    LEFT JOIN media m ON m.RecipeID = r.RecipeID
        AND m.MediaType = 'image'
        AND m.OrderIndex = (
            SELECT MIN(m2.OrderIndex) FROM media m2
            WHERE m2.RecipeID = r.RecipeID AND m2.MediaType = 'image'
        )
    LEFT JOIN likes l ON l.RecipeID = r.RecipeID
";

if ($q !== '') {
    $like = '%' . $q . '%';

    if ($type === 'recipes') {
        $stmt = $conn->prepare("
            SELECT DISTINCT r.RecipeID, r.Title, r.Difficulty, r.TotalCookTime,
                   m.MediaURL, COUNT(DISTINCT l.UserID) AS LikeCount
            FROM recipes r
            LEFT JOIN recipeingredients ri ON ri.RecipeID = r.RecipeID
            LEFT JOIN ingredients i ON i.IngredientID = ri.IngredientID
            LEFT JOIN media m ON m.RecipeID = r.RecipeID
                AND m.MediaType = 'image'
                AND m.OrderIndex = (
                    SELECT MIN(m2.OrderIndex) FROM media m2
                    WHERE m2.RecipeID = r.RecipeID AND m2.MediaType = 'image'
                )
            LEFT JOIN likes l ON l.RecipeID = r.RecipeID
            WHERE r.Title LIKE ? OR i.IngredientName LIKE ?
            GROUP BY r.RecipeID
            ORDER BY LikeCount DESC, r.DateCreation DESC
            LIMIT 30
        ");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($results)) {
            // Suggest hashtags
            $s = $conn->prepare("SELECT TagName FROM hashtags WHERE TagName LIKE ? ORDER BY TagName LIMIT 4");
            $s->bind_param('s', $like); $s->execute();
            $suggestions['hashtags'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
            // Suggest users
            $s = $conn->prepare("SELECT Username, Name FROM users WHERE Username LIKE ? OR Name LIKE ? LIMIT 4");
            $s->bind_param('ss', $like, $like); $s->execute();
            $suggestions['users'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
        }

    } elseif ($type === 'hashtags') {
        $stmt = $conn->prepare("
            SELECT h.HashtagID, h.TagName, COUNT(DISTINCT rh.RecipeID) AS RecipeCount
            FROM hashtags h
            LEFT JOIN recipehashtags rh ON rh.HashtagID = h.HashtagID
            WHERE h.TagName LIKE ?
            GROUP BY h.HashtagID
            ORDER BY RecipeCount DESC
            LIMIT 30
        ");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // If exactly one hashtag matches, load its recipes
        if (count($results) === 1) {
            $hid  = (int)$results[0]['HashtagID'];
            $stmt2 = $conn->prepare($recipe_media_sql . "
                JOIN recipehashtags rh ON rh.RecipeID = r.RecipeID AND rh.HashtagID = ?
                GROUP BY r.RecipeID ORDER BY LikeCount DESC LIMIT 12
            ");
            $stmt2->bind_param('i', $hid);
            $stmt2->execute();
            $hashtag_recipes = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        }

        if (empty($results)) {
            $s = $conn->prepare("
                SELECT DISTINCT r.RecipeID, r.Title FROM recipes r
                LEFT JOIN recipeingredients ri ON ri.RecipeID = r.RecipeID
                LEFT JOIN ingredients i ON i.IngredientID = ri.IngredientID
                WHERE r.Title LIKE ? OR i.IngredientName LIKE ? LIMIT 4
            ");
            $s->bind_param('ss', $like, $like); $s->execute();
            $suggestions['recipes'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
            $s = $conn->prepare("SELECT Username, Name FROM users WHERE Username LIKE ? OR Name LIKE ? LIMIT 4");
            $s->bind_param('ss', $like, $like); $s->execute();
            $suggestions['users'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
        }

    } elseif ($type === 'users') {
        $stmt = $conn->prepare("
            SELECT UserID, Username, Name, Bio, ProfilePictureURL
            FROM users
            WHERE Username LIKE ? OR Name LIKE ?
            ORDER BY Username ASC
            LIMIT 30
        ");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch recipes for each found user
        foreach ($results as $u) {
            $uid  = (int)$u['UserID'];
            $stmt2 = $conn->prepare($recipe_media_sql . "
                WHERE r.UserID = ?
                GROUP BY r.RecipeID ORDER BY r.DateCreation DESC LIMIT 6
            ");
            $stmt2->bind_param('i', $uid);
            $stmt2->execute();
            $user_recipes[$uid] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        }

        if (empty($results)) {
            $s = $conn->prepare("
                SELECT DISTINCT r.RecipeID, r.Title FROM recipes r
                LEFT JOIN recipeingredients ri ON ri.RecipeID = r.RecipeID
                LEFT JOIN ingredients i ON i.IngredientID = ri.IngredientID
                WHERE r.Title LIKE ? OR i.IngredientName LIKE ? LIMIT 4
            ");
            $s->bind_param('ss', $like, $like); $s->execute();
            $suggestions['recipes'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
            $s = $conn->prepare("SELECT TagName FROM hashtags WHERE TagName LIKE ? ORDER BY TagName LIMIT 4");
            $s->bind_param('s', $like); $s->execute();
            $suggestions['hashtags'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
        }
    }
}

function avatar_color($id) {
    $p = ['#7dbf77','#e6845a','#5a8fe6','#9b6de6','#e6b05a','#5ab8e6','#e6527a','#52b8a0'];
    return $p[(int)$id % count($p)];
}

function fmt_time($t) {
    if (!$t) return null;
    [$h, $m] = explode(':', $t);
    $h = (int)$h; $m = (int)$m;
    if ($h > 0 && $m > 0) return "{$h} hr {$m} min";
    if ($h > 0) return "{$h} hr";
    return "{$m} min";
}

// Prefix DB-stored path with ../ for use in pages/ HTML
function media_url($raw, $fallback = '../public/images/recipe_background.jpg') {
    return $raw ? '../' . htmlspecialchars($raw) : $fallback;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Search – RecipeNest</title>
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
        <button class="navbar-toggler rn-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#searchNav" aria-controls="searchNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="searchNav">
          <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link active" href="search.php">Search</a></li>
            
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($logged_in): ?>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li>
            <?php endif; ?>
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
    <div class="container" style="max-width:860px;">

      <!-- Search form -->
      <div class="rn-search-hero mb-4">
        <h1 class="rn-search-heading mb-3">Search RecipeNest</h1>
        <form method="GET" action="search.php" class="rn-search-form">
          <div class="rn-search-bar">
            <i class="bi bi-search rn-search-icon"></i>
            <input
              type="text"
              name="q"
              class="rn-search-input"
              placeholder="Search recipes, ingredients, hashtags, or users…"
              value="<?php echo htmlspecialchars($q); ?>"
              autofocus
            />
            <button type="submit" class="rn-search-btn">Search</button>
          </div>
          <div class="rn-type-tabs mt-3">
            <a href="search.php?q=<?php echo urlencode($q); ?>&type=recipes"
               class="rn-type-tab <?php echo $type === 'recipes' ? 'active' : ''; ?>">
              <i class="bi bi-journal-text me-1"></i>Recipes &amp; Ingredients
            </a>
            <a href="search.php?q=<?php echo urlencode($q); ?>&type=hashtags"
               class="rn-type-tab <?php echo $type === 'hashtags' ? 'active' : ''; ?>">
              <i class="bi bi-hash me-1"></i>Hashtags
            </a>
            <a href="search.php?q=<?php echo urlencode($q); ?>&type=users"
               class="rn-type-tab <?php echo $type === 'users' ? 'active' : ''; ?>">
              <i class="bi bi-person me-1"></i>Users
            </a>
          </div>
        </form>
      </div>

      <?php if ($q === ''): ?>
      <!-- Initial empty state -->
      <div class="rn-search-empty">
        <i class="bi bi-search rn-empty-icon"></i>
        <p>Enter a keyword to find recipes by title or ingredient, browse hashtags, or discover users.</p>
      </div>

      <?php elseif ($type === 'recipes'): ?>
      <!-- ── Recipe results ── -->
      <p class="rn-result-count"><?php echo count($results); ?> result<?php echo count($results) !== 1 ? 's' : ''; ?> for <strong>"<?php echo htmlspecialchars($q); ?>"</strong></p>
      <?php if (empty($results)): ?>
        <div class="rn-search-empty">
          <i class="bi bi-journal-x rn-empty-icon"></i>
          <p>No recipes or ingredients matched <strong>"<?php echo htmlspecialchars($q); ?>"</strong>.</p>
        </div>
        <?php if (!empty($suggestions['hashtags']) || !empty($suggestions['users'])): ?>
        <div class="rn-suggestions">
          <p class="rn-suggestions-label"><i class="bi bi-lightbulb me-1"></i>Maybe you were looking for…</p>
          <?php if (!empty($suggestions['hashtags'])): ?>
          <div class="mb-3">
            <span class="rn-sug-cat">Hashtags</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['hashtags'] as $h): ?>
              <a href="search.php?q=<?php echo urlencode($h['TagName']); ?>&type=hashtags" class="rn-sug-chip rn-sug-chip--hash">
                <i class="bi bi-hash"></i><?php echo htmlspecialchars($h['TagName']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($suggestions['users'])): ?>
          <div>
            <span class="rn-sug-cat">Users</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['users'] as $u): ?>
              <a href="search.php?q=<?php echo urlencode($u['Username']); ?>&type=users" class="rn-sug-chip rn-sug-chip--user">
                <i class="bi bi-person"></i><?php echo htmlspecialchars($u['Name'] ?: $u['Username']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="rn-search-grid">
          <?php foreach ($results as $r):
            $id    = (int)$r['RecipeID'];
            $title = htmlspecialchars($r['Title']);
            $diff  = htmlspecialchars($r['Difficulty'] ?? '');
            $time  = fmt_time($r['TotalCookTime']);
            $likes = (int)$r['LikeCount'];
          ?>
          <a href="recipe.php?id=<?php echo $id; ?>" class="rn-search-card">
            <img src="<?php echo media_url($r['MediaURL']); ?>" alt="<?php echo $title; ?>" class="rn-search-card-img" />
            <div class="rn-search-card-body">
              <h3 class="rn-search-card-title"><?php echo $title; ?></h3>
              <div class="rn-meta">
                <span class="rn-pill green"><i class="bi bi-heart-fill me-1"></i><?php echo $likes; ?></span>
                <?php if ($time): ?><span class="rn-pill"><i class="bi bi-clock me-1"></i><?php echo $time; ?></span><?php endif; ?>
                <?php if ($diff): ?><span class="rn-pill orange"><?php echo $diff; ?></span><?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php elseif ($type === 'hashtags'): ?>
      <!-- ── Hashtag results ── -->
      <p class="rn-result-count"><?php echo count($results); ?> hashtag<?php echo count($results) !== 1 ? 's' : ''; ?> matching <strong>"<?php echo htmlspecialchars($q); ?>"</strong></p>
      <?php if (empty($results)): ?>
        <div class="rn-search-empty">
          <i class="bi bi-hash rn-empty-icon"></i>
          <p>No hashtags matched <strong>"<?php echo htmlspecialchars($q); ?>"</strong>.</p>
        </div>
        <?php if (!empty($suggestions['recipes']) || !empty($suggestions['users'])): ?>
        <div class="rn-suggestions">
          <p class="rn-suggestions-label"><i class="bi bi-lightbulb me-1"></i>Maybe you were looking for…</p>
          <?php if (!empty($suggestions['recipes'])): ?>
          <div class="mb-3">
            <span class="rn-sug-cat">Recipes</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['recipes'] as $r): ?>
              <a href="search.php?q=<?php echo urlencode($r['Title']); ?>&type=recipes" class="rn-sug-chip rn-sug-chip--recipe">
                <i class="bi bi-journal-text"></i><?php echo htmlspecialchars($r['Title']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($suggestions['users'])): ?>
          <div>
            <span class="rn-sug-cat">Users</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['users'] as $u): ?>
              <a href="search.php?q=<?php echo urlencode($u['Username']); ?>&type=users" class="rn-sug-chip rn-sug-chip--user">
                <i class="bi bi-person"></i><?php echo htmlspecialchars($u['Name'] ?: $u['Username']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="rn-hashtag-list mb-4">
          <?php foreach ($results as $h): ?>
          <a href="search.php?q=<?php echo urlencode($h['TagName']); ?>&type=hashtags" class="rn-hashtag-chip">
            <i class="bi bi-hash"></i><?php echo htmlspecialchars($h['TagName']); ?>
            <span class="rn-hashtag-count"><?php echo (int)$h['RecipeCount']; ?> recipe<?php echo $h['RecipeCount'] !== 1 ? 's' : ''; ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($hashtag_recipes)): ?>
        <h2 class="rn-section-label mb-3">Recipes tagged <span class="rn-pill pink">#<?php echo htmlspecialchars($results[0]['TagName']); ?></span></h2>
        <div class="rn-search-grid">
          <?php foreach ($hashtag_recipes as $r):
            $id    = (int)$r['RecipeID'];
            $title = htmlspecialchars($r['Title']);
            $diff  = htmlspecialchars($r['Difficulty'] ?? '');
            $time  = fmt_time($r['TotalCookTime']);
            $likes = (int)$r['LikeCount'];
          ?>
          <a href="recipe.php?id=<?php echo $id; ?>" class="rn-search-card">
            <img src="<?php echo media_url($r['MediaURL']); ?>" alt="<?php echo $title; ?>" class="rn-search-card-img" />
            <div class="rn-search-card-body">
              <h3 class="rn-search-card-title"><?php echo $title; ?></h3>
              <div class="rn-meta">
                <span class="rn-pill green"><i class="bi bi-heart-fill me-1"></i><?php echo $likes; ?></span>
                <?php if ($time): ?><span class="rn-pill"><i class="bi bi-clock me-1"></i><?php echo $time; ?></span><?php endif; ?>
                <?php if ($diff): ?><span class="rn-pill orange"><?php echo $diff; ?></span><?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php elseif ($type === 'users'): ?>
      <!-- ── User results ── -->
      <p class="rn-result-count"><?php echo count($results); ?> user<?php echo count($results) !== 1 ? 's' : ''; ?> matching <strong>"<?php echo htmlspecialchars($q); ?>"</strong></p>
      <?php if (empty($results)): ?>
        <div class="rn-search-empty">
          <i class="bi bi-person-x rn-empty-icon"></i>
          <p>No users matched <strong>"<?php echo htmlspecialchars($q); ?>"</strong>.</p>
        </div>
        <?php if (!empty($suggestions['recipes']) || !empty($suggestions['hashtags'])): ?>
        <div class="rn-suggestions">
          <p class="rn-suggestions-label"><i class="bi bi-lightbulb me-1"></i>Maybe you were looking for…</p>
          <?php if (!empty($suggestions['recipes'])): ?>
          <div class="mb-3">
            <span class="rn-sug-cat">Recipes</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['recipes'] as $r): ?>
              <a href="search.php?q=<?php echo urlencode($r['Title']); ?>&type=recipes" class="rn-sug-chip rn-sug-chip--recipe">
                <i class="bi bi-journal-text"></i><?php echo htmlspecialchars($r['Title']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($suggestions['hashtags'])): ?>
          <div>
            <span class="rn-sug-cat">Hashtags</span>
            <div class="rn-sug-list">
              <?php foreach ($suggestions['hashtags'] as $h): ?>
              <a href="search.php?q=<?php echo urlencode($h['TagName']); ?>&type=hashtags" class="rn-sug-chip rn-sug-chip--hash">
                <i class="bi bi-hash"></i><?php echo htmlspecialchars($h['TagName']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="rn-user-list">
          <?php foreach ($results as $u):
            $uid      = (int)$u['UserID'];
            $avatar   = $u['ProfilePictureURL'] ? htmlspecialchars($u['ProfilePictureURL']) : null;
            $username = htmlspecialchars($u['Username'] ?? '');
            $name     = htmlspecialchars($u['Name'] ?? '');
            $bio      = htmlspecialchars($u['Bio'] ?? '');
            $initials = strtoupper(mb_substr($name ?: $username, 0, 1));
            $recipes  = $user_recipes[$uid] ?? [];
          ?>
          <div class="rn-user-block">
            <a href="user.php?id=<?php echo $uid; ?>" class="rn-user-card rn-user-card--link">
              <div class="rn-user-avatar" style="background:<?php echo avatar_color($uid); ?>">
                <?php if ($avatar): ?>
                  <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>" />
                <?php else: ?>
                  <span class="rn-user-initials"><?php echo $initials; ?></span>
                <?php endif; ?>
              </div>
              <div class="rn-user-info">
                <div class="rn-user-name"><?php echo $name ?: $username; ?></div>
                <?php if ($name): ?><div class="rn-user-username">@<?php echo $username; ?></div><?php endif; ?>
                <?php if ($bio): ?><div class="rn-user-bio"><?php echo $bio; ?></div><?php endif; ?>
              </div>
            </a>

            <?php if (!empty($recipes)): ?>
            <div class="rn-user-recipes">
              <p class="rn-user-recipes-label"><?php echo count($recipes); ?> recipe<?php echo count($recipes) !== 1 ? 's' : ''; ?> by <?php echo $name ?: $username; ?></p>
              <div class="rn-user-recipes-grid">
                <?php foreach ($recipes as $r):
                  $rid   = (int)$r['RecipeID'];
                  $rtitle = htmlspecialchars($r['Title']);
                  $rdiff = htmlspecialchars($r['Difficulty'] ?? '');
                  $rtime = fmt_time($r['TotalCookTime']);
                  $rlikes = (int)$r['LikeCount'];
                ?>
                <a href="recipe.php?id=<?php echo $rid; ?>" class="rn-search-card rn-search-card--sm">
                  <img src="<?php echo media_url($r['MediaURL']); ?>" alt="<?php echo $rtitle; ?>" class="rn-search-card-img" />
                  <div class="rn-search-card-body">
                    <h4 class="rn-search-card-title"><?php echo $rtitle; ?></h4>
                    <div class="rn-meta">
                      <span class="rn-pill green"><i class="bi bi-heart-fill me-1"></i><?php echo $rlikes; ?></span>
                      <?php if ($rtime): ?><span class="rn-pill"><i class="bi bi-clock me-1"></i><?php echo $rtime; ?></span><?php endif; ?>
                      <?php if ($rdiff): ?><span class="rn-pill orange"><?php echo $rdiff; ?></span><?php endif; ?>
                    </div>
                  </div>
                </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php else: ?>
            <p class="rn-user-no-recipes">This user hasn't posted any recipes yet.</p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php endif; ?>

    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
