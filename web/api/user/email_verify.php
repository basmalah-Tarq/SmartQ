<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';
require_once __DIR__ . '/../../config/auth_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);

try {
    $user = smartq_email_code_verify($conn, (string)($_POST['email'] ?? ''), (string)($_POST['code'] ?? ''), (string)($_POST['full_name'] ?? ''));
    if ($user['role'] !== 'user') {
        jsonResponse(false, "This app is for customers. Managers and admins please use the website.", [], 403);
    }
    $token = issueApiToken($conn, (int)$user['id']);
    jsonResponse(true, "Welcome!", [
        "user" => userPayload($user),
        "api_token" => $token,
        "is_new_user" => !empty($user['is_new_user'])
    ]);
} catch (AuthException $e) {
    jsonResponse(false, $e->getMessage(), [], 401);
}
