<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/helpers.php'; // smartq_csv_cell: blocks spreadsheet formula injection

$user = require_role('manager');

$managerId = (int)$user['id'];

$stmt = $conn->prepare("
    SELECT DISTINCT
        a.customer_name,
        a.customer_phone,
        a.customer_email,
        COUNT(a.id) AS bookings_count
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = ?
    GROUP BY a.customer_name, a.customer_phone, a.customer_email
    ORDER BY a.customer_name ASC
");
$stmt->bind_param('i', $managerId);
$stmt->execute();
$result = $stmt->get_result();

$filename = 'smartq-manager-customers-' . date('Y-m-d-H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Customer Name', 'Phone Number', 'Email Address', 'Bookings Count']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_map('smartq_csv_cell', [
        $row['customer_name'],
        $row['customer_phone'],
        $row['customer_email'],
        $row['bookings_count']
    ]));
}

fclose($output);
exit();
