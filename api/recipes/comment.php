<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';

// ── Delete comment ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $recipe_id  = (int)($_POST['recipe_id']  ?? 0);

    if ($comment_id && $recipe_id) {
        if ($is_admin) {
            $stmt = $conn->prepare('DELETE FROM comments WHERE CommentID = ?');
            $stmt->bind_param('i', $comment_id);
        } else {
            $stmt = $conn->prepare('DELETE FROM comments WHERE CommentID = ? AND UserID = ?');
            $stmt->bind_param('ii', $comment_id, $user_id);
        }
        $stmt->execute();
        $stmt->close();
    }

    $redirect = $_POST['redirect'] ?? '';
    if ($redirect === 'admin') {
        header('Location: ../../pages/admin.php?tab=comments&msg=Comment+deleted');
    } else {
        header('Location: ../../pages/recipe.php?id=' . $recipe_id . '#comments');
    }
    exit;
}

// ── Post comment ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipe_id = (int)($_POST['recipe_id'] ?? 0);
    $content   = trim($_POST['content'] ?? '');

    if (!$recipe_id || $content === '') {
        header('Location: ../../pages/recipe.php?id=' . $recipe_id . '&comment_error=empty#comments');
        exit;
    }

    if (mb_strlen($content) > 1000) {
        header('Location: ../../pages/recipe.php?id=' . $recipe_id . '&comment_error=too_long#comments');
        exit;
    }

    // Verify recipe exists
    $chk = $conn->prepare('SELECT 1 FROM recipes WHERE RecipeID = ?');
    $chk->bind_param('i', $recipe_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) { $chk->close(); header('Location: ../../index.php'); exit; }
    $chk->close();

    $stmt = $conn->prepare('INSERT INTO comments (RecipeID, UserID, CommentText, DateCreation) VALUES (?, ?, ?, NOW())');
    $stmt->bind_param('iis', $recipe_id, $user_id, $content);
    $stmt->execute();
    $stmt->close();

    header('Location: ../../pages/recipe.php?id=' . $recipe_id . '#comments');
    exit;
}

header('Location: ../../index.php');
exit;
