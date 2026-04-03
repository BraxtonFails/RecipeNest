<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../pages/login.php');
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
if (!$user_id) { header('Location: ../../pages/admin.php'); exit; }

// Prevent deleting yourself
if ($user_id === (int)$_SESSION['user_id']) {
    header('Location: ../../pages/admin.php?msg=Cannot+delete+your+own+account');
    exit;
}

$stmt = $conn->prepare('DELETE FROM users WHERE UserID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->close();

header('Location: ../../pages/admin.php?msg=User+deleted');
exit;
