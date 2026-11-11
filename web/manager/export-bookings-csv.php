<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/helpers.php'; // smartq_csv_cell: blocks spreadsheet formula injection

$user = require_role('manager');

$managerId = (int)$user['id'];
$scheduleFilter = (int)($_GET['schedule_id'] ?? 0);

$sql = "
    SELECT
        a.id,
        a.customer_name,
        a.customer_phone,
        a.customer_email,
        a.queue_number,
        a.appointment_time,
        a.status,
        a.notes,
        p.place_name,
        s.schedule_date,
        s.start_time,
        s.end_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = ?
";

$params = [$managerId];
$types = 'i';

if ($scheduleFilter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $scheduleFilter;
    $types .= 'i';
}

$sql .= " ORDER BY s.schedule_date DESC, s.start_time DESC, a.queue_number ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = 'smartq-manager-bookings-' . date('Y-m-d-H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Booking ID',
    'Customer Name',
    'Phone',
    'Email',
    'Place',
    'Date',
    'Time',
    'Queue Number',
    'Status',
    'Notes'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_map('smartq_csv_cell', [
        $row['id'],
        $row['customer_name'],
        $row['customer_phone'],
        $row['customer_email'],
        $row['place_name'],
        $row['schedule_date'],
        (!empty($row['appointment_time']) ? substr($row['appointment_time'], 0, 5) : substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5)),
        $row['queue_number'],
        ucfirst($row['status']),
        $row['notes'] ?: '-'
    ]));
}

fclose($output);
exit();
