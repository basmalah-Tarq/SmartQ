<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';
require_once __DIR__ . '/../../config/booking.php';

$scheduleId = (int)($_GET['schedule_id'] ?? 0);
if ($scheduleId <= 0) jsonResponse(false, "Schedule ID is required", [], 422);

$schedule = smartq_get_schedule($conn, $scheduleId);
if (!$schedule || (int)$schedule['active'] !== 1 || $schedule['status'] !== 'open') {
    jsonResponse(false, "Schedule not available", [], 404);
}

// Same slot list + "Help me choose" suggestion as the website (config/booking.php).
$view = smartq_bookable_slots($conn, $schedule);

jsonResponse(true, "Slots fetched successfully", [
    "schedule" => [
        "id" => (int)$schedule['id'],
        "place_name" => $schedule['place_name'],
        "schedule_date" => $schedule['schedule_date'],
        "start_time" => $schedule['start_time'],
        "end_time" => $schedule['end_time'],
        "slot_duration" => (int)$schedule['slot_duration'],
        "seats_left" => $view['seats_left']
    ],
    "free_slots" => $view['free_slots'],
    // "HH:MM:SS" of the recommended free slot, or null when nothing is free (added in API v2.1; older apps ignore it).
    "suggested_time" => $view['suggested_time'],
    "slots" => $view['slots']
]);
