<?php
require_once '../config/db.php';
require_once '../config/security.php';

require_role('admin');
require_post();
verify_csrf();

$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($bookingId > 0) {
    $stmt = $conn->prepare('DELETE FROM appointments WHERE id = ?');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
}

header('Location: bookings.php');
exit();
?>
