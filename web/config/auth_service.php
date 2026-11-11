<?php
/**
 * SmartQ passwordless auth: "Continue with email" (6-digit one-time code) and "Continue with Google".
 * Shared by the website and the mobile API.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

class AuthException extends Exception {}

const OTP_TTL_MINUTES = 10;
const OTP_MAX_ATTEMPTS = 5;
const OTP_RESEND_SECONDS = 60;
const OTP_MAX_PER_HOUR = 6;

function smartq_auth_options(): array
{
    global $SMARTQ_CONFIG;
    $ids = array_values(array_filter((array)($SMARTQ_CONFIG['GOOGLE_CLIENT_IDS'] ?? [])));
    return [
        'email_code' => smartq_mail_enabled(),
        'google' => count($ids) > 0,
        'google_client_id' => $ids[0] ?? '',
        'password' => true,
    ];
}

/** Step 1: send a 6-digit code to the email. */
function smartq_email_code_start(mysqli $conn, string $email): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AuthException('Please enter a valid email address.', 422);
    if (!smartq_mail_enabled()) throw new AuthException('Email sign-in is not available right now. Please use your password.', 400);

    $s = $conn->prepare("SELECT COUNT(*) c, MAX(created_at) last FROM email_otps
                         WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $s->bind_param('s', $email);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ((int)$row['c'] >= OTP_MAX_PER_HOUR) throw new AuthException('Too many codes requested. Please try again later.', 429);
    if ($row['last'] && time() - strtotime($row['last']) < OTP_RESEND_SECONDS) {
        throw new AuthException('Please wait a minute before requesting a new code.', 429);
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + OTP_TTL_MINUTES * 60);
    $now = date('Y-m-d H:i:s');
    $ins = $conn->prepare("INSERT INTO email_otps (email, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?)");
    $ins->bind_param('ssss', $email, $hash, $expires, $now);
    $ins->execute();

    if (!smartq_mail_login_code($email, $code)) {
        throw new AuthException('We could not send the email. Please try again in a moment.', 503);
    }

    $u = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $u->bind_param('s', $email);
    $u->execute();
    $exists = (bool)$u->get_result()->fetch_assoc();

    return ['email' => $email, 'is_new_user' => !$exists, 'expires_in' => OTP_TTL_MINUTES * 60, 'resend_in' => OTP_RESEND_SECONDS];
}

/**
 * Step 2: check the code. Logs in the existing account, or creates a customer account for a new email.
 * Returns the user row.
 */
function smartq_email_code_verify(mysqli $conn, string $email, string $code, string $fullName = ''): array
{
    $email = strtolower(trim($email));
    $code = preg_replace('/\D/', '', $code);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6) throw new AuthException('Enter the 6-digit code from your email.');

    $s = $conn->prepare("SELECT id, code_hash, attempts, expires_at FROM email_otps
                         WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
    $s->bind_param('s', $email);
    $s->execute();
    $otp = $s->get_result()->fetch_assoc();
    if (!$otp || strtotime($otp['expires_at']) < time()) throw new AuthException('This code has expired. Please request a new one.');
    if ((int)$otp['attempts'] >= OTP_MAX_ATTEMPTS) throw new AuthException('Too many wrong attempts. Please request a new code.');

    if (!password_verify($code, $otp['code_hash'])) {
        $a = $conn->prepare("UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?");
        $a->bind_param('i', $otp['id']);
        $a->execute();
        $left = OTP_MAX_ATTEMPTS - (int)$otp['attempts'] - 1;
        throw new AuthException($left > 0 ? "Wrong code. $left attempts left." : 'Too many wrong attempts. Please request a new code.');
    }
    $used = $conn->prepare("UPDATE email_otps SET used_at = NOW() WHERE id = ?");
    $used->bind_param('i', $otp['id']);
    $used->execute();

    return smartq_find_or_create_user($conn, $email, $fullName, 'email', null);
}

/** Verify a Google ID token (from Google Identity Services on web, Credential Manager on Android). */
function smartq_google_verify(mysqli $conn, string $idToken): array
{
    global $SMARTQ_CONFIG;
    $allowed = array_filter((array)($SMARTQ_CONFIG['GOOGLE_CLIENT_IDS'] ?? []));
    if (!$allowed) throw new AuthException('Google sign-in is not configured yet.');
    if ($idToken === '' || !function_exists('curl_init')) throw new AuthException('Google sign-in failed.');

    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 8]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $info = json_decode((string)$body, true);

    if ($http !== 200 || !is_array($info)) throw new AuthException('Google sign-in failed. Please try again.');
    if (!in_array($info['aud'] ?? '', $allowed, true)) throw new AuthException('Google sign-in failed (wrong app).');
    if (!in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) throw new AuthException('Google sign-in failed.');
    if ((int)($info['exp'] ?? 0) < time()) throw new AuthException('Google sign-in expired. Please try again.');
    if (($info['email_verified'] ?? 'false') !== 'true' && ($info['email_verified'] ?? false) !== true) throw new AuthException('Your Google email is not verified.');

    return smartq_find_or_create_user($conn, strtolower($info['email']), (string)($info['name'] ?? ''), 'google', (string)$info['sub'], (string)($info['picture'] ?? ''));
}

/** Start a website session for a user id (same session shape login.php uses). */
function smartq_web_login(mysqli $conn, int $userId): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $s = $conn->prepare("SELECT id, full_name, email, password, phone, birth_date, profile_image, role FROM users WHERE id = ? LIMIT 1");
    $s->bind_param('i', $userId);
    $s->execute();
    $user = $s->get_result()->fetch_assoc();
    if (!$user) throw new AuthException('Account not found.');
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function smartq_role_home(string $role): string
{
    return $role === 'admin' ? 'admin/index.php' : ($role === 'manager' ? 'manager/index.php' : 'user/index.php');
}

function smartq_find_or_create_user(mysqli $conn, string $email, string $fullName, string $provider, ?string $googleId, string $picture = ''): array
{
    $sel = "SELECT id, full_name, email, phone, birth_date, profile_image, role, google_id FROM users WHERE ";
    if ($googleId) {
        $s = $conn->prepare($sel . "google_id = ? OR email = ? ORDER BY google_id = ? DESC LIMIT 1");
        $s->bind_param('sss', $googleId, $email, $googleId);
    } else {
        $s = $conn->prepare($sel . "email = ? LIMIT 1");
        $s->bind_param('s', $email);
    }
    $s->execute();
    $user = $s->get_result()->fetch_assoc();

    if ($user) {
        $u = $conn->prepare("UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()),
                                 google_id = COALESCE(google_id, ?) WHERE id = ?");
        $u->bind_param('si', $googleId, $user['id']);
        $u->execute();
        if (empty($user['profile_image']) && $picture !== '') {
            $p = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $p->bind_param('si', $picture, $user['id']);
            $p->execute();
            $user['profile_image'] = $picture;
        }
        return $user;
    }

    $name = trim($fullName) !== '' ? trim($fullName) : ucwords(str_replace(['.', '_', '-'], ' ', strstr($email, '@', true)));
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // passwordless account
    $img = $picture !== '' ? $picture : null;
    $ins = $conn->prepare("INSERT INTO users (full_name, email, password, profile_image, role, auth_provider, google_id, email_verified_at)
                           VALUES (?, ?, ?, ?, 'user', ?, ?, NOW())");
    $ins->bind_param('ssssss', $name, $email, $randomPassword, $img, $provider, $googleId);
    if (!$ins->execute()) throw new AuthException('Could not create your account. Please try again.');

    return [
        'id' => (int)$ins->insert_id, 'full_name' => $name, 'email' => $email, 'phone' => '',
        'birth_date' => null, 'profile_image' => $img ?? '', 'role' => 'user', 'google_id' => $googleId,
        'is_new_user' => true,
    ];
}
