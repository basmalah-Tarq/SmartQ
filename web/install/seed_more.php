<?php
/**
 * One-time demo filler (safe to run once on the live site):
 *  - adds ~40 fictional customers (emails @example.com, which are never emailed),
 *  - gives every real/demo customer account 2-4 upcoming turns + a short history,
 *  - fills today..+3 days to a realistic 45-75% so managers see busy days,
 *  - adds served/missed visits for the past week (history, stats and charts).
 * Web: protected by config/seed_token.php (deleted after a successful run). CLI: php seed_more.php [db_name]
 */
$tokenFile = __DIR__ . '/../config/seed_token.php';
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    if (!is_file($tokenFile)) { http_response_code(404); exit('Not found'); }
    $expected = (string)(require $tokenFile);
    if ($expected === '' || !hash_equals($expected, (string)($_GET['t'] ?? ''))) { http_response_code(404); exit('Not found'); }
}

require_once __DIR__ . '/../config/config.php';
$SMARTQ_CONFIG['MAIL_DRIVER'] = 'none';
if ($isCli && !empty($argv[1])) $SMARTQ_CONFIG['DB_NAME'] = $argv[1];
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/booking.php';
@set_time_limit(120);
mt_srand(20261110);
header('Content-Type: text/plain; charset=utf-8');

if ((int)$conn->query("SELECT COUNT(*) c FROM users WHERE email LIKE '%@example.com'")->fetch_assoc()['c'] > 0) {
    exit("Demo data was already added. Nothing to do.\n");
}

// --- 1) Fictional customers -------------------------------------------------
$first = ['Ahmad','Mohammed','Yousef','Omar','Khaled','Hamza','Ali','Mahmoud','Ibrahim','Anas','Sami','Tamer','Rami','Fadi','Bilal','Majd','Adam','Kareem','Nour','Lina','Sara','Rana','Huda','Mona','Dana','Aya','Hiba','Reem','Salma','Yasmin','Malak','Jana','Leen','Farah','Ruba','Shahd','Asmaa','Duaa','Maryam','Tala'];
$last = ['Abu Shaban','Al-Masri','Haddad','Saleh','Nasser','Khalil','Odeh','Hamdan','Barakat','Shaheen','Jaber','Darwish','Qasem','Zaqout','Abed','Hijazi','Ayyad','Mansour','Salama','Awad'];
$hash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
$insU = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, created_at) VALUES (?, ?, ?, ?, 'user', ?)");
foreach ($first as $i => $fn) {
    $name = $fn . ' ' . $last[$i % count($last)];
    $email = strtolower(str_replace([' ', '-'], ['.', ''], $name)) . '@example.com';
    $phone = '059' . str_pad((string)mt_rand(1000000, 9999999), 7, '0');
    $created = date('Y-m-d H:i:s', time() - mt_rand(5, 90) * 86400);
    $insU->bind_param('sssss', $name, $email, $hash, $phone, $created);
    $insU->execute();
}

$load = function (string $where) use ($conn) {
    $out = [];
    $r = $conn->query("SELECT id, full_name, email, phone FROM users WHERE role = 'user' AND $where");
    while ($u = $r->fetch_assoc()) $out[] = $u;
    return $out;
};
$fake = $load("email LIKE '%@example.com'");
$real = $load("email NOT LIKE '%@example.com' AND email NOT LIKE '%.test'");

$book = function (array $c, int $scheduleId, string $time, string $status) use ($conn) {
    $b = smartq_book($conn, (int)$c['id'], $scheduleId, [
        'customer_name' => $c['full_name'], 'customer_phone' => $c['phone'] ?? '',
        'customer_email' => $c['email'], 'appointment_time' => $time,
    ]);
    $conn->query("UPDATE appointments SET status = '" . $conn->real_escape_string($status) . "' WHERE id = " . (int)$b['appointment_id']);
};

$stats = ['real_upcoming' => 0, 'busy' => 0, 'past' => 0];
$upcoming = [];
$r = $conn->query("SELECT id FROM schedules WHERE status = 'open' AND schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY) ORDER BY schedule_date, start_time");
while ($s = $r->fetch_assoc()) $upcoming[] = (int)$s['id'];

// --- 2) 2-4 upcoming turns for every real/demo customer --------------------
foreach ($real as $c) {
    $have = (int)$conn->query("SELECT COUNT(*) c FROM appointments a JOIN schedules s ON s.id = a.schedule_id
        WHERE a.user_id = {$c['id']} AND a.status IN ('waiting','confirmed') AND s.schedule_date >= CURDATE()")->fetch_assoc()['c'];
    $want = mt_rand(2, 4) - $have;
    $ids = $upcoming;
    shuffle($ids);
    foreach ($ids as $sid) {
        if ($want <= 0) break;
        $schedule = smartq_get_schedule($conn, $sid);
        $free = array_values(array_filter(smartq_schedule_slots($conn, $schedule), fn($x) => $x['available']));
        if (!$free) continue;
        try {
            $book($c, $sid, $free[mt_rand(0, count($free) - 1)]['time'], mt_rand(0, 1) ? 'confirmed' : 'waiting');
            $want--; $stats['real_upcoming']++;
        } catch (Throwable $e) {}
    }
}

// --- 3) Busy days (today..+3) filled by fictional customers ----------------
$r = $conn->query("SELECT id FROM schedules WHERE status = 'open' AND schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
while ($s = $r->fetch_assoc()) {
    $sid = (int)$s['id'];
    $schedule = smartq_get_schedule($conn, $sid);
    $slots = smartq_schedule_slots($conn, $schedule);
    $free = array_values(array_filter($slots, fn($x) => $x['available']));
    $target = (int)round(count($slots) * mt_rand(45, 75) / 100);
    $have = count($slots) - count($free);
    shuffle($free);
    $pool = $fake;
    shuffle($pool);
    foreach ($free as $slot) {
        if ($have >= $target || !$pool) break;
        try {
            $book(array_pop($pool), $sid, $slot['time'], mt_rand(1, 100) <= 55 ? 'confirmed' : 'waiting');
            $have++; $stats['busy']++;
        } catch (Throwable $e) {}
    }
}

// --- 4) Past week: served / missed visits ----------------------------------
$ins = $conn->prepare("INSERT INTO appointments (user_id, schedule_id, customer_name, customer_phone, customer_email,
                       queue_number, appointment_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$r = $conn->query("SELECT id, schedule_date, start_time, end_time, slot_duration FROM schedules
                   WHERE schedule_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()");
while ($s = $r->fetch_assoc()) {
    $sid = (int)$s['id'];
    $taken = []; $users = [];
    $q = $conn->query("SELECT appointment_time, user_id FROM appointments WHERE schedule_id = $sid AND status IN ('waiting','confirmed','done')");
    while ($a = $q->fetch_assoc()) { $taken[$a['appointment_time']] = true; $users[$a['user_id']] = true; }
    $qn = (int)$conn->query("SELECT COALESCE(MAX(queue_number),0) m FROM appointments WHERE schedule_id = $sid")->fetch_assoc()['m'];
    $dur = max(5, (int)$s['slot_duration']);
    $pool = array_merge($fake, mt_rand(0, 3) === 0 ? $real : []);
    shuffle($pool);
    for ($t = strtotime($s['schedule_date'] . ' ' . $s['start_time']), $end = strtotime($s['schedule_date'] . ' ' . $s['end_time']);
         $t + $dur * 60 <= $end && $t < time() - 1800; $t += $dur * 60) {
        $time = date('H:i:s', $t);
        if (isset($taken[$time]) || mt_rand(1, 100) > 55) continue;
        $c = null;
        while ($pool && !$c) { $cand = array_pop($pool); if (!isset($users[$cand['id']])) $c = $cand; }
        if (!$c) break;
        $users[$c['id']] = true;
        $qn++;
        $status = mt_rand(1, 100) <= 88 ? 'done' : 'missed';
        $created = date('Y-m-d H:i:s', $t - mt_rand(1, 3) * 86400);
        $uid = (int)$c['id']; $phone = $c['phone'] ?? '';
        $ins->bind_param('iisssisss', $uid, $sid, $c['full_name'], $phone, $c['email'], $qn, $time, $status, $created);
        if ($ins->execute()) $stats['past']++;
    }
}

if (!$isCli) @unlink($tokenFile);
echo "Done ✅\nFictional customers: " . count($fake) . "\nUpcoming turns for your accounts: {$stats['real_upcoming']}\n"
   . "Bookings filling the next days: {$stats['busy']}\nPast visits (history): {$stats['past']}\n\n";
$t = $conn->query("SELECT u.email, SUM(a.status IN ('waiting','confirmed') AND s.schedule_date >= CURDATE()) upcoming, COUNT(*) total
                   FROM users u JOIN appointments a ON a.user_id = u.id JOIN schedules s ON s.id = a.schedule_id
                   WHERE u.role = 'user' AND u.email NOT LIKE '%@example.com' GROUP BY u.email ORDER BY u.email");
while ($row = $t->fetch_assoc()) echo str_pad($row['email'], 32) . " upcoming: {$row['upcoming']}  total: {$row['total']}\n";
