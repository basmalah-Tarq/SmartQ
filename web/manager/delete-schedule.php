<?php
require_once '../config/db.php';
require_once '../config/security.php';

$user = require_role('manager');
require_post();
verify_csrf();

$managerId = (int)$user['id'];
$scheduleId = (int)($_POST['schedule_id'] ?? 0);

if ($scheduleId > 0) {
    $stmt = $conn->prepare(""
        . "DELETE s FROM schedules s "
        . "JOIN places p ON s.place_id = p.id "
        . "WHERE s.id = ? AND p.manager_id = ?"
    );
    $stmt->bind_param('ii', $scheduleId, $managerId);
    $stmt->execute();
}

header('Location: schedules.php');
exit();
?>
