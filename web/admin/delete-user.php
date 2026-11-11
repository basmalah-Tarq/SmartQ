<?php
require_once '../config/db.php';
require_once '../config/security.php';

$admin = require_role('admin');
require_post();
verify_csrf();

$userId = (int)($_POST['user_id'] ?? 0);
$currentAdminId = (int)$admin['id'];

if ($userId > 0 && $userId !== $currentAdminId) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('user','manager')");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

header('Location: users.php');
exit();
?>
