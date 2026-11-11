<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/categories.php';

$user = require_role('user');
$userId = (int)$user['id'];
$appointmentId = (int)($_GET['id'] ?? 0);

if ($appointmentId <= 0) {
    header("Location: appointment.php");
    exit();
}

// (appointment_time column is created by database/migration_001_integration.sql)

$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.queue_number,
        a.appointment_time,
        a.status,
        p.place_name,
        p.category,
        p.city,
        s.schedule_date,
        s.start_time,
        s.end_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE a.id = ? AND a.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $appointmentId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: appointment.php");
    exit();
}

$bookingTime = !empty($booking['appointment_time']) ? sq_time($booking['appointment_time']) : sq_time($booking['start_time']);
$rangeTime = sq_time($booking['start_time']) . ' - ' . sq_time($booking['end_time']);
$queueCode = 'Q-' . str_pad((string)$booking['queue_number'], 3, '0', STR_PAD_LEFT);

$activePage = 'appointments';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.done.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
    <?= smartq_head('../') ?>
</head>
<body class="role-user sqm-page-flow sqm-page-ticket">

<?php $sqHideTabs = true; include('partials/navbar.php'); ?>

<div class="container">
    <div class="sq-ticket-page" data-sq-confetti>
        <div class="sq-success">
            <div class="sq-check" aria-hidden="true">
                <svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg>
            </div>
            <h1><span class="sqm-d"><?= t('user.done.heading') ?></span><span class="sqm-m"><?= t('m.booked_title') ?></span></h1>
            <p><span class="sqm-d"><?= t('user.done.intro') ?></span><span class="sqm-m"><?= t('m.booked_text') ?></span></p>
        </div>

        <article class="sq-ticket" aria-label="<?= t('user.done.ticket') ?>">
            <div class="sq-ticket__head">
                <span class="sqm-thumb"><i class="<?= smartq_category_icon((string)$booking['category']) ?>"></i></span>
                <div>
                    <span class="place-badge"><?= t_cat($booking['category']) ?></span>
                    <h2><?= htmlspecialchars($booking['place_name']) ?></h2>
                    <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($booking['city']) ?></p>
                </div>
                <span class="sq-logo-mark"><img src="../img/logo-small.png" alt="SmartQ"></span>
            </div>

            <div class="sq-ticket__code">
                <small><span class="sqm-d"><?= t('user.done.number') ?></span><span class="sqm-m"><?= t('m.queue_number') ?></span></small>
                <strong class="sq-ltr"><?= htmlspecialchars($queueCode) ?></strong>
                <span class="status-badge status-<?= htmlspecialchars($booking['status']) ?>"><?= t_status($booking['status']) ?></span>
            </div>

            <div class="sq-ticket__cut"></div>

            <div class="sq-ticket__grid">
                <div><span><?= t('user.date') ?></span><b><?= htmlspecialchars(sq_date('D, M j, Y', strtotime($booking['schedule_date']))) ?></b></div>
                <div><span><?= t('user.done.your_time') ?></span><b><?= htmlspecialchars($bookingTime) ?></b></div>
                <div><span><?= t('user.done.range') ?></span><b><?= htmlspecialchars($rangeTime) ?></b></div>
                <div><span><?= t('user.done.id') ?></span><b class="sq-ltr">#<?= (int)$booking['id'] ?></b></div>
            </div>

            <div class="sq-ticket__barcode" aria-hidden="true"></div>
        </article>

        <div class="sq-ticket-actions">
            <a href="appointment.php" class="btn btn-primary">
                <i class="fa-solid fa-ticket"></i>
                <?= t('user.my_appointments') ?>
            </a>

            <a href="index.php" class="btn btn-secondary sqm-m-flex">
                <i class="fa-solid fa-house"></i>
                <?= t('m.back_home') ?>
            </a>

            <a href="places.php" class="btn btn-secondary sqm-d-flex">
                <i class="fa-solid fa-plus"></i>
                <?= t('user.done.another') ?>
            </a>
        </div>
    </div>
</div>

</body>
</html>
