<?php
require_once '../config/db.php';
require_once '../config/security.php';

$user = require_role('manager');
require_post();
verify_csrf();

$managerId = (int)$user['id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($bookingId > 0) {
    $stmt = $conn->prepare(""
        . "DELETE a FROM appointments a "
        . "JOIN schedules s ON a.schedule_id = s.id "
        . "JOIN places p ON s.place_id = p.id "
        . "WHERE a.id = ? AND p.manager_id = ?"
    );
    $stmt->bind_param('ii', $bookingId, $managerId);
    $stmt->execute();
}

header('Location: bookings.php');
exit();
?>
