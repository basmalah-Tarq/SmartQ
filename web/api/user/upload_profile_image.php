<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);
$authUser = requireUserAuth($conn);

// Multipart field name used by the app: "profile_image" (same as the website form).
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse(false, "No image uploaded", [], 422);
}

try {
    $relativePath = smartq_store_image_upload($_FILES['profile_image'], 'profile_images', 'user_' . (int)$authUser['id']);
} catch (RuntimeException $e) {
    jsonResponse(false, $e->getMessage(), [], 422);
}

$stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ? AND role = 'user'");
$stmt->bind_param("si", $relativePath, $authUser['id']);
if (!$stmt->execute()) {
    smartq_delete_upload($relativePath);
    jsonResponse(false, "Failed to save image path", [], 500);
}

// Only ever deletes files inside uploads/ (old value could be a Google photo URL or a legacy name).
smartq_delete_upload($authUser['profile_image'] ?? '');

jsonResponse(true, "Profile image uploaded successfully", [
    "profile_image" => $relativePath,
    "profile_image_url" => buildFileUrl($relativePath)
]);
