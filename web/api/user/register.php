<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$birthDate = trim($_POST['birth_date'] ?? '');

if ($fullName === '' || $email === '' || $password === '') {
    jsonResponse(false, "Full name, email and password are required", [], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, "Invalid email address", [], 422);
if (strlen($password) < 6) jsonResponse(false, "Password must be at least 6 characters", [], 422);

if ($birthDate !== '') {
    $birthDate = normalizeDateInput($birthDate);
    if ($birthDate === false) jsonResponse(false, "Birth date must be in YYYY-MM-DD format", [], 422);
} else {
    $birthDate = null;
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) jsonResponse(false, "This email is already registered", [], 409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, birth_date, role) VALUES (?, ?, ?, ?, ?, 'user')");
$stmt->bind_param("sssss", $fullName, $email, $hash, $phone, $birthDate);
if (!$stmt->execute()) jsonResponse(false, "Registration failed. Please try again.", [], 500);

$userId = (int)$stmt->insert_id;
$token = issueApiToken($conn, $userId);
jsonResponse(true, "Registration successful", [
    "user" => userPayload([
        "id" => $userId, "full_name" => $fullName, "email" => $email,
        "phone" => $phone, "birth_date" => $birthDate, "profile_image" => ""
    ]),
    "api_token" => $token
]);
