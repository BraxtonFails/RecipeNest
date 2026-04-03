<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/upload.php');
    exit;
}

$user_id     = $_SESSION['user_id'];
$title       = trim($_POST['recipeTitle'] ?? '');
$description = trim($_POST['recipeDescription'] ?? '');
$difficulty  = $_POST['difficulty'] ?? null;
$servings    = (int)($_POST['servings'] ?? 0) ?: null;
$tips        = trim($_POST['tips'] ?? '') ?: null;
$ingredients = trim($_POST['ingredients'] ?? '');
$directions  = trim($_POST['directions'] ?? '');
$hashtags    = trim($_POST['hashtags'] ?? '');
$cook_hours  = (int)($_POST['cook_hours'] ?? 0);
$cook_mins   = (int)($_POST['cook_minutes'] ?? 0);

if (!$title || !$description || !$ingredients || !$directions) {
    header('Location: ../../pages/upload.php?error=missing_fields');
    exit;
}

// Build TotalCookTime as HH:MM:SS
$total_cook_time = ($cook_hours > 0 || $cook_mins > 0)
    ? sprintf('%02d:%02d:00', $cook_hours, $cook_mins)
    : null;

// Handle image upload
$image_path = null;
if (!empty($_FILES['recipeImage']['name'])) {
    $file      = $_FILES['recipeImage'];
    $allowed   = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size  = 5 * 1024 * 1024;

    if (!in_array($file['type'], $allowed) || $file['size'] > $max_size) {
        header('Location: ../../pages/upload.php?error=bad_image');
        exit;
    }

    $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename  = uniqid('recipe_') . '.' . $ext;
    $dest      = __DIR__ . '/../../public/images/Food/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        header('Location: ../../pages/upload.php?error=upload_failed');
        exit;
    }

    $image_path = 'public/images/Food/' . $filename;
}

// Insert recipe
$stmt = $conn->prepare('
    INSERT INTO recipes (UserID, Title, Description, DateCreation, TotalCookTime, ServingSize, Difficulty, CookingTips)
    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
');
$diff_val = $difficulty ?: null;
$stmt->bind_param('issssss', $user_id, $title, $description, $total_cook_time, $servings, $diff_val, $tips);

if (!$stmt->execute()) {
    $stmt->close();
    header('Location: ../../pages/upload.php?error=server_error');
    exit;
}
$recipe_id = $conn->insert_id;
$stmt->close();

// Insert image into media
if ($image_path) {
    $stmt = $conn->prepare('INSERT INTO media (RecipeID, MediaURL, MediaType, OrderIndex) VALUES (?, ?, "image", 1)');
    $stmt->bind_param('is', $recipe_id, $image_path);
    $stmt->execute();
    $stmt->close();
}

// Insert ingredients (one per line)
$ing_lines = array_filter(array_map('trim', explode("\n", $ingredients)));
$order = 1;
foreach ($ing_lines as $line) {
    if (!$line) continue;

    // Try to split quantity from ingredient name (first "word" is quantity if it starts with a digit or fraction)
    if (preg_match('/^([\d\/½¼¾⅓⅔\.\s]+(?:cup|cups|tbsp|tsp|oz|lb|g|kg|ml|l|pinch|dash|clove|cloves|piece|pieces|slice|slices|can|cans|bunch|bunches|small|medium|large)?\s*)\s+(.+)/i', $line, $m)) {
        $quantity = trim($m[1]);
        $ing_name = trim($m[2]);
    } else {
        $quantity = null;
        $ing_name = $line;
    }

    // Check if ingredient already exists
    $stmt = $conn->prepare('SELECT IngredientID FROM ingredients WHERE IngredientName = ?');
    $stmt->bind_param('s', $ing_name);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($ing_id);
        $stmt->fetch();
        $stmt->close();
    } else {
        $stmt->close();
        $stmt = $conn->prepare('INSERT INTO ingredients (IngredientName) VALUES (?)');
        $stmt->bind_param('s', $ing_name);
        $stmt->execute();
        $ing_id = $conn->insert_id;
        $stmt->close();
    }

    $stmt = $conn->prepare('INSERT IGNORE INTO recipeingredients (RecipeID, IngredientID, Quantity, OrderIndex) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iisi', $recipe_id, $ing_id, $quantity, $order);
    $stmt->execute();
    $stmt->close();
    $order++;
}

// Insert directions (one per line)
$dir_lines = array_filter(array_map('trim', explode("\n", $directions)));
$step = 1;
foreach ($dir_lines as $line) {
    if (!$line) continue;
    $stmt = $conn->prepare('INSERT INTO directions (RecipeID, StepNumber, DirectionDescription) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $recipe_id, $step, $line);
    $stmt->execute();
    $stmt->close();
    $step++;
}

// Insert hashtags
if ($hashtags) {
    $tags = array_filter(array_map('trim', explode(',', $hashtags)));
    foreach ($tags as $tag) {
        $tag = strtolower($tag);
        if (!$tag) continue;

        // Get or create hashtag
        $stmt = $conn->prepare('SELECT HashtagID FROM hashtags WHERE TagName = ?');
        $stmt->bind_param('s', $tag);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($tag_id);
            $stmt->fetch();
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO hashtags (TagName) VALUES (?)');
            $stmt->bind_param('s', $tag);
            $stmt->execute();
            $tag_id = $conn->insert_id;
            $stmt->close();
        }

        $stmt = $conn->prepare('INSERT IGNORE INTO recipehashtags (RecipeID, HashtagID) VALUES (?, ?)');
        $stmt->bind_param('ii', $recipe_id, $tag_id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ../../pages/recipe.php?id=' . $recipe_id);
exit;
