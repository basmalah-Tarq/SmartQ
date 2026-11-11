<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';
require_once '../config/booking.php';

$user = require_role('manager');

$managerId = (int)$user['id'];
$scheduleFilter = (int)($_GET['schedule_id'] ?? ($_POST['schedule_id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'status') {
    verify_csrf();
    try {
        // Same rules as the admin panel (config/booking.php); only this manager's bookings.
        smartq_set_appointment_status($conn, (int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''), $managerId);
    } catch (BookingException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    $redirect = "bookings.php";
    if ($scheduleFilter > 0) {
        $redirect .= "?schedule_id=" . $scheduleFilter;
    }

    header("Location: " . $redirect);
    exit();
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

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
    WHERE p.manager_id = ?
";

$params = [$managerId];
$types = "i";

if ($scheduleFilter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $scheduleFilter;
    $types .= "i";
}

$sql .= " ORDER BY s.schedule_date DESC, COALESCE(a.appointment_time, s.start_time) ASC, a.queue_number ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();

$activePage = 'bookings';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.bookings.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
    <?= smartq_head('../') ?>
</head>
<body class="role-manager">

<?php include('partials/navbar.php'); ?>

<div class="container">
    <div class="page-header page-header-actions">
        <div>
            <h1><?= t('manager.bookings.heading') ?></h1>
            <p><?= t('manager.bookings.intro') ?></p>
        </div>

        <div class="export-actions">
            <?php $exportQuery = $scheduleFilter > 0 ? '?schedule_id=' . $scheduleFilter : ''; ?>
            <a href="export-bookings-csv.php<?= $exportQuery ?>" class="btn btn-secondary">
                <i class="fa-solid fa-file-csv"></i>
                <?= t('manager.export_excel') ?>
            </a>
            <a href="export-bookings-pdf.php<?= $exportQuery ?>" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-file-pdf"></i>
                <?= t('manager.export_pdf') ?>
            </a>
        </div>
    </div>

    <?php if ($flashError): ?>
        <div class="alert error-alert"><?= htmlspecialchars(t_msg($flashError)) ?></div>
    <?php endif; ?>

    <?php if ($bookings->num_rows > 0): ?>
        <div class="table-card">
            <div class="table-wrap">
                <table class="manager-table">
                    <thead>
                        <tr>
                            <th><?= t('manager.th.customer') ?></th>
                            <th><?= t('manager.th.phone') ?></th>
                            <th><?= t('manager.th.email') ?></th>
                            <th><?= t('manager.th.place') ?></th>
                            <th><?= t('manager.th.date') ?></th>
                            <th><?= t('manager.th.time') ?></th>
                            <th><?= t('manager.th.queue') ?></th>
                            <th><?= t('manager.th.status') ?></th>
                            <th><?= t('manager.th.notes') ?></th>
                            <th><?= t('manager.th.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $bookings->fetch_assoc()): ?>
                            <?php
                                $filterParam = $scheduleFilter > 0 ? '&schedule_id=' . $scheduleFilter : '';
                                $bookingId = (int)$row['id'];
                                $status = $row['status'];
                            ?>
                            <tr>
                                <td>
                                    <div class="customer-mini">
                                        <?php if (!empty($row['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars(getProfileImageUrl($row['profile_image'] ?? '')) ?>" alt="<?= t('manager.customer_alt') ?>" class="customer-mini-img">
                                        <?php else: ?>
                                            <div class="customer-mini-avatar"><?= smartq_initial($row['customer_name']) ?></div>
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
                                <td><?= !empty($row['appointment_time']) ? htmlspecialchars(sq_time($row['appointment_time'])) : '<span class="sq-ltr">' . htmlspecialchars(substr($row['start_time'], 0, 5)) . ' - ' . htmlspecialchars(substr($row['end_time'], 0, 5)) . '</span>' ?></td>
                                <td><span class="sq-ltr">#<?= (int)$row['queue_number'] ?></span></td>

                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                                        <?= t_status($status) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['notes'] ?: '-') ?></td>

                                <td>
                                    <div class="table-actions">

                                        <?php if ($status !== 'confirmed' && $status !== 'done' && $status !== 'cancelled' && $status !== 'missed'): ?>
                                            <form method="POST" action="bookings.php" class="inline-form">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <input type="hidden" name="schedule_id" value="<?= $scheduleFilter ?>">
                                                <button type="submit" class="btn btn-primary btn-sm"><?= t('manager.bookings.confirm') ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status !== 'done' && $status !== 'cancelled' && $status !== 'missed'): ?>
                                            <form method="POST" action="bookings.php" class="inline-form">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="status" value="done">
                                                <input type="hidden" name="schedule_id" value="<?= $scheduleFilter ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm"><?= t('manager.bookings.done') ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status !== 'missed' && $status !== 'done' && $status !== 'cancelled'): ?>
                                            <form method="POST" action="bookings.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('manager.bookings.confirm_missed'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="status" value="missed">
                                                <input type="hidden" name="schedule_id" value="<?= $scheduleFilter ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm"><?= t('manager.bookings.missed') ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status !== 'cancelled' && $status !== 'done' && $status !== 'missed'): ?>
                                            <form method="POST" action="bookings.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('manager.bookings.confirm_cancel'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <input type="hidden" name="schedule_id" value="<?= $scheduleFilter ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm"><?= t('manager.bookings.cancel') ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" action="delete-booking.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('manager.bookings.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                            <button type="submit" class="btn danger-btn btn-sm"><?= t('manager.delete') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3><?= t('manager.bookings.empty_title') ?></h3>
            <p><?= t('manager.bookings.empty_text') ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>