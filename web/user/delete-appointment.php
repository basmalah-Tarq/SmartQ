<?php
// Customer cancels one of their bookings (same rules as the app: config/booking.php smartq_cancel).
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/booking.php';

$user = require_role('user');
require_post();
verify_csrf();

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
if ($appointmentId <= 0) {
    header('Location: appointment.php');
    exit();
}

try {
    smartq_cancel($conn, (int)$user['id'], $appointmentId);
    header('Location: appointment.php?success=cancelled');
} catch (BookingException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: appointment.php');
}
exit();
