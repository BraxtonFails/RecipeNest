<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/register.php');
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

// Basic validation
if (!$first_name || !$last_name || !$email || !$password) {
    header('Location: ../../pages/register.php?error=missing_fields');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../pages/register.php?error=invalid_email');
    exit;
}

if ($password !== $confirm) {
    header('Location: ../../pages/register.php?error=password_mismatch');
    exit;
}

if (strlen($password) < 6) {
    header('Location: ../../pages/register.php?error=password_short');
    exit;
}

// Check if email already exists
$stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    header('Location: ../../pages/register.php?error=email_taken');
    exit;
}
$stmt->close();

// Insert new user
$name          = $first_name . ' ' . $last_name;
$username      = strtolower($first_name . '_' . $last_name);
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$created_at    = date('Y-m-d H:i:s');

$stmt = $conn->prepare('INSERT INTO users (Username, Email, Name, PasswordHash, DateCreation) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $username, $email, $name, $password_hash, $created_at);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;
    $stmt->close();

    $_SESSION['user_id']   = $user_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'user';

    header('Location: ../../index.php');
    exit;
} else {
    $stmt->close();
    header('Location: ../../pages/register.php?error=server_error');
    exit;
}
