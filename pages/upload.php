<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$logged_in = isset($_SESSION["user_id"]);
$user_name = $_SESSION["user_name"] ?? "";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Upload Recipe – RecipeNest</title>
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
        <button
          class="navbar-toggler"
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
            <li class="nav-item"><a class="nav-link rn-nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link rn-nav-link" href="search.php">Search</a></li>
            
            <li class="nav-item"><a class="nav-link rn-nav-link active" aria-current="page" href="upload.php">Upload</a></li>
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
          <div class="card rn-card rn-upload-card">
            <div class="card-body p-4">
              <h1 class="rn-upload-title">Upload your recipe</h1>
              <p class="text-muted mb-4">Add an image, title, description, and details to share your recipe.</p>

              <?php if (isset($_GET["error"])): ?>
              <div class="alert alert-danger mb-4">
                <?php
                  $msgs = [
                    "missing_fields" => "Please fill in all required fields.",
                    "bad_image"      => "Invalid image file. PNG or JPG only, max 5MB.",
                    "upload_failed"  => "Image upload failed. Please try again.",
                    "server_error"   => "Something went wrong. Please try again.",
                  ];
                  echo htmlspecialchars($msgs[$_GET["error"]] ?? "An error occurred.");
                ?>
              </div>
              <?php endif; ?>

              <form class="rn-upload-form" id="uploadForm" action="../api/recipes/upload.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                  <label for="recipeImage" class="form-label fw-semibold">Recipe image</label>
                  <input type="file" class="form-control" id="recipeImage" name="recipeImage" accept="image/*" required />
                  <div class="form-text">PNG or JPG. Max 5MB.</div>
                </div>

                <div class="mb-3">
                  <label for="recipeTitle" class="form-label fw-semibold">Title</label>
                  <input type="text" class="form-control form-control-lg" id="recipeTitle" name="recipeTitle" placeholder="e.g. Blueberry Pancakes" required />
                </div>

                <div class="mb-3">
                  <label for="recipeDescription" class="form-label fw-semibold">Description</label>
                  <textarea class="form-control" id="recipeDescription" name="recipeDescription" rows="3" placeholder="A short description of your recipe." required></textarea>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Cook time (hrs)</label>
                    <input type="number" class="form-control" name="cook_hours" min="0" max="23" placeholder="0" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Cook time (mins)</label>
                    <input type="number" class="form-control" name="cook_minutes" min="0" max="59" placeholder="30" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Servings</label>
                    <input type="number" class="form-control" name="servings" min="1" placeholder="4" />
                  </div>
                  <div class="col-md-3">
                    <label for="difficulty" class="form-label fw-semibold">Difficulty</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                      <option value="">Choose…</option>
                      <option value="Easy">Easy</option>
                      <option value="Medium">Medium</option>
                      <option value="Hard">Hard</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="ingredients" class="form-label fw-semibold">Ingredients</label>
                  <textarea class="form-control font-monospace" id="ingredients" name="ingredients" rows="6"
                    placeholder="One ingredient per line, include quantity e.g.&#10;1½ cups all-purpose flour&#10;3½ tsp baking powder&#10;1 tbsp sugar" required></textarea>
                </div>

                <div class="mb-3">
                  <label for="directions" class="form-label fw-semibold">Directions</label>
                  <textarea class="form-control" id="directions" name="directions" rows="8"
                    placeholder="One step per line, in order.&#10;e.g. In a bowl, whisk together flour, baking powder, sugar, and salt." required></textarea>
                </div>

                <div class="mb-4">
                  <label for="hashtags" class="form-label fw-semibold">Tags <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="text" class="form-control" id="hashtags" name="hashtags" placeholder="e.g. vegan, breakfast, quick" />
                  <div class="form-text">Comma-separated tags.</div>
                </div>

                <div class="mb-4">
                  <label for="tips" class="form-label fw-semibold">Cooking tips <span class="text-muted fw-normal">(optional)</span></label>
                  <textarea class="form-control" id="tips" name="tips" rows="2" placeholder="Any extra tips or notes..."></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <button type="submit" class="btn btn-rn-primary">
                    <i class="bi bi-cloud-upload me-2" aria-hidden="true"></i>Publish recipe
                  </button>
                  <a href="../index.php" class="btn btn-rn-outline">Cancel</a>
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
