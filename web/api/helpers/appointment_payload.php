<?php
const APPOINTMENT_SELECT = "
    SELECT a.id, a.queue_number, a.appointment_time, a.status AS appointment_status, a.created_at,
           s.id AS schedule_id, s.schedule_date, s.start_time, s.end_time,
           p.id AS place_id, p.place_name, p.category, p.city, p.address, p.place_image
    FROM appointments a
    INNER JOIN schedules s ON a.schedule_id = s.id
    INNER JOIN places p ON s.place_id = p.id";

function appointmentPayload(array $r)
{
    $time = $r['appointment_time'] ?: $r['start_time'];
    return [
        "id" => (int)$r["id"],
        "queue_number" => (int)$r["queue_number"],
        "queue_code" => 'Q-' . str_pad((string)$r['queue_number'], 3, '0', STR_PAD_LEFT),
        "appointment_status" => $r["appointment_status"],
        "appointment_time" => $time,
        "appointment_time_label" => date('g:i A', strtotime($time)),
        "schedule_date" => $r["schedule_date"],
        "start_time" => $r["start_time"],
        "end_time" => $r["end_time"],
        "place_name" => $r["place_name"],
        "category" => $r["category"],
        "city" => $r["city"],
        "address" => $r["address"] ?? "",
        "place_image_url" => placeImageUrl($r["place_image"] ?? ""),
        "can_cancel" => in_array($r["appointment_status"], ['waiting', 'confirmed'], true)
            && strtotime($r['schedule_date'] . ' ' . $r['end_time']) >= time(),
        // Nested objects kept for backward compatibility with older app builds.
        "schedule" => [
            "id" => (int)$r["schedule_id"],
            "schedule_date" => $r["schedule_date"],
            "start_time" => $r["start_time"],
            "end_time" => $r["end_time"]
        ],
        "place" => [
            "id" => (int)$r["place_id"],
            "place_name" => $r["place_name"]
        ]
    ];
}
