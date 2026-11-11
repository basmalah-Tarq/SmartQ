<?php
/**
 * Shared security helpers for SmartQ.
 * Include this file after config/db.php or before protected actions.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_role(string $role): array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== $role) {
        header('Location: ../login.php');
        exit();
    }
    return $_SESSION['user'];
}

/**
 * Guest browsing: returns the logged-in customer, or null for a visitor without an account.
 * Managers/admins who open customer pages are sent back to their own workspace.
 */
function current_user_or_guest(): ?array
{
    $u = $_SESSION['user'] ?? null;
    if (!is_array($u)) return null;
    $role = $u['role'] ?? '';
    if ($role === 'user') return $u;
    header('Location: ../' . ($role === 'admin' ? 'admin' : 'manager') . '/index.php');
    exit();
}

/** Only allow returning to customer pages after login (prevents open redirects). */
function smartq_safe_next(?string $next): string
{
    $next = trim((string)$next);
    return preg_match('#^user/[a-z0-9_-]+\.php(\?[a-z0-9_=&%-]*)?$#i', $next) ? $next : '';
}

/** Customer-only page that guests may reach: send them to log in, then back here. */
function require_user_or_login(string $returnTo): array
{
    $u = current_user_or_guest();
    if ($u) return $u;
    $next = smartq_safe_next($returnTo);
    header('Location: ../login.php?guest=1' . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
    exit();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Please go back and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
}
?>
