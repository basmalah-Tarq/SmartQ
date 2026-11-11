<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/api_helper.php';

function requireUserAuth($conn)
{
    $token = getBearerToken();
    if ($token === '') {
        // Fallback for hosts that strip headers: token in the POST body (never in the URL).
        $token = trim($_POST['api_token'] ?? '');
    }
    if ($token === '') {
        jsonResponse(false, "Please log in again.", ["code" => "AUTH_REQUIRED"], 401);
    }

    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, birth_date, profile_image, role, api_token, token_expires_at
        FROM users
        WHERE api_token = ? AND role = 'user'
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !hash_equals((string)$user['api_token'], $token)) {
        jsonResponse(false, "Your session has ended. Please log in again.", ["code" => "AUTH_INVALID"], 401);
    }
    if (!empty($user['token_expires_at']) && strtotime($user['token_expires_at']) < time()) {
        jsonResponse(false, "Your session has expired. Please log in again.", ["code" => "AUTH_EXPIRED"], 401);
    }
    return $user;
}
