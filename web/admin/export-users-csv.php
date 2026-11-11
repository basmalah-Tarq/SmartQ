<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../config/helpers.php'; // smartq_csv_cell: blocks spreadsheet formula injection

$user = require_role('admin');

$roleFilter = trim($_GET['role'] ?? '');

$sql = "SELECT id, full_name, email, phone, birth_date, role, created_at FROM users WHERE role IN ('user','manager')";
$params = [];
$types = '';

if ($roleFilter !== '' && in_array($roleFilter, ['user', 'manager'], true)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

$filename = 'smartq-users-' . ($roleFilter ?: 'all') . '-' . date('Y-m-d-H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Birth Date', 'Role', 'Joined']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_map('smartq_csv_cell', [
        $row['id'],
        $row['full_name'],
        $row['email'],
        $row['phone'] ?: '-',
        $row['birth_date'] ?: '-',
        ucfirst($row['role']),
        $row['created_at']
    ]));
}

fclose($output);
exit();
