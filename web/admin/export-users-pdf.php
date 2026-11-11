<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

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
$users = $stmt->get_result();

$totalUsers = $users->num_rows;
$generatedAt = date('Y-m-d H:i:s');
$exportType = $roleFilter ? ucfirst($roleFilter) . ' Accounts' : 'All Users & Managers';
if ($roleFilter === 'user' || $roleFilter === 'manager') {
    $exportTypeView = t('admin.pdfu.type_' . $roleFilter);
} elseif ($roleFilter) {
    $exportTypeView = htmlspecialchars($exportType);
} else {
    $exportTypeView = t('admin.pdfu.type_all');
}
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.pdfu.title') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, 'IBM Plex Sans Arabic', sans-serif; color: #0f172a; background: #fff; padding: 30px; }
        .report-shell { max-width: 1100px; margin: 0 auto; }
        .print-actions { display: flex; gap: 12px; margin-bottom: 22px; }
        .print-btn { border: 0; border-radius: 999px; padding: 12px 18px; cursor: pointer; font-weight: 700; font-size: 14px; }
        .print-primary { background: #1F6FE0; color: #fff; }
        .print-secondary { background: #e2e8f0; color: #0f172a; }
        .report-header { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 2px solid #e2e8f0; }
        h1 { font-size: 30px; margin-bottom: 8px; }
        p { color: #64748b; font-size: 14px; line-height: 1.6; }
        .report-badge { padding: 12px 16px; border-radius: 14px; background: #EEF4FC; color: #12306B; font-weight: 700; min-width: 170px; text-align: center; }
        .report-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 24px; }
        .meta-box { padding: 14px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .meta-box span { display: block; font-size: 12px; color: #64748b; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: start; font-size: 12px; }
        th { background: #EEF4FC; font-weight: 700; }
        tbody tr:nth-child(even) { background: #fafcff; }
        .role { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .role-user { background: #dcfce7; color: #15803d; }
        .role-manager { background: #E3EBF8; color: #12306B; }
        @media print { body { padding: 0; } .print-actions { display: none; } @page { size: A4 landscape; margin: 12mm; } }
    </style>
</head>
<body class="role-admin">
<div class="report-shell">
    <div class="print-actions">
        <button class="print-btn print-primary" onclick="window.print()"><?= t('admin.pdf.print') ?></button>
        <button class="print-btn print-secondary" onclick="window.close()"><?= t('common.close') ?></button>
    </div>
    <div class="report-header">
        <div><h1><?= t('admin.pdfu.title') ?></h1><p><?= t('admin.pdfu.subtitle') ?></p></div>
        <div class="report-badge"><?= t('admin.pdfu.total', ['n' => (int)$totalUsers]) ?></div>
    </div>
    <div class="report-meta">
        <div class="meta-box"><span><?= t('admin.pdf.generated_at') ?></span><strong dir="ltr"><?= htmlspecialchars($generatedAt) ?></strong></div>
        <div class="meta-box"><span><?= t('admin.pdf.export_type') ?></span><strong><?= $exportTypeView ?></strong></div>
    </div>
    <table>
        <thead><tr><th>#</th><th><?= t('admin.th.full_name') ?></th><th><?= t('admin.th.email') ?></th><th><?= t('admin.th.phone') ?></th><th><?= t('admin.th.birth_date') ?></th><th><?= t('admin.th.role') ?></th><th><?= t('admin.th.joined') ?></th></tr></thead>
        <tbody>
        <?php if ($users->num_rows > 0): ?>
            <?php $counter = 1; while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><span dir="ltr"><?= htmlspecialchars($row['email']) ?></span></td>
                    <td><span dir="ltr"><?= htmlspecialchars($row['phone'] ?: '-') ?></span></td>
                    <td><span dir="ltr"><?= htmlspecialchars($row['birth_date'] ?: '-') ?></span></td>
                    <td><span class="role role-<?= htmlspecialchars($row['role']) ?>"><?= in_array($row['role'], ['user', 'manager'], true) ? t('admin.role.' . $row['role']) : htmlspecialchars(ucfirst($row['role'])) ?></span></td>
                    <td><span dir="ltr"><?= htmlspecialchars($row['created_at']) ?></span></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7"><?= t('admin.pdfu.none') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 500); });</script>
</body>
</html>
