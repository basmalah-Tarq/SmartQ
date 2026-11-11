<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(
    $SMARTQ_CONFIG['DB_HOST'],
    $SMARTQ_CONFIG['DB_USER'],
    $SMARTQ_CONFIG['DB_PASS'],
    $SMARTQ_CONFIG['DB_NAME'],
    $SMARTQ_CONFIG['DB_PORT']
);

if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'Database connection failed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');
// Keep MySQL NOW()/CURDATE()/CURTIME() in the same timezone as PHP.
$conn->query("SET time_zone = '" . $conn->real_escape_string((new DateTime())->format('P')) . "'");

// Appointment reminder emails: no cron on shared hosting, so check (throttled to every 5 min) on normal traffic.
try {
    require_once __DIR__ . '/mailer.php';
    smartq_run_due_reminders($conn);
} catch (Throwable $reminderError) {
}

if (!defined('BASE_URL')) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $basePath = preg_replace('#/(admin|manager|user|api(/user|/helpers)?)$#', '', $scriptDir);
    $basePath = ($basePath === '/' || $basePath === '.') ? '' : $basePath;
    define('BASE_URL', rtrim($scheme . '://' . $hostName . $basePath, '/') . '/');
}
