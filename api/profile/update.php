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

$user_id  = $_SESSION['user_id'];
$name     = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$bio      = trim($_POST['bio'] ?? '');

if (!$name || !$username || !$email) {
    header('Location: ../../pages/profile.php?error=missing_fields');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../pages/profile.php?error=invalid_email');
    exit;
}

// Check if email or username is taken by another user
$stmt = $conn->prepare('SELECT UserID FROM users WHERE (Email = ? OR Username = ?) AND UserID != ?');
$stmt->bind_param('ssi', $email, $username, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    header('Location: ../../pages/profile.php?error=taken');
    exit;
}
$stmt->close();

$stmt = $conn->prepare('UPDATE users SET Name = ?, Username = ?, Email = ?, Bio = ? WHERE UserID = ?');
$stmt->bind_param('ssssi', $name, $username, $email, $bio, $user_id);

if ($stmt->execute()) {
    $_SESSION['user_name'] = $name;
    $stmt->close();
    header('Location: ../../pages/profile.php?success=1');
} else {
    $stmt->close();
    header('Location: ../../pages/profile.php?error=server_error');
}
exit;
