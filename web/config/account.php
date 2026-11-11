<?php
/**
 * Account settings shared by the customer, manager and admin "My account" pages
 * (before: four slightly different copies, some without validation).
 */
require_once __DIR__ . '/helpers.php';

/** Re-read the logged-in user into the session (same columns login.php stores). */
function smartq_refresh_session_user(mysqli $conn, int $userId): ?array
{
    $s = $conn->prepare("SELECT id, full_name, email, password, phone, birth_date, profile_image, role FROM users WHERE id = ? LIMIT 1");
    $s->bind_param('i', $userId);
    $s->execute();
    $user = $s->get_result()->fetch_assoc();
    if ($user) {
        $_SESSION['user'] = $user;
    }
    return $user ?: null;
}

/**
 * Update name / phone / birth date and (optionally) the profile photo.
 * Returns [bool ok, string message].
 */
function smartq_update_account(mysqli $conn, array $user, array $post, ?array $file): array
{
    $fullName = trim((string)($post['full_name'] ?? ''));
    $phone = trim((string)($post['phone'] ?? ''));
    $birthDate = smartq_optional_date($post['birth_date'] ?? '');

    if ($fullName === '') return [false, 'Full name is required.'];
    if (mb_strlen($fullName) > 120) return [false, 'Full name is too long.'];
    if (mb_strlen($phone) > 30) return [false, 'Phone number is too long.'];
    if ($birthDate === false) return [false, 'Please enter a valid birth date.'];

    $image = $user['profile_image'] ?? null;
    try {
        $newImage = smartq_store_image_upload($file, 'profile_images', 'user_' . (int)$user['id']);
    } catch (RuntimeException $e) {
        return [false, $e->getMessage()];
    }
    if ($newImage !== null) {
        $image = $newImage;
    }

    $phoneValue = $phone !== '' ? $phone : null;
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, birth_date = ?, profile_image = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $fullName, $phoneValue, $birthDate, $image, $user['id']);
    if (!$stmt->execute()) {
        if ($newImage !== null) smartq_delete_upload($newImage);
        return [false, 'Failed to update profile.'];
    }
    if ($newImage !== null && !empty($user['profile_image'])) {
        smartq_delete_upload($user['profile_image']);
    }
    smartq_refresh_session_user($conn, (int)$user['id']);
    return [true, 'Profile updated successfully.'];
}

/** Change password after checking the current one against the database. Returns [bool ok, string message]. */
function smartq_change_password(mysqli $conn, int $userId, string $current, string $new, string $confirm): array
{
    if ($current === '' || $new === '' || $confirm === '') return [false, 'Please fill all password fields.'];
    if ($new !== $confirm) return [false, 'New password and confirm password do not match.'];
    if (strlen($new) < 6) return [false, 'New password must be at least 6 characters.'];

    $s = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $s->bind_param('i', $userId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row || !password_verify($current, (string)$row['password'])) return [false, 'Current password is incorrect.'];

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $u = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $u->bind_param('si', $hash, $userId);
    if (!$u->execute()) return [false, 'Failed to update password.'];
    smartq_refresh_session_user($conn, $userId);
    return [true, 'Password updated successfully.'];
}
