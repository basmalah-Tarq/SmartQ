<?php
/**
 * SmartQ mailer: sends transactional emails through Brevo's HTTPS API
 * (shared hosts like InfinityFree block PHP mail() and SMTP, but allow HTTPS requests).
 */
require_once __DIR__ . '/config.php';

function smartq_mail_enabled(): bool
{
    global $SMARTQ_CONFIG;
    $d = $SMARTQ_CONFIG['MAIL_DRIVER'] ?? 'none';
    return $d === 'log' || ($d === 'brevo' && !empty($SMARTQ_CONFIG['BREVO_API_KEY']));
}

/** Send one email. Returns true on success. Never throws. */
function smartq_send_mail(string $toEmail, string $toName, string $subject, string $html): bool
{
    global $SMARTQ_CONFIG;
    $driver = $SMARTQ_CONFIG['MAIL_DRIVER'] ?? 'none';

    // Never email demo/test addresses (fake customers in the demo data).
    if (preg_match('/@(example\.(com|org|net)|[^@]+\.(test|demo|invalid))$/i', $toEmail)) {
        return false;
    }

    if ($driver === 'log') {
        $line = "[" . date('Y-m-d H:i:s') . "] TO: $toEmail | SUBJECT: $subject\n" . strip_tags(str_replace(['<br>', '</p>', '</h1>', '</div>'], "\n", $html)) . "\n-----\n";
        return (bool)@file_put_contents(__DIR__ . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    }
    if ($driver !== 'brevo' || empty($SMARTQ_CONFIG['BREVO_API_KEY']) || !function_exists('curl_init')) {
        return false;
    }

    $payload = json_encode([
        'sender' => ['name' => $SMARTQ_CONFIG['MAIL_FROM_NAME'], 'email' => $SMARTQ_CONFIG['MAIL_FROM']],
        'to' => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
        'subject' => $subject,
        'htmlContent' => $html,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $SMARTQ_CONFIG['BREVO_API_KEY'],
        ],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/** Branded HTML email layout (inline styles for email clients). */
function smartq_mail_layout(string $title, string $bodyHtml): string
{
    global $SMARTQ_CONFIG;
    $app = htmlspecialchars($SMARTQ_CONFIG['APP_NAME'] ?? 'SmartQ');
    return '<!doctype html><html><body style="margin:0;background:#F5F8FD;font-family:Inter,Segoe UI,Arial,sans-serif;color:#0B1B36">'
        . '<div style="max-width:520px;margin:0 auto;padding:32px 16px">'
        . '<div style="background:linear-gradient(135deg,#0B1F45,#1F6FE0 60%,#14A39A);border-radius:20px 20px 0 0;padding:22px 28px;color:#fff;font-weight:800;font-size:20px;letter-spacing:.2px">' . $app . '</div>'
        . '<div style="background:#fff;border:1px solid #E2E9F4;border-top:0;border-radius:0 0 20px 20px;padding:28px">'
        . '<h1 style="margin:0 0 12px;font-size:22px;color:#0B1F45">' . htmlspecialchars($title) . '</h1>'
        . $bodyHtml
        . '</div><p style="text-align:center;color:#5B6B85;font-size:12px;margin-top:18px">' . $app . ' · Book your turn. Skip the line.</p>'
        . '</div></body></html>';
}

function smartq_mail_login_code(string $email, string $code): bool
{
    $html = smartq_mail_layout('Your sign-in code',
        '<p style="margin:0 0 18px;color:#5B6B85">Use this code to continue. It expires in 10 minutes.</p>'
        . '<div style="font-size:34px;font-weight:800;letter-spacing:10px;color:#1F6FE0;background:#EEF4FC;border-radius:14px;padding:16px;text-align:center">' . htmlspecialchars($code) . '</div>'
        . '<p style="margin:18px 0 0;color:#5B6B85;font-size:13px">If you did not try to sign in, you can ignore this email.</p>');
    return smartq_send_mail($email, '', 'Your SmartQ code: ' . $code, $html);
}

function smartq_ticket_html(array $b, string $intro): string
{
    $rows = [
        'Place' => $b['place_name'],
        'Date' => date('l, j F Y', strtotime($b['schedule_date'])),
        'Time' => $b['appointment_time_label'] ?? date('g:i A', strtotime($b['appointment_time'])),
        'Queue number' => $b['queue_code'] ?? ('Q-' . str_pad((string)$b['queue_number'], 3, '0', STR_PAD_LEFT)),
    ];
    $html = '<p style="margin:0 0 18px;color:#5B6B85">' . $intro . '</p><table style="width:100%;border-collapse:collapse">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td style="padding:10px 0;color:#5B6B85;border-bottom:1px solid #E2E9F4">' . $k . '</td>'
            . '<td style="padding:10px 0;text-align:right;font-weight:700;border-bottom:1px solid #E2E9F4">' . htmlspecialchars((string)$v) . '</td></tr>';
    }
    return $html . '</table>';
}

/** Booking confirmation (called right after a successful booking; failures are ignored). */
function smartq_mail_booking_confirmation(mysqli $conn, int $appointmentId, array $booking, string $email, string $name): void
{
    if (!smartq_mail_enabled()) return;
    $ok = smartq_send_mail($email, $name, 'Booking confirmed · ' . $booking['place_name'],
        smartq_mail_layout('Your turn is booked ✔', smartq_ticket_html($booking, 'Hi ' . htmlspecialchars($name) . ', your booking is confirmed. Show your queue number when you arrive.')));
    if ($ok) {
        $s = $conn->prepare("UPDATE appointments SET confirmation_sent_at = NOW() WHERE id = ?");
        $s->bind_param('i', $appointmentId);
        $s->execute();
    }
}

/**
 * Send reminders for bookings starting within REMINDER_MINUTES_BEFORE minutes.
 * Shared hosting has no cron, so this runs opportunistically (throttled to once every 5 minutes)
 * whenever someone uses the site or the app.
 */
function smartq_run_due_reminders(mysqli $conn): void
{
    global $SMARTQ_CONFIG;
    if (!smartq_mail_enabled()) return;

    $now = time();
    $r = $conn->query("SELECT meta_value FROM app_meta WHERE meta_key = 'reminders_last_run'");
    $last = ($r && ($row = $r->fetch_assoc())) ? (int)$row['meta_value'] : 0;
    if ($now - $last < 300) return;
    $conn->query("REPLACE INTO app_meta (meta_key, meta_value) VALUES ('reminders_last_run', '" . $now . "')");

    $minutes = (int)($SMARTQ_CONFIG['REMINDER_MINUTES_BEFORE'] ?? 60);
    $stmt = $conn->prepare("
        SELECT a.id, a.queue_number, a.appointment_time, a.customer_email, a.customer_name,
               s.schedule_date, s.start_time, p.place_name
        FROM appointments a
        JOIN schedules s ON s.id = a.schedule_id
        JOIN places p ON p.id = s.place_id
        WHERE a.status IN ('waiting','confirmed') AND a.reminder_sent_at IS NULL
          AND TIMESTAMP(s.schedule_date, COALESCE(a.appointment_time, s.start_time))
              BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
        LIMIT 20");
    $stmt->bind_param('i', $minutes);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($a = $res->fetch_assoc()) {
        $a['appointment_time'] = $a['appointment_time'] ?: $a['start_time'];
        $a['appointment_time_label'] = date('g:i A', strtotime($a['appointment_time']));
        $ok = smartq_send_mail($a['customer_email'], $a['customer_name'], 'Reminder: your turn at ' . $a['place_name'] . ' is soon',
            smartq_mail_layout('Your turn is coming up ⏳', smartq_ticket_html($a, 'Hi ' . htmlspecialchars($a['customer_name']) . ', this is a friendly reminder about your booking.')));
        if ($ok) {
            $u = $conn->prepare("UPDATE appointments SET reminder_sent_at = NOW() WHERE id = ?");
            $u->bind_param('i', $a['id']);
            $u->execute();
        }
    }
}
