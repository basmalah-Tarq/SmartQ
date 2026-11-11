<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

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
$customers = $stmt->get_result();

$totalCustomers = $customers->num_rows;
$generatedAt = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="<?= smartq_lang() ?>" dir="<?= smartq_is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.pdf.customers_title') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; color: #0f172a; background: #fff; padding: 30px; }
        .report-shell { max-width: 1000px; margin: 0 auto; }
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
        th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: start; font-size: 13px; }
        th { background: #EEF4FC; font-weight: 700; }
        tbody tr:nth-child(even) { background: #fafcff; }
        html[lang="ar"] body { font-family: 'IBM Plex Sans Arabic', Arial, sans-serif; }
        .sq-ltr { direction: ltr; unicode-bidi: isolate; }
        @media print { body { padding: 0; } .print-actions { display: none; } @page { size: A4; margin: 12mm; } }
    </style>
</head>
<body class="role-manager">
<div class="report-shell">
    <div class="print-actions">
        <button class="print-btn print-primary" onclick="window.print()"><?= t('manager.pdf.print') ?></button>
        <button class="print-btn print-secondary" onclick="window.close()"><?= t('common.close') ?></button>
    </div>
    <div class="report-header">
        <div><h1><?= t('manager.pdf.customers_title') ?></h1><p><?= t('manager.pdf.customers_intro') ?></p></div>
        <div class="report-badge"><?= t('manager.pdf.total_customers', ['n' => (int)$totalCustomers]) ?></div>
    </div>
    <div class="report-meta">
        <div class="meta-box"><span><?= t('manager.pdf.generated') ?></span><strong class="sq-ltr"><?= htmlspecialchars($generatedAt) ?></strong></div>
        <div class="meta-box"><span><?= t('manager.pdf.manager') ?></span><strong><?= htmlspecialchars($user['full_name'] ?? 'Manager') ?></strong></div>
    </div>
    <table>
        <thead><tr><th>#</th><th><?= t('manager.th.customer') ?></th><th><?= t('manager.th.phone') ?></th><th><?= t('manager.th.email') ?></th><th><?= t('manager.th.bookings_count') ?></th></tr></thead>
        <tbody>
        <?php if ($customers->num_rows > 0): ?>
            <?php $counter = 1; while ($row = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><span class="sq-ltr"><?= htmlspecialchars($row['customer_phone']) ?></span></td>
                    <td><span class="sq-ltr"><?= htmlspecialchars($row['customer_email']) ?></span></td>
                    <td><?= (int)$row['bookings_count'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5"><?= t('manager.pdf.no_customers') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 500); });</script>
</body>
</html>
