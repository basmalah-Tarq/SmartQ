<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';

$user = require_role('admin');

$scheduleFilter = (int)($_GET['schedule_id'] ?? 0);

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
$statusUpdated = ($_GET['success'] ?? '') === 'status_updated';

$sql = "
    SELECT
        a.id,
        a.appointment_time,
        a.customer_name,
        a.customer_phone,
        a.customer_email,
        a.queue_number,
        a.status,
        a.notes,
        p.place_name,
        s.schedule_date,
        s.start_time,
        s.end_time,
        u.profile_image
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    LEFT JOIN users u ON a.user_id = u.id
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

$activePage = 'bookings';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.bookings.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
<?= smartq_head('../') ?>
</head>
<body class="role-admin">

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container">
    <div class="admin-shell">
        <div class="admin-topbar">
            <div>
                <h1><?= t('admin.bookings.heading') ?></h1>
                <p><?= t('admin.bookings.intro') ?></p>
            </div>

            <div class="admin-export-actions">
                <a href="export-bookings-csv.php<?= $scheduleFilter > 0 ? '?schedule_id=' . $scheduleFilter : '' ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-file-csv"></i>
                    <?= t('admin.export_csv') ?>
                </a>

                <a href="export-bookings-pdf.php<?= $scheduleFilter > 0 ? '?schedule_id=' . $scheduleFilter : '' ?>" target="_blank" class="btn btn-primary">
                    <i class="fa-solid fa-file-pdf"></i>
                    <?= t('admin.export_pdf') ?>
                </a>
            </div>
        </div>

        <?php if ($flashError): ?>
            <div class="alert error-alert"><?= htmlspecialchars(t_msg($flashError)) ?></div>
        <?php elseif ($statusUpdated): ?>
            <div class="alert success-alert"><?= t('admin.bookings.status_updated') ?></div>
        <?php endif; ?>

        <?php if ($bookings->num_rows > 0): ?>
            <div class="table-card">
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= t('admin.th.customer') ?></th>
                                <th><?= t('admin.th.phone') ?></th>
                                <th><?= t('admin.th.email') ?></th>
                                <th><?= t('admin.th.place') ?></th>
                                <th><?= t('admin.th.date') ?></th>
                                <th><?= t('admin.th.time') ?></th>
                                <th><?= t('admin.th.queue') ?></th>
                                <th><?= t('admin.th.status') ?></th>
                                <th><?= t('admin.th.notes') ?></th>
                                <th><?= t('admin.th.delete') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="admin-user-mini">
                                            <?php if (!empty($row['profile_image'])): ?>
                                                <img src="<?= htmlspecialchars(getProfileImageUrl($row['profile_image'] ?? '')) ?>" alt="<?= t('admin.bookings.img_alt') ?>" class="admin-user-mini-img">
                                            <?php else: ?>
                                                <div class="admin-user-mini-avatar"><?= smartq_initial($row['customer_name']) ?></div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($row['customer_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($row['customer_phone']) ?></span></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($row['customer_email']) ?>" class="table-mail-link" dir="ltr">
                                            <?= htmlspecialchars($row['customer_email']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($row['place_name']) ?></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($row['schedule_date']) ?></span></td>
                                    <td><span class="sq-ltr"><?= !empty($row['appointment_time']) ? htmlspecialchars(sq_time($row['appointment_time'])) : htmlspecialchars(substr($row['start_time'], 0, 5)) . ' - ' . htmlspecialchars(substr($row['end_time'], 0, 5)) ?></span></td>
                                    <td><span class="sq-ltr">#<?= (int)$row['queue_number'] ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($row['status']) ?>">
                                            <?= t_status($row['status']) ?>
                                        </span>
                                        <form method="POST" action="update_booking_status.php" class="inline-form" style="margin-block-start:6px">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="schedule_id" value="<?= (int)$scheduleFilter ?>">
                                            <select name="status" aria-label="<?= t('admin.bookings.change_status') ?>" onchange="this.form.submit()">
                                                <?php foreach (['waiting', 'confirmed', 'done', 'missed', 'cancelled'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= t_status($st) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <noscript><button type="submit" class="btn btn-sm"><?= t('common.save') ?></button></noscript>
                                        </form>
                                    </td>
                                    <td><?= htmlspecialchars($row['notes'] ?: '-') ?></td>
                                    <td>
                                        <form method="POST" action="delete-booking.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('admin.bookings.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="booking_id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="btn danger-btn btn-sm">
                                                <i class="fa-solid fa-trash"></i>
                                                <?= t('admin.delete') ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-box">
                <h3><?= t('admin.bookings.empty_title') ?></h3>
                <p><?= t('admin.bookings.empty_text') ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
