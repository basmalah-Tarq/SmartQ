<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);
$authUser = requireUserAuth($conn);

$fullName = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
// '' clears the birth date (stored as NULL, never '' / 0000-00-00).
$birthDate = smartq_optional_date($_POST['birth_date'] ?? '');

if ($fullName === '' || $email === '') {
    jsonResponse(false, "Full name and email are required", [], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Invalid email address", [], 422);
}
if (mb_strlen($fullName) > 120 || mb_strlen($phone) > 30) {
    jsonResponse(false, "Name or phone number is too long", [], 422);
}
if ($birthDate === false) {
    jsonResponse(false, "Birth date must be a valid past date in YYYY-MM-DD or YYYY/MM/DD format", [], 422);
}

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
$checkStmt->bind_param("si", $email, $authUser['id']);
$checkStmt->execute();
if ($checkStmt->get_result()->fetch_assoc()) {
    jsonResponse(false, "Email already used by another account", [], 409);
}

$phoneValue = $phone !== '' ? $phone : null;
$stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, birth_date = ? WHERE id = ? AND role = 'user'");
$stmt->bind_param("ssssi", $fullName, $email, $phoneValue, $birthDate, $authUser['id']);
if (!$stmt->execute()) {
    jsonResponse(false, "Failed to update profile", [], 500);
}

// Full user object (incl. profile_image_url) so the app's cached profile stays complete.
$authUser['full_name'] = $fullName;
$authUser['email'] = $email;
$authUser['phone'] = $phone;
$authUser['birth_date'] = $birthDate ?? '';
jsonResponse(true, "Profile updated successfully", ["user" => userPayload($authUser)]);
