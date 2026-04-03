<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: ../../pages/login.php?error=missing_fields');
    exit;
}

$stmt = $conn->prepare('
    SELECT u.UserID, u.Name, u.PasswordHash,
           COALESCE(r.RoleName, "user") AS Role
    FROM users u
    LEFT JOIN userroles ur ON ur.UserID = u.UserID
    LEFT JOIN roles r ON r.RoleID = ur.RoleID
    WHERE u.Email = ?
    LIMIT 1
');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($id, $name, $password_hash, $role);
$stmt->fetch();
$stmt->close();

if (!$id || !password_verify($password, $password_hash)) {
    header('Location: ../../pages/login.php?error=invalid_credentials');
    exit;
}

$_SESSION['user_id']   = $id;
$_SESSION['user_name'] = $name;
$_SESSION['user_role'] = $role;

if ($role === 'admin') {
    header('Location: ../../pages/admin.php');
} else {
    header('Location: ../../index.php');
}
exit;
