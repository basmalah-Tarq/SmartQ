<?php
require_once '../config/db.php';
require_once '../config/security.php';

require_role('admin');
require_post();
verify_csrf();

$scheduleId = (int)($_POST['schedule_id'] ?? 0);

if ($scheduleId > 0) {
    $stmt = $conn->prepare('DELETE FROM schedules WHERE id = ?');
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
}

header('Location: schedules.php');
exit();
?>
