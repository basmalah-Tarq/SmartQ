<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';
require_once __DIR__ . '/../../config/auth_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);

try {
    $res = smartq_email_code_start($conn, (string)($_POST['email'] ?? ''));
    jsonResponse(true, "We sent a 6-digit code to " . $res['email'], $res);
} catch (AuthException $e) {
    // 422 invalid email, 429 too many requests, 400 email login disabled, 503 email could not be sent
    jsonResponse(false, $e->getMessage(), [], $e->getCode() ?: 400);
}
