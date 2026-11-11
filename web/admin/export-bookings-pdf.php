<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('admin');

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
    WHERE 1=1
";

$params = [];
$types = '';

if ($scheduleFilter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $scheduleFilter;
    $types .= 'i';
}

$sql .= " ORDER BY s.schedule_date DESC, s.start_time DESC, a.queue_number ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result();

$totalBookings = $bookings->num_rows;
$generatedAt = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.pdfb.title') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, 'IBM Plex Sans Arabic', sans-serif;
            color: #0f172a;
            background: #ffffff;
            padding: 30px;
        }

        .report-shell {
            max-width: 1100px;
            margin: 0 auto;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 2px solid #e2e8f0;
        }

        .report-title h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .report-title p {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
        }

        .report-badge {
            padding: 12px 16px;
            border-radius: 14px;
            background: #EEF4FC;
            color: #12306B;
            font-weight: 700;
            font-size: 14px;
            min-width: 170px;
            text-align: center;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .meta-box {
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .meta-box span {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .meta-box strong {
            font-size: 15px;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: start;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #EEF4FC;
            color: #0f172a;
            font-weight: 700;
        }

        tbody tr:nth-child(even) {
            background: #fafcff;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-waiting {
            background: #fef3c7;
            color: #b45309;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #15803d;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-done {
            background: #E3EBF8;
            color: #12306B;
        }

        .print-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 22px;
        }

        .print-btn {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
        }

        .print-primary {
            background: #1F6FE0;
            color: #fff;
        }

        .print-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .report-shell {
                max-width: 100%;
            }

            @page {
                size: A4 landscape;
                margin: 12mm;
            }
        }
    </style>
</head>
<body class="role-admin">

<div class="report-shell">
    <div class="print-actions">
        <button class="print-btn print-primary" onclick="window.print()"><?= t('admin.pdf.print') ?></button>
        <button class="print-btn print-secondary" onclick="window.close()"><?= t('common.close') ?></button>
    </div>

    <div class="report-header">
        <div class="report-title">
            <h1><?= t('admin.pdfb.title') ?></h1>
            <p><?= t('admin.pdfb.subtitle') ?></p>
        </div>
        <div class="report-badge">
            <?= t('admin.pdfb.total', ['n' => (int)$totalBookings]) ?>
        </div>
    </div>

    <div class="report-meta">
        <div class="meta-box">
            <span><?= t('admin.pdf.generated_at') ?></span>
            <strong dir="ltr"><?= htmlspecialchars($generatedAt) ?></strong>
        </div>
        <div class="meta-box">
            <span><?= t('admin.pdf.export_type') ?></span>
            <strong><?= $scheduleFilter > 0 ? t('admin.pdfb.filtered') : t('admin.pdfb.all') ?></strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th><?= t('admin.th.customer') ?></th>
                <th><?= t('admin.th.phone') ?></th>
                <th><?= t('admin.th.email') ?></th>
                <th><?= t('admin.th.place') ?></th>
                <th><?= t('admin.th.date') ?></th>
                <th><?= t('admin.th.time') ?></th>
                <th><?= t('admin.th.queue') ?></th>
                <th><?= t('admin.th.status') ?></th>
                <th><?= t('admin.th.notes') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bookings->num_rows > 0): ?>
                <?php $counter = 1; ?>
                <?php while ($row = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><span dir="ltr"><?= htmlspecialchars($row['customer_phone']) ?></span></td>
                        <td><span dir="ltr"><?= htmlspecialchars($row['customer_email']) ?></span></td>
                        <td><?= htmlspecialchars($row['place_name']) ?></td>
                        <td><span dir="ltr"><?= htmlspecialchars($row['schedule_date']) ?></span></td>
                        <td><span dir="ltr"><?= !empty($row['appointment_time']) ? htmlspecialchars(substr($row['appointment_time'], 0, 5)) : htmlspecialchars(substr($row['start_time'], 0, 5)) . ' - ' . htmlspecialchars(substr($row['end_time'], 0, 5)) ?></span></td>
                        <td><span dir="ltr">#<?= (int)$row['queue_number'] ?></span></td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($row['status']) ?>">
                                <?= t_status($row['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['notes'] ?: '-') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10"><?= t('admin.pdfb.none') ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        window.print();
    }, 500);
});
</script>

</body>
</html>