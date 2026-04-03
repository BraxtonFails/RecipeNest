<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../pages/login.php');
    exit;
}

$recipe_id = (int)($_POST['recipe_id'] ?? 0);
if (!$recipe_id) { header('Location: ../../pages/admin.php'); exit; }

$stmt = $conn->prepare('DELETE FROM recipes WHERE RecipeID = ?');
$stmt->bind_param('i', $recipe_id);
$stmt->execute();
$stmt->close();

header('Location: ../../pages/admin.php?msg=Recipe+deleted');
exit;
