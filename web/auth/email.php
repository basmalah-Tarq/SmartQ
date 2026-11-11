<?php
/**
 * Website "Continue with email" (passwordless one-time code) — JSON endpoint used by login.php / signup.php.
 *   POST action=start  email=...                    -> sends a 6-digit code
 *   POST action=verify email=... code=... [full_name] -> signs in (creates a customer account for new emails)
 * Both require the page's csrf_token.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth_service.php';

header('Content-Type: application/json; charset=utf-8');

function auth_json(bool $ok, string $message, array $data = [], int $http = 200): void
{
    http_response_code($http);
    echo json_encode(['status' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') auth_json(false, 'Method not allowed', [], 405);
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    auth_json(false, 'Your session expired. Please refresh the page and try again.', [], 403);
}

$action = $_POST['action'] ?? '';
try {
    if ($action === 'start') {
        $res = smartq_email_code_start($conn, (string)($_POST['email'] ?? ''));
        auth_json(true, 'We sent a 6-digit code to ' . $res['email'], $res);
    }
    if ($action === 'verify') {
        $user = smartq_email_code_verify($conn, (string)($_POST['email'] ?? ''), (string)($_POST['code'] ?? ''), (string)($_POST['full_name'] ?? ''));
        smartq_web_login($conn, (int)$user['id']);
        $next = $_SESSION['user']['role'] === 'user' ? smartq_safe_next($_POST['next'] ?? '') : '';
        auth_json(true, 'Welcome!', ['redirect' => $next !== '' ? $next : smartq_role_home($_SESSION['user']['role']), 'is_new_user' => !empty($user['is_new_user'])]);
    }
    auth_json(false, 'Unknown action', [], 400);
} catch (AuthException $e) {
    auth_json(false, $e->getMessage(), [], 400);
}
