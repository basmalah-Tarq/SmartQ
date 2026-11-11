<?php
/**
 * Website "Continue with Google": receives the ID token from Google Identity Services (credential response),
 * verifies it on the server and signs the user in. POST credential=..., csrf_token=...
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth_service.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit; }
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Your session expired. Please refresh the page.']);
    exit;
}

try {
    $user = smartq_google_verify($conn, (string)($_POST['credential'] ?? ''));
    smartq_web_login($conn, (int)$user['id']);
    echo json_encode(['status' => true, 'message' => 'Welcome!', 'data' => ['redirect' => smartq_role_home($_SESSION['user']['role'])]]);
} catch (AuthException $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage(), 'data' => []]);
}
