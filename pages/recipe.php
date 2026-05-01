<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

require_once '../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }

// Fetch recipe + author
$stmt = $conn->prepare('
    SELECT r.Title, r.Description, r.Difficulty, r.TotalCookTime, r.ActiveCookTime,
           r.ServingSize, r.CookingTips, r.DateCreation,
           r.UserID AS AuthorID, u.Name AS AuthorName
    FROM recipes r
    JOIN users u ON u.UserID = r.UserID
    WHERE r.RecipeID = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) { header('Location: ../index.php'); exit; }

// Fetch ingredients
$stmt = $conn->prepare('
    SELECT i.IngredientName, ri.Quantity
    FROM recipeingredients ri
    JOIN ingredients i ON i.IngredientID = ri.IngredientID
    WHERE ri.RecipeID = ?
    ORDER BY ri.OrderIndex
');
$stmt->bind_param('i', $id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch directions
$stmt = $conn->prepare('
    SELECT StepNumber, DirectionDescription
    FROM directions
    WHERE RecipeID = ?
    ORDER BY StepNumber
');
$stmt->bind_param('i', $id);
$stmt->execute();
$directions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch first image
$stmt = $conn->prepare('
    SELECT MediaURL FROM media
    WHERE RecipeID = ? AND MediaType = "image"
    ORDER BY OrderIndex LIMIT 1
');
$stmt->bind_param('i', $id);
$stmt->execute();
$media = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Like count
$stmt = $conn->prepare('SELECT COUNT(*) FROM likes WHERE RecipeID = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($like_count);
$stmt->fetch();
$stmt->close();

// Has current user liked it?
$user_liked = false;
if ($logged_in) {
    $stmt = $conn->prepare('SELECT 1 FROM likes WHERE RecipeID = ? AND UserID = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();
    $user_liked = $stmt->num_rows > 0;
    $stmt->close();
}

// Fetch comments with author name
$stmt = $conn->prepare('
    SELECT c.CommentID, c.CommentText AS Content, c.DateCreation, c.UserID,
           u.Name AS AuthorName, u.Username
    FROM comments c
    JOIN users u ON u.UserID = c.UserID
    WHERE c.RecipeID = ?
    ORDER BY c.DateCreation ASC
');
$stmt->bind_param('i', $id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$img   = $media['MediaURL'] ?? '../public/images/recipe_background.jpg';
$title = htmlspecialchars($recipe['Title']);

function fmt_time($t) {
    if (!$t) return null;
    [$h, $m] = explode(':', $t);
    $h = (int)$h; $m = (int)$m;
    if ($h > 0 && $m > 0) return "{$h} hr {$m} min";
    if ($h > 0) return "{$h} hr";
    return "{$m} min";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $title; ?> - RecipeNest</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../public/css/recipenest.css?v=<?php echo filemtime('../public/css/recipenest.css'); ?>">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
</head>
<body>

  <header class="rn-header">
    <nav class="navbar navbar-expand-lg">
      <div class="container">
        <a href="../index.php" class="navbar-brand rn-brand-link d-flex align-items-center">
          <span class="rn-brand-text">RecipeNest</span>
        </a>
        <button class="navbar-toggler rn-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#recipeNav" aria-controls="recipeNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="recipeNav">
          <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="search.php">Search</a></li>
            
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($logged_in): ?>
            <?php if ($_SESSION["user_role"] === "admin"): ?><li class="nav-item"><a class="nav-link rn-nav-link" href="../pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li><?php endif; ?><li class="nav-item"><a class="nav-link rn-nav-link" href="profile.php">Hi, <?php echo htmlspecialchars($user_name); ?></a></li>
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
          <article class="card shadow-sm rn-card">
            <img src="../<?php echo htmlspecialchars($img); ?>" class="card-img-top rn-thumb" alt="<?php echo $title; ?>">
            <div class="card-body p-4">

              <h1 class="h3 mb-1"><?php echo $title; ?></h1>
              <p class="text-muted small mb-3">By <a href="user.php?id=<?php echo (int)$recipe['AuthorID']; ?>" class="rn-author-link"><?php echo htmlspecialchars($recipe['AuthorName']); ?></a></p>

              <div class="d-flex flex-wrap gap-2 mb-4 rn-meta">
                <span class="rn-pill green"><i class="bi bi-heart-fill me-1"></i><?php echo $like_count; ?> likes</span>
                <?php if ($t = fmt_time($recipe['TotalCookTime'])): ?>
                <span class="rn-pill"><i class="bi bi-clock me-1"></i><?php echo $t; ?></span>
                <?php endif; ?>
                <?php if ($recipe['Difficulty']): ?>
                <span class="rn-pill orange"><?php echo htmlspecialchars($recipe['Difficulty']); ?></span>
                <?php endif; ?>
                <?php if ($recipe['ServingSize']): ?>
                <span class="rn-pill"><i class="bi bi-people me-1"></i><?php echo (int)$recipe['ServingSize']; ?> servings</span>
                <?php endif; ?>
              </div>

              <?php if ($recipe['Description']): ?>
              <p class="mb-4"><?php echo htmlspecialchars($recipe['Description']); ?></p>
              <?php endif; ?>

              <?php if ($ingredients): ?>
              <h2 class="h5">Ingredients</h2>
              <ul class="mb-4">
                <?php foreach ($ingredients as $ing): ?>
                <li>
                  <?php if ($ing['Quantity']): echo htmlspecialchars($ing['Quantity']) . ' '; endif; ?>
                  <?php echo htmlspecialchars($ing['IngredientName']); ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>

              <?php if ($directions): ?>
              <h2 class="h5">Directions</h2>
              <ol class="mb-4">
                <?php foreach ($directions as $step): ?>
                <li class="mb-2"><?php echo htmlspecialchars($step['DirectionDescription']); ?></li>
                <?php endforeach; ?>
              </ol>
              <?php endif; ?>

              <?php if ($recipe['CookingTips']): ?>
              <div class="alert alert-warning mt-2">
                <strong><i class="bi bi-lightbulb me-1"></i>Tips:</strong>
                <?php echo htmlspecialchars($recipe['CookingTips']); ?>
              </div>
              <?php endif; ?>

              <div class="d-flex gap-2 mt-3 flex-wrap">
                <a href="../index.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left me-1"></i>Back to recipes
                </a>
                <?php if ($logged_in): ?>
                <form method="POST" action="../api/recipes/like.php">
                  <input type="hidden" name="recipe_id" value="<?php echo $id; ?>">
                  <input type="hidden" name="redirect" value="../../pages/recipe.php?id=<?php echo $id; ?>">
                  <button type="submit" class="btn <?php echo $user_liked ? 'btn-rn-primary' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-heart<?php echo $user_liked ? '-fill' : ''; ?> me-1"></i>
                    <?php echo $user_liked ? 'Liked' : 'Like'; ?> (<?php echo $like_count; ?>)
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($logged_in && ($_SESSION['user_id'] == $recipe['AuthorID'] || $_SESSION['user_role'] === 'admin')): ?>
                <a href="edit_recipe.php?id=<?php echo $id; ?>" class="btn btn-rn-primary">
                  <i class="bi bi-pencil me-1"></i>Edit recipe
                </a>
                <?php endif; ?>
              </div>
            </div>
          </article>

          <!-- ── Comments ── -->
          <section class="rn-comments mt-4" id="comments">
            <h2 class="rn-comments-heading">
              <i class="bi bi-chat-dots me-2"></i>Comments
              <span class="rn-comments-count"><?php echo count($comments); ?></span>
            </h2>

            <?php if (isset($_GET['comment_error'])): ?>
            <div class="alert alert-danger mb-3">
              <?php echo $_GET['comment_error'] === 'too_long'
                ? 'Comment must be 1000 characters or fewer.'
                : 'Please write something before submitting.'; ?>
            </div>
            <?php endif; ?>

            <!-- Post form -->
            <?php if ($logged_in): ?>
            <form method="POST" action="../api/recipes/comment.php" class="rn-comment-form mb-4">
              <input type="hidden" name="recipe_id" value="<?php echo $id; ?>">
              <div class="rn-comment-form-row">
                <div class="rn-comment-avatar rn-comment-avatar--mine">
                  <?php echo strtoupper(mb_substr($user_name, 0, 1)); ?>
                </div>
                <div class="rn-comment-input-wrap">
                  <textarea
                    name="content"
                    class="rn-comment-textarea"
                    placeholder="Share your thoughts on this recipe…"
                    maxlength="1000"
                    rows="3"
                    required
                  ></textarea>
                  <div class="rn-comment-form-footer">
                    <button type="submit" class="rn-comment-submit">
                      <i class="bi bi-send me-1"></i>Post
                    </button>
                  </div>
                </div>
              </div>
            </form>
            <?php else: ?>
            <p class="rn-comments-login-prompt">
              <a href="login.php">Log in</a> to leave a comment.
            </p>
            <?php endif; ?>

            <!-- Comment list -->
            <?php if (empty($comments)): ?>
            <p class="rn-comments-empty">No comments yet — be the first!</p>
            <?php else: ?>
            <div class="rn-comment-list">
              <?php foreach ($comments as $c):
                $c_name     = htmlspecialchars($c['AuthorName'] ?: $c['Username']);
                $c_initial  = strtoupper(mb_substr($c_name, 0, 1));
                $c_content  = htmlspecialchars($c['Content']);
                $c_date     = date('M j, Y', strtotime($c['DateCreation']));
                $can_delete = $logged_in && ($c['UserID'] == $_SESSION['user_id'] || ($_SESSION['user_role'] ?? '') === 'admin');
              ?>
              <div class="rn-comment">
                <div class="rn-comment-avatar">
                  <?php echo $c_initial; ?>
                </div>
                <div class="rn-comment-body">
                  <div class="rn-comment-meta">
                    <span class="rn-comment-author"><?php echo $c_name; ?></span>
                    <span class="rn-comment-date"><?php echo $c_date; ?></span>
                    <?php if ($can_delete): ?>
                    <form method="POST" action="../api/recipes/comment.php" class="rn-comment-delete-form">
                      <input type="hidden" name="_action"    value="delete">
                      <input type="hidden" name="comment_id" value="<?php echo (int)$c['CommentID']; ?>">
                      <input type="hidden" name="recipe_id"  value="<?php echo $id; ?>">
                      <button type="submit" class="rn-comment-delete" title="Delete comment">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                  <p class="rn-comment-text"><?php echo $c_content; ?></p>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </section>

        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
