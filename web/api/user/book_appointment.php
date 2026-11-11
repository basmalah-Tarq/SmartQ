<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../../config/booking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, "Method not allowed", [], 405);
$authUser = requireUserAuth($conn);

try {
    $booking = smartq_book($conn, (int)$authUser['id'], (int)($_POST['schedule_id'] ?? 0), [
        'customer_name' => $_POST['customer_name'] ?? $authUser['full_name'],
        'customer_phone' => $_POST['customer_phone'] ?? ($authUser['phone'] ?? ''),
        'customer_email' => $_POST['customer_email'] ?? $authUser['email'],
        'customer_age' => $_POST['customer_age'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'appointment_time' => $_POST['appointment_time'] ?? '',
    ]);
    jsonResponse(true, "Appointment booked successfully", $booking);
} catch (BookingException $e) {
    // 422 = invalid input, 409 = booking conflict (taken / full / closed / duplicate)
    jsonResponse(false, $e->getMessage(), [], $e->getCode() === 422 ? 422 : 409);
}
