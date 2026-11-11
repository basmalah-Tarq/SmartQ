<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../../config/booking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);
$authUser = requireUserAuth($conn);

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
if ($appointmentId <= 0) jsonResponse(false, "Appointment ID is required", [], 422);
if (isset($_POST['status']) && $_POST['status'] !== 'cancelled') {
    jsonResponse(false, "Users can only cancel appointments", [], 403);
}

try {
    smartq_cancel($conn, (int)$authUser['id'], $appointmentId);
    jsonResponse(true, "Appointment cancelled successfully", [
        "appointment_id" => $appointmentId,
        "status" => "cancelled"
    ]);
} catch (BookingException $e) {
    jsonResponse(false, $e->getMessage(), [], 409);
}
