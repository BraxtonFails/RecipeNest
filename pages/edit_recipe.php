<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

$id      = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

if (!$id) { header('Location: ../index.php'); exit; }

// Fetch recipe — admin can edit any, others only their own
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
if ($is_admin) {
    $stmt = $conn->prepare('SELECT * FROM recipes WHERE RecipeID = ?');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare('SELECT * FROM recipes WHERE RecipeID = ? AND UserID = ?');
    $stmt->bind_param('ii', $id, $user_id);
}
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) { header('Location: ../index.php'); exit; }

// Fetch ingredients
$stmt = $conn->prepare('
    SELECT i.IngredientName, ri.Quantity
    FROM recipeingredients ri
    JOIN ingredients i ON i.IngredientID = ri.IngredientID
    WHERE ri.RecipeID = ? ORDER BY ri.OrderIndex
');
$stmt->bind_param('i', $id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch directions
$stmt = $conn->prepare('SELECT DirectionDescription FROM directions WHERE RecipeID = ? ORDER BY StepNumber');
$stmt->bind_param('i', $id);
$stmt->execute();
$directions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch hashtags
$stmt = $conn->prepare('
    SELECT h.TagName FROM recipehashtags rh
    JOIN hashtags h ON h.HashtagID = rh.HashtagID
    WHERE rh.RecipeID = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pre-fill values
$ing_text  = implode("\n", array_map(fn($r) => trim(($r['Quantity'] ? $r['Quantity'] . ' ' : '') . $r['IngredientName']), $ingredients));
$dir_text  = implode("\n", array_map(fn($r) => $r['DirectionDescription'], $directions));
$tag_text  = implode(', ', array_map(fn($r) => $r['TagName'], $tags));
$cook_time = $recipe['TotalCookTime'] ?? '00:00:00';
[$cook_h, $cook_m] = explode(':', $cook_time);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit – <?php echo htmlspecialchars($recipe['Title']); ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../public/css/recipenest.css" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
</head>
<body>
  <header class="rn-header">
    <nav class="navbar navbar-expand-lg">
      <div class="container">
        <a href="../index.php" class="navbar-brand rn-brand-link d-flex align-items-center"><span class="rn-brand-text">RecipeNest</span></a>
        <button class="navbar-toggler rn-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto gap-1 gap-lg-2">
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="upload.php">Upload</a></li>
            <?php if ($_SESSION["user_role"] === "admin"): ?><li class="nav-item"><a class="nav-link rn-nav-link" href="../pages/admin.php"><i class="bi bi-shield-lock me-1"></i>Dashboard</a></li><?php endif; ?><li class="nav-item"><span class="nav-link rn-nav-link">Hi, <?php echo htmlspecialchars($user_name); ?></span></li>
            <li class="nav-item"><a class="nav-link rn-nav-link rn-nav-link-cta" href="../api/auth/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main class="py-5">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card rn-card rn-upload-card">
            <div class="card-body p-4">
              <h1 class="rn-upload-title">Edit recipe</h1>

              <?php if (isset($_GET['error'])): ?>
              <div class="alert alert-danger mb-4">
                <?php
                  $msgs = [
                    "missing_fields" => "Please fill in all required fields.",
                    "server_error"   => "Something went wrong. Please try again.",
                  ];
                  echo htmlspecialchars($msgs[$_GET['error']] ?? "An error occurred.");
                ?>
              </div>
              <?php endif; ?>

              <form action="../api/recipes/edit.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="recipe_id" value="<?php echo $id; ?>">
                <input type="hidden" name="from" value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>">

                <div class="mb-4">
                  <label class="form-label fw-semibold">Replace image <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="file" class="form-control" name="recipeImage" accept="image/*" />
                  <div class="form-text">Leave blank to keep the current image.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Title</label>
                  <input type="text" class="form-control form-control-lg" name="recipeTitle" value="<?php echo htmlspecialchars($recipe['Title']); ?>" required />
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea class="form-control" name="recipeDescription" rows="3" required><?php echo htmlspecialchars($recipe['Description']); ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Cook time (hrs)</label>
                    <input type="number" class="form-control" name="cook_hours" min="0" max="23" value="<?php echo (int)$cook_h; ?>" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Cook time (mins)</label>
                    <input type="number" class="form-control" name="cook_minutes" min="0" max="59" value="<?php echo (int)$cook_m; ?>" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Servings</label>
                    <input type="number" class="form-control" name="servings" min="1" value="<?php echo htmlspecialchars($recipe['ServingSize'] ?? ''); ?>" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Difficulty</label>
                    <select class="form-select" name="difficulty">
                      <option value="">Choose…</option>
                      <?php foreach (['Easy','Medium','Hard'] as $d): ?>
                      <option value="<?php echo $d; ?>" <?php echo $recipe['Difficulty'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Ingredients</label>
                  <textarea class="form-control font-monospace" name="ingredients" rows="6" required><?php echo htmlspecialchars($ing_text); ?></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Directions</label>
                  <textarea class="form-control" name="directions" rows="8" required><?php echo htmlspecialchars($dir_text); ?></textarea>
                </div>

                <div class="mb-4">
                  <label class="form-label fw-semibold">Tags <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="text" class="form-control" name="hashtags" value="<?php echo htmlspecialchars($tag_text); ?>" placeholder="e.g. vegan, breakfast, quick" />
                </div>

                <div class="mb-4">
                  <label class="form-label fw-semibold">Cooking tips <span class="text-muted fw-normal">(optional)</span></label>
                  <textarea class="form-control" name="tips" rows="2"><?php echo htmlspecialchars($recipe['CookingTips'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-rn-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                  <a href="recipe.php?id=<?php echo $id; ?>" class="btn btn-rn-outline">Cancel</a>
                </div>
              </form>

            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
