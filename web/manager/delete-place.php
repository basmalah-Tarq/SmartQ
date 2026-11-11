<?php
require_once '../config/db.php';
require_once '../config/security.php';

$user = require_role('manager');
require_post();
verify_csrf();

$managerId = (int)$user['id'];
$placeId = (int)($_POST['place_id'] ?? 0);

if ($placeId > 0) {
    $stmt = $conn->prepare('DELETE FROM places WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $placeId, $managerId);
    $stmt->execute();
}

header('Location: add-place.php');
exit();
?>
