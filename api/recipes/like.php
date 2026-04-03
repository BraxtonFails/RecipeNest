<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$recipe_id = (int)($_POST['recipe_id'] ?? 0);
$redirect  = $_POST['redirect'] ?? '../../pages/recipe.php?id=' . $recipe_id;

if (!$recipe_id) {
    header('Location: ' . $redirect);
    exit;
}

// Check if already liked
$stmt = $conn->prepare('SELECT 1 FROM likes WHERE RecipeID = ? AND UserID = ?');
$stmt->bind_param('ii', $recipe_id, $user_id);
$stmt->execute();
$stmt->store_result();
$already_liked = $stmt->num_rows > 0;
$stmt->close();

if ($already_liked) {
    $stmt = $conn->prepare('DELETE FROM likes WHERE RecipeID = ? AND UserID = ?');
} else {
    $stmt = $conn->prepare('INSERT IGNORE INTO likes (RecipeID, UserID) VALUES (?, ?)');
}
$stmt->bind_param('ii', $recipe_id, $user_id);
$stmt->execute();
$stmt->close();

header('Location: ' . $redirect);
exit;
