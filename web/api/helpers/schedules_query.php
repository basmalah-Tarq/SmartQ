<?php
/** Upcoming open schedules of a place that still have free seats. */
function fetchOpenSchedules(mysqli $conn, int $placeId)
{
    $stmt = $conn->prepare("
        SELECT s.id, s.place_id, s.schedule_date, s.start_time, s.end_time, s.capacity, s.slot_duration, s.status,
               (SELECT COUNT(*) FROM appointments a
                 WHERE a.schedule_id = s.id AND a.status IN ('waiting','confirmed','done')) AS booked_count
        FROM schedules s
        WHERE s.place_id = ? AND s.status = 'open'
          AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
        HAVING booked_count < s.capacity
        ORDER BY s.schedule_date ASC, s.start_time ASC");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $cap = (int)$r['capacity'];
        $booked = (int)$r['booked_count'];
        $out[] = [
            "id" => (int)$r['id'],
            "place_id" => (int)$r['place_id'],
            "schedule_date" => $r['schedule_date'],
            "start_time" => $r['start_time'],
            "end_time" => $r['end_time'],
            "capacity" => $cap,
            "slot_duration" => (int)$r['slot_duration'],
            "status" => $r['status'],
            "booked_count" => $booked,
            "available_slots" => max(0, $cap - $booked)
        ];
    }
    return $out;
}

function fetchActivePlace(mysqli $conn, int $placeId)
{
    $stmt = $conn->prepare("SELECT id, place_name, category, city, address, description, place_image, active
                            FROM places WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    return ($p && (int)$p['active'] === 1) ? $p : null;
}
