<?php
require_once __DIR__ . '/../helpers/auth_user.php';

$user = requireUserAuth($conn);
$stmt = $conn->prepare("UPDATE users SET api_token = NULL, token_expires_at = NULL WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
jsonResponse(true, "Logged out");
