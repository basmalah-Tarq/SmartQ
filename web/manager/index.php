<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('manager');

$managerId = (int)$user['id'];

$placesCount = (int)$conn->query("SELECT COUNT(*) c FROM places WHERE manager_id = $managerId")->fetch_assoc()['c'];
$schedulesCount = (int)$conn->query("
    SELECT COUNT(*) c
    FROM schedules s
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = $managerId
")->fetch_assoc()['c'];

$bookingsCount = (int)$conn->query("
    SELECT COUNT(*) c
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = $managerId
")->fetch_assoc()['c'];

$waitingCount = (int)$conn->query("
    SELECT COUNT(*) c
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = $managerId
      AND a.status = 'waiting'
")->fetch_assoc()['c'];

$recentBookings = $conn->query("
    SELECT 
        a.customer_name,
        a.customer_phone,
        a.queue_number,
        a.status,
        p.place_name,
        s.schedule_date,
        s.start_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = $managerId
    ORDER BY a.created_at DESC
    LIMIT 5
");

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.dash.title') ?></title>
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
    <div class="manager-shell">
        <div class="manager-topbar workspace-hero">
            <div>
                <span class="dashboard-kicker"><?= t('manager.dash.kicker') ?></span>
                <h1><?= t('manager.dash.heading') ?></h1>
                <p><?= t('manager.dash.intro') ?></p>
            </div>
            <div class="workspace-hero-badge">
                <i class="fa-solid fa-briefcase"></i>
                <div><strong><?= t('manager.dash.badge_title') ?></strong><span><?= t('manager.dash.badge_text') ?></span></div>
            </div>
        </div>

        <div class="manager-stats-grid">
            <div class="manager-stat-card">
                <div class="manager-stat-head">
                    <span><?= t('manager.dash.total_places') ?></span>
                    <i class="fa-solid fa-hospital"></i>
                </div>
                <div class="manager-stat-number" data-count="<?= $placesCount ?>"><?= $placesCount ?></div>
                <div class="manager-stat-note"><?= t('manager.dash.total_places_note') ?></div>
            </div>

            <div class="manager-stat-card">
                <div class="manager-stat-head">
                    <span><?= t('manager.dash.total_schedules') ?></span>
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="manager-stat-number" data-count="<?= $schedulesCount ?>"><?= $schedulesCount ?></div>
                <div class="manager-stat-note"><?= t('manager.dash.total_schedules_note') ?></div>
            </div>

            <div class="manager-stat-card">
                <div class="manager-stat-head">
                    <span><?= t('manager.dash.total_bookings') ?></span>
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="manager-stat-number" data-count="<?= $bookingsCount ?>"><?= $bookingsCount ?></div>
                <div class="manager-stat-note"><?= t('manager.dash.total_bookings_note') ?></div>
            </div>

            <div class="manager-stat-card">
                <div class="manager-stat-head">
                    <span><?= t('manager.dash.waiting') ?></span>
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="manager-stat-number" data-count="<?= $waitingCount ?>"><?= $waitingCount ?></div>
                <div class="manager-stat-note"><?= t('manager.dash.waiting_note') ?></div>
            </div>
        </div>

        <div class="manager-actions">
            <a href="add-place.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <?= t('manager.dash.manage_places') ?>
            </a>

            <a href="add-schedule.php" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-plus"></i>
                <?= t('manager.add_schedule') ?>
            </a>

            <a href="bookings.php" class="btn btn-secondary">
                <i class="fa-solid fa-list-check"></i>
                <?= t('manager.view_bookings') ?>
            </a>

        </div>

        <div class="table-card">
            <h3><?= t('manager.dash.recent') ?></h3>
            <div class="manager-note"><?= t('manager.dash.recent_note') ?></div>

            <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
                <div class="table-wrap">
                    <table class="manager-table">
                        <thead>
                            <tr>
                                <th><?= t('manager.th.customer') ?></th>
                                <th><?= t('manager.th.phone') ?></th>
                                <th><?= t('manager.th.place') ?></th>
                                <th><?= t('manager.th.date') ?></th>
                                <th><?= t('manager.th.time') ?></th>
                                <th><?= t('manager.th.queue') ?></th>
                                <th><?= t('manager.th.status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($booking['customer_name']) ?></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($booking['customer_phone']) ?></span></td>
                                    <td><?= htmlspecialchars($booking['place_name']) ?></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($booking['schedule_date']) ?></span></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars(substr($booking['start_time'], 0, 5)) ?></span></td>
                                    <td><span class="sq-ltr">#<?= (int)$booking['queue_number'] ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($booking['status']) ?>">
                                            <?= t_status($booking['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    <h3><?= t('manager.dash.empty_title') ?></h3>
                    <p><?= t('manager.dash.empty_text') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>