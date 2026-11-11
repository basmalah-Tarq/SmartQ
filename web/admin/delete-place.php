<?php
require_once '../config/db.php';
require_once '../config/security.php';

require_role('admin');
require_post();
verify_csrf();

$placeId = (int)($_POST['place_id'] ?? 0);

if ($placeId > 0) {
    $stmt = $conn->prepare('DELETE FROM places WHERE id = ?');
    $stmt->bind_param('i', $placeId);
    $stmt->execute();
}

header('Location: places.php');
exit();
?>
