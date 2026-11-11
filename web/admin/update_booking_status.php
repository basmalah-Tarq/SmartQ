<?php
// Admin changes a booking status (form in admin/bookings.php). Same rules as the manager panel.
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/booking.php';

require_role('admin');
require_post();
verify_csrf();

$scheduleFilter = (int)($_POST['schedule_id'] ?? 0);
$back = 'bookings.php' . ($scheduleFilter > 0 ? '?schedule_id=' . $scheduleFilter : '');

try {
    smartq_set_appointment_status($conn, (int)($_POST['appointment_id'] ?? 0), trim((string)($_POST['status'] ?? '')));
    $back .= ($scheduleFilter > 0 ? '&' : '?') . 'success=status_updated';
} catch (BookingException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}
header('Location: ' . $back);
exit;
