<?php
require_once __DIR__ . '/../helpers/auth_user.php';
require_once __DIR__ . '/../helpers/appointment_payload.php';

$authUser = requireUserAuth($conn);
$stmt = $conn->prepare(APPOINTMENT_SELECT . "
    WHERE a.user_id = ?
    ORDER BY s.schedule_date DESC, COALESCE(a.appointment_time, s.start_time) DESC");
$stmt->bind_param("i", $authUser['id']);
$stmt->execute();
$res = $stmt->get_result();

$list = [];
$stats = ["total" => 0, "upcoming" => 0, "waiting" => 0, "confirmed" => 0, "done" => 0, "cancelled" => 0, "missed" => 0];
while ($r = $res->fetch_assoc()) {
    $item = appointmentPayload($r);
    $list[] = $item;
    $stats["total"]++;
    if (isset($stats[$item["appointment_status"]])) $stats[$item["appointment_status"]]++;
    if ($item["can_cancel"]) $stats["upcoming"]++;
}
jsonResponse(true, "Appointments fetched successfully", ["appointments" => $list, "stats" => $stats]);
