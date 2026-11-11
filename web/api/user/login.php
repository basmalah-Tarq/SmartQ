<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
if ($email === '' || $password === '') {
    jsonResponse(false, "Email and password are required", [], 422);
}

$stmt = $conn->prepare("SELECT id, full_name, email, password, phone, birth_date, profile_image, role
                        FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    usleep(300000); // slow down password guessing
    jsonResponse(false, "Invalid email or password", [], 401);
}
if ($user['role'] !== 'user') {
    jsonResponse(false, "This app is for customers. Managers and admins please use the website.", [], 403);
}

$token = issueApiToken($conn, (int)$user['id']);
jsonResponse(true, "Login successful", [
    "user" => userPayload($user),
    "api_token" => $token
]);
