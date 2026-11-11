<?php
/**
 * SmartQ booking service.
 * ONE implementation used by both the website (user/booking.php) and the mobile API
 * (api/user/book_appointment.php, api/user/slots.php), so both clients always behave the same.
 */

class BookingException extends Exception {}

/** Statuses that occupy a seat / time slot. */
const SMARTQ_ACTIVE_STATUSES = ['waiting', 'confirmed', 'done'];

/** Load a bookable schedule (with its place). Optionally lock the row for the transaction. */
function smartq_get_schedule(mysqli $conn, int $scheduleId, bool $forUpdate = false): ?array
{
    $sql = "SELECT s.id, s.place_id, s.schedule_date, s.start_time, s.end_time, s.capacity,
                   s.slot_duration, s.status, p.place_name, p.category, p.city, p.address, p.active
            FROM schedules s
            INNER JOIN places p ON p.id = s.place_id
            WHERE s.id = ? LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

/** All time slots of a schedule with availability. */
function smartq_schedule_slots(mysqli $conn, array $schedule): array
{
    $duration = max(5, (int)($schedule['slot_duration'] ?? 30));
    $date = $schedule['schedule_date'];
    $start = strtotime($date . ' ' . $schedule['start_time']);
    $end = strtotime($date . ' ' . $schedule['end_time']);
    $now = time();

    $stmt = $conn->prepare("SELECT appointment_time FROM appointments
                            WHERE schedule_id = ? AND appointment_time IS NOT NULL
                              AND status IN ('waiting','confirmed','done')");
    $stmt->bind_param('i', $schedule['id']);
    $stmt->execute();
    $taken = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $taken[date('H:i:s', strtotime($r['appointment_time']))] = true;
    }

    $slots = [];
    for ($t = $start; $t + $duration * 60 <= $end; $t += $duration * 60) {
        $time = date('H:i:s', $t);
        $isTaken = isset($taken[$time]);
        $isPast = $t < $now;
        $slots[] = [
            'time' => $time,
            'label' => date('g:i A', $t),
            'available' => !$isTaken && !$isPast,
            'reason' => $isTaken ? 'booked' : ($isPast ? 'past' : null),
        ];
    }
    return $slots;
}

/** Number of active bookings on a schedule. */
function smartq_active_count(mysqli $conn, int $scheduleId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments
                            WHERE schedule_id = ? AND status IN ('waiting','confirmed','done')");
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/**
 * Book a slot. Runs in a transaction with the schedule row locked, so two people
 * pressing "Book" at the same moment can never get the same slot or exceed capacity.
 * The database unique indexes (migration 001) are a second safety net.
 *
 * $data keys: customer_name, customer_phone, customer_email, customer_age (opt), notes (opt),
 *             appointment_time (opt, "HH:MM[:SS]"; if empty the first free slot is assigned)
 * Returns the booking summary array. Throws BookingException with a user-friendly message.
 */
function smartq_book(mysqli $conn, int $userId, int $scheduleId, array $data): array
{
    $name = trim((string)($data['customer_name'] ?? ''));
    $phone = trim((string)($data['customer_phone'] ?? ''));
    $email = trim((string)($data['customer_email'] ?? ''));
    $ageRaw = trim((string)($data['customer_age'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $wantedTime = trim((string)($data['appointment_time'] ?? ''));

    if ($scheduleId <= 0 || $name === '' || $email === '') {
        throw new BookingException('Please fill in your name and email.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new BookingException('Invalid email address.', 422);
    }
    $age = null;
    if ($ageRaw !== '') {
        if (!ctype_digit($ageRaw) || (int)$ageRaw > 120) {
            throw new BookingException('Invalid age.', 422);
        }
        $age = (int)$ageRaw;
    }
    if ($wantedTime !== '') {
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $wantedTime)) {
            throw new BookingException('Invalid time format.', 422);
        }
        $wantedTime = date('H:i:s', strtotime('2000-01-01 ' . $wantedTime));
    }

    $conn->begin_transaction();
    try {
        $schedule = smartq_get_schedule($conn, $scheduleId, true);
        if (!$schedule || (int)$schedule['active'] !== 1) {
            throw new BookingException('This schedule does not exist.');
        }
        if ($schedule['status'] !== 'open') {
            throw new BookingException('This schedule is closed.');
        }
        if (strtotime($schedule['schedule_date'] . ' ' . $schedule['end_time']) < time()) {
            throw new BookingException('This schedule has already ended.');
        }

        $dup = $conn->prepare("SELECT id FROM appointments WHERE user_id = ? AND schedule_id = ?
                               AND status IN ('waiting','confirmed','done') LIMIT 1");
        $dup->bind_param('ii', $userId, $scheduleId);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            throw new BookingException('You already have a booking in this schedule.');
        }

        if (smartq_active_count($conn, $scheduleId) >= (int)$schedule['capacity']) {
            throw new BookingException('This schedule is full.');
        }

        $slots = smartq_schedule_slots($conn, $schedule);
        $chosen = null;
        foreach ($slots as $slot) {
            if ($wantedTime === '' && $slot['available']) { $chosen = $slot; break; }
            if ($wantedTime !== '' && $slot['time'] === $wantedTime) { $chosen = $slot; break; }
        }
        if ($chosen === null) {
            throw new BookingException($wantedTime === '' ? 'No free time slots left.' : 'This time is not part of the schedule.');
        }
        if (!$chosen['available']) {
            throw new BookingException($chosen['reason'] === 'past'
                ? 'This time has already passed. Please choose another time.'
                : 'This time was just booked by someone else. Please choose another time.');
        }

        $q = $conn->prepare("SELECT COALESCE(MAX(queue_number), 0) + 1 AS nextq FROM appointments WHERE schedule_id = ?");
        $q->bind_param('i', $scheduleId);
        $q->execute();
        $queueNumber = (int)$q->get_result()->fetch_assoc()['nextq'];

        $ins = $conn->prepare("INSERT INTO appointments
            (user_id, schedule_id, customer_name, customer_phone, customer_email, customer_age,
             queue_number, appointment_time, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')");
        $time = $chosen['time'];
        $ins->bind_param('iisssiiss', $userId, $scheduleId, $name, $phone, $email, $age, $queueNumber, $time, $notes);
        if (!$ins->execute()) {
            // 1062 = duplicate key from the unique indexes -> a parallel request won the race.
            if ($conn->errno === 1062) {
                throw new BookingException('This time was just booked by someone else. Please choose another time.');
            }
            throw new BookingException('Booking failed. Please try again.');
        }
        $appointmentId = (int)$ins->insert_id;
        $conn->commit();

        $result = [
            'appointment_id' => $appointmentId,
            'queue_number' => $queueNumber,
            'queue_code' => 'Q-' . str_pad((string)$queueNumber, 3, '0', STR_PAD_LEFT),
            'appointment_time' => $time,
            'appointment_time_label' => $chosen['label'],
            'status' => 'waiting',
            'place_name' => $schedule['place_name'],
            'schedule_date' => $schedule['schedule_date'],
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
        ];

        // Confirmation email (best effort; never blocks or breaks a booking).
        try {
            require_once __DIR__ . '/mailer.php';
            smartq_mail_booking_confirmation($conn, $appointmentId, $result, $email, $name);
        } catch (Throwable $mailError) {
        }
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e instanceof BookingException) {
            throw $e;
        }
        throw new BookingException('Booking failed. Please try again.');
    }
}

/** Cancel an active booking that belongs to the user (throws BookingException otherwise). */
function smartq_cancel(mysqli $conn, int $userId, int $appointmentId): void
{
    $stmt = $conn->prepare("SELECT a.status, s.schedule_date, s.end_time FROM appointments a
                            JOIN schedules s ON s.id = a.schedule_id
                            WHERE a.id = ? AND a.user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $appointmentId, $userId);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    if (!$a) throw new BookingException('Appointment not found.');
    if ($a['status'] === 'cancelled') throw new BookingException('Appointment already cancelled.');
    if (in_array($a['status'], ['done', 'missed'], true)) throw new BookingException('This appointment can no longer be cancelled.');
    if (strtotime($a['schedule_date'] . ' ' . $a['end_time']) < time()) throw new BookingException('Past appointments cannot be cancelled.');

    $up = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $up->bind_param('ii', $appointmentId, $userId);
    if (!$up->execute()) throw new BookingException('Failed to cancel appointment.');
}

/**
 * "Help me choose" — ONE suggestion used by the website and the app (api/user/slots.php -> suggested_time).
 *
 * Heuristic (documented so it can be explained in the UI / report):
 *   1. Only free slots that start at least SMARTQ_SUGGEST_LEAD_MINUTES from now are candidates
 *      (nobody wants a turn they cannot reach). If none qualify, any free slot is a candidate.
 *   2. Among the first SMARTQ_SUGGEST_LOOKAHEAD candidates (keeps the suggestion early in the day),
 *      prefer the slot whose neighbours (slot before / after) are not booked = the least crowded moment,
 *      so the customer is less likely to wait behind a running-late appointment.
 *   3. Ties -> the earliest slot.
 * Returns the slot time "HH:MM:SS" or null when nothing can be booked.
 */
const SMARTQ_SUGGEST_LEAD_MINUTES = 15;
const SMARTQ_SUGGEST_LOOKAHEAD = 6;

function smartq_suggest_slot(mysqli $conn, array $schedule, ?array $slots = null): ?string
{
    if ($slots === null) {
        $slots = smartq_bookable_slots($conn, $schedule)['slots'];
    }
    $minStart = time() + SMARTQ_SUGGEST_LEAD_MINUTES * 60;
    $candidates = [];
    $fallback = [];
    foreach ($slots as $i => $slot) {
        if (!$slot['available']) continue;
        $fallback[] = $i;
        if (strtotime($schedule['schedule_date'] . ' ' . $slot['time']) >= $minStart) {
            $candidates[] = $i;
        }
    }
    if (!$candidates) $candidates = $fallback;
    if (!$candidates) return null;

    $best = null;
    $bestScore = -1;
    foreach (array_slice($candidates, 0, SMARTQ_SUGGEST_LOOKAHEAD) as $i) {
        $score = 0;
        foreach ([$i - 1, $i + 1] as $n) {
            if (!isset($slots[$n]) || ($slots[$n]['reason'] ?? null) !== 'booked') $score++;
        }
        if ($score > $bestScore) { // strict ">" keeps the earliest slot on ties
            $best = $i;
            $bestScore = $score;
        }
    }
    return $slots[$best]['time'];
}

/**
 * Slots of a schedule as clients should show them: booked/past slots are unavailable and,
 * when the schedule's capacity is reached, every remaining slot is marked 'full'.
 * Returns ['slots' => [...], 'seats_left' => int, 'free_slots' => int, 'suggested_time' => ?string].
 */
function smartq_bookable_slots(mysqli $conn, array $schedule): array
{
    $slots = smartq_schedule_slots($conn, $schedule);
    $seatsLeft = max(0, (int)$schedule['capacity'] - smartq_active_count($conn, (int)$schedule['id']));
    if ($seatsLeft === 0) {
        foreach ($slots as &$s) {
            if ($s['available']) { $s['available'] = false; $s['reason'] = 'full'; }
        }
        unset($s);
    }
    $free = count(array_filter($slots, fn($s) => $s['available']));
    return [
        'slots' => $slots,
        'seats_left' => $seatsLeft,
        'free_slots' => $free,
        'suggested_time' => $free > 0 ? smartq_suggest_slot($conn, $schedule, $slots) : null,
    ];
}

/** Number of time slots a schedule window holds. */
function smartq_slot_count(string $startTime, string $endTime, int $duration): int
{
    $duration = max(5, $duration);
    $minutes = (int)((strtotime('2000-01-01 ' . $endTime) - strtotime('2000-01-01 ' . $startTime)) / 60);
    return $minutes > 0 ? intdiv($minutes, $duration) : 0;
}

/**
 * Staff (manager/admin) status change. $managerId limits it to that manager's places (null = admin).
 * Reactivating a cancelled/missed booking can fail when its time was re-booked meanwhile:
 * that is reported instead of failing silently.
 */
function smartq_set_appointment_status(mysqli $conn, int $appointmentId, string $status, ?int $managerId = null): void
{
    $allowed = ['waiting', 'confirmed', 'cancelled', 'done', 'missed'];
    if (!in_array($status, $allowed, true)) throw new BookingException('Invalid status.');

    $sql = "SELECT a.id, a.status FROM appointments a
            JOIN schedules s ON s.id = a.schedule_id
            JOIN places p ON p.id = s.place_id
            WHERE a.id = ?" . ($managerId !== null ? " AND p.manager_id = ?" : "") . " LIMIT 1";
    $q = $conn->prepare($sql);
    if ($managerId !== null) $q->bind_param('ii', $appointmentId, $managerId);
    else $q->bind_param('i', $appointmentId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if (!$row) throw new BookingException('Booking not found.');
    if ($row['status'] === $status) return;

    $u = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $u->bind_param('si', $status, $appointmentId);
    if (!$u->execute()) {
        if ($conn->errno === 1062) {
            throw new BookingException('This booking cannot be reactivated: its time slot (or this customer) already has another active booking in the same schedule.');
        }
        throw new BookingException('Failed to update the booking status.');
    }
}

/**
 * Check a schedule edit against its existing bookings. Returns '' when OK, otherwise a message.
 * Rules: capacity can't go below the active bookings; with active bookings the date can't change and
 * every booked time must still be a slot of the new time range (no orphaned bookings);
 * no overlap with another schedule of the same place on that day.
 */
function smartq_check_schedule_change(mysqli $conn, array $schedule, string $date, string $start, string $end, int $capacity): string
{
    $id = (int)$schedule['id'];
    $duration = max(5, (int)$schedule['slot_duration']);
    $slotCount = smartq_slot_count($start, $end, $duration);
    if ($slotCount < 1) return "The time range must fit at least one {$duration}-minute slot.";
    if ($capacity > $slotCount) return "Capacity can't be more than the {$slotCount} time slots of this schedule (one customer per slot).";

    $active = smartq_active_count($conn, $id);
    if ($capacity < $active) return "Capacity can't be lower than the {$active} active booking(s) of this schedule.";

    $b = $conn->prepare("SELECT appointment_time FROM appointments
                         WHERE schedule_id = ? AND status IN ('waiting','confirmed') AND appointment_time IS NOT NULL");
    $b->bind_param('i', $id);
    $b->execute();
    $res = $b->get_result();
    $orphans = [];
    $hasBookings = false;
    $startTs = strtotime('2000-01-01 ' . $start);
    $endTs = strtotime('2000-01-01 ' . $end);
    while ($r = $res->fetch_assoc()) {
        $hasBookings = true;
        $t = strtotime('2000-01-01 ' . $r['appointment_time']);
        $aligned = (($t - $startTs) % ($duration * 60)) === 0;
        if ($t < $startTs || $t + $duration * 60 > $endTs || !$aligned) {
            $orphans[] = date('g:i A', $t);
        }
    }
    if ($hasBookings && $date !== $schedule['schedule_date']) {
        return "This schedule already has active bookings, so its date can't be changed. Cancel those bookings first or create a new schedule.";
    }
    if ($orphans) {
        return 'These booked times would no longer fit the new hours: ' . implode(', ', array_unique($orphans)) . '. Keep them inside the schedule or cancel those bookings first.';
    }

    $o = $conn->prepare("SELECT COUNT(*) c FROM schedules
                         WHERE place_id = ? AND schedule_date = ? AND id <> ? AND start_time < ? AND end_time > ?");
    $placeId = (int)$schedule['place_id'];
    $o->bind_param('isiss', $placeId, $date, $id, $end, $start);
    $o->execute();
    if ((int)$o->get_result()->fetch_assoc()['c'] > 0) return 'This time range overlaps another schedule of the same place on that day.';
    return '';
}
