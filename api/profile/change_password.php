<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/profile.php');
    exit;
}

$user_id         = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm          = $_POST['confirm_password'] ?? '';

if (!$current_password || !$new_password || !$confirm) {
    header('Location: ../../pages/profile.php?error=missing_fields');
    exit;
}

if ($new_password !== $confirm) {
    header('Location: ../../pages/profile.php?error=password_mismatch');
    exit;
}

if (strlen($new_password) < 6) {
    header('Location: ../../pages/profile.php?error=password_short');
    exit;
}

$stmt = $conn->prepare('SELECT PasswordHash FROM users WHERE UserID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($hash);
$stmt->fetch();
$stmt->close();

if (!password_verify($current_password, $hash)) {
    header('Location: ../../pages/profile.php?error=wrong_password');
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE users SET PasswordHash = ? WHERE UserID = ?');
$stmt->bind_param('si', $new_hash, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: ../../pages/profile.php?success=1');
} else {
    $stmt->close();
    header('Location: ../../pages/profile.php?error=server_error');
}
exit;
