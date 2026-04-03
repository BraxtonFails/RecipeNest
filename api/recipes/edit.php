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
$recipe_id   = (int)($_POST['recipe_id'] ?? 0);
$title       = trim($_POST['recipeTitle'] ?? '');
$description = trim($_POST['recipeDescription'] ?? '');
$difficulty  = $_POST['difficulty'] ?: null;
$servings    = (int)($_POST['servings'] ?? 0) ?: null;
$tips        = trim($_POST['tips'] ?? '') ?: null;
$ingredients = trim($_POST['ingredients'] ?? '');
$directions  = trim($_POST['directions'] ?? '');
$hashtags    = trim($_POST['hashtags'] ?? '');
$cook_hours  = (int)($_POST['cook_hours'] ?? 0);
$cook_mins   = (int)($_POST['cook_minutes'] ?? 0);

if (!$recipe_id || !$title || !$description || !$ingredients || !$directions) {
    header('Location: ../../pages/edit_recipe.php?id=' . $recipe_id . '&error=missing_fields');
    exit;
}

// Verify ownership — admin can edit any recipe
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
if ($is_admin) {
    $stmt = $conn->prepare('SELECT RecipeID FROM recipes WHERE RecipeID = ?');
    $stmt->bind_param('i', $recipe_id);
} else {
    $stmt = $conn->prepare('SELECT RecipeID FROM recipes WHERE RecipeID = ? AND UserID = ?');
    $stmt->bind_param('ii', $recipe_id, $user_id);
}
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close(); header('Location: ../../index.php'); exit; }
$stmt->close();

$total_cook_time = ($cook_hours > 0 || $cook_mins > 0)
    ? sprintf('%02d:%02d:00', $cook_hours, $cook_mins)
    : null;

// Handle image replacement
if (!empty($_FILES['recipeImage']['name'])) {
    $file     = $_FILES['recipeImage'];
    $allowed  = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('recipe_') . '.' . $ext;
        $dest     = __DIR__ . '/../../public/images/Food/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $image_path = 'public/images/Food/' . $filename;
            // Delete old media and insert new
            $stmt = $conn->prepare('DELETE FROM media WHERE RecipeID = ?');
            $stmt->bind_param('i', $recipe_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO media (RecipeID, MediaURL, MediaType, OrderIndex) VALUES (?, ?, "image", 1)');
            $stmt->bind_param('is', $recipe_id, $image_path);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Update recipe
$stmt = $conn->prepare('
    UPDATE recipes SET Title=?, Description=?, TotalCookTime=?, ServingSize=?, Difficulty=?, CookingTips=?
    WHERE RecipeID=?
');
$stmt->bind_param('ssssssi', $title, $description, $total_cook_time, $servings, $difficulty, $tips, $recipe_id);
if (!$stmt->execute()) {
    $stmt->close();
    header('Location: ../../pages/edit_recipe.php?id=' . $recipe_id . '&error=server_error');
    exit;
}
$stmt->close();

// Replace ingredients
$stmt = $conn->prepare('DELETE FROM recipeingredients WHERE RecipeID = ?');
$stmt->bind_param('i', $recipe_id);
$stmt->execute();
$stmt->close();

$ing_lines = array_filter(array_map('trim', explode("\n", $ingredients)));
$order = 1;
foreach ($ing_lines as $line) {
    if (!$line) continue;
    if (preg_match('/^([\d\/½¼¾⅓⅔\.\s]+(?:cup|cups|tbsp|tsp|oz|lb|g|kg|ml|l|pinch|dash|clove|cloves|piece|pieces|slice|slices|can|cans|bunch|bunches|small|medium|large)?\s*)\s+(.+)/i', $line, $m)) {
        $quantity = trim($m[1]);
        $ing_name = trim($m[2]);
    } else {
        $quantity = null;
        $ing_name = $line;
    }

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

// Replace directions
$stmt = $conn->prepare('DELETE FROM directions WHERE RecipeID = ?');
$stmt->bind_param('i', $recipe_id);
$stmt->execute();
$stmt->close();

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

// Replace hashtags
$stmt = $conn->prepare('DELETE FROM recipehashtags WHERE RecipeID = ?');
$stmt->bind_param('i', $recipe_id);
$stmt->execute();
$stmt->close();

if ($hashtags) {
    $tags = array_filter(array_map('trim', explode(',', $hashtags)));
    foreach ($tags as $tag) {
        $tag = strtolower($tag);
        if (!$tag) continue;
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

if (($_POST['from'] ?? '') === 'admin') {
    header('Location: ../../pages/admin.php?msg=Recipe+updated');
} else {
    header('Location: ../../pages/recipe.php?id=' . $recipe_id);
}
exit;
