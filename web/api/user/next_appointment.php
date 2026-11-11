<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../helpers/appointment_payload.php';

$authUser = requireUserAuth($conn);
$stmt = $conn->prepare(APPOINTMENT_SELECT . "
    WHERE a.user_id = ? AND a.status IN ('waiting','confirmed')
      AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
    ORDER BY s.schedule_date ASC, COALESCE(a.appointment_time, s.start_time) ASC
    LIMIT 1");
$stmt->bind_param("i", $authUser['id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();

// "No upcoming booking" is a normal state, not an error (the old version answered 404 here).
jsonResponse(true, $r ? "Upcoming appointment fetched successfully" : "No upcoming appointment", [
    "appointment" => $r ? appointmentPayload($r) : null
]);
