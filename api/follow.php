<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$follower_id  = (int)$_SESSION['user_id'];
$following_id = (int)($_POST['user_id'] ?? 0);
$redirect     = $_POST['redirect'] ?? '../pages/login.php';

if (!$following_id || $follower_id === $following_id) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM follows WHERE FollowerID = ? AND FollowingID = ?');
$stmt->bind_param('ii', $follower_id, $following_id);
$stmt->execute();
$stmt->store_result();
$already = $stmt->num_rows > 0;
$stmt->close();

if ($already) {
    $stmt = $conn->prepare('DELETE FROM follows WHERE FollowerID = ? AND FollowingID = ?');
    $stmt->bind_param('ii', $follower_id, $following_id);
} else {
    $stmt = $conn->prepare('INSERT IGNORE INTO follows (FollowerID, FollowingID) VALUES (?, ?)');
    $stmt->bind_param('ii', $follower_id, $following_id);
}
$stmt->execute();
$stmt->close();

header('Location: ' . $redirect);
exit;
