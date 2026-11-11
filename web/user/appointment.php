<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/categories.php';

$user = require_role('user');
$userId = (int)$user['id'];

$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.queue_number,
        a.customer_name,
        a.customer_phone,
        a.customer_email,
        a.customer_age,
        a.notes,
        a.status,
        a.appointment_time,
        p.place_name,
        p.category,
        p.city,
        s.schedule_date,
        s.start_time,
        s.end_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE a.user_id = ?
    ORDER BY s.schedule_date DESC, COALESCE(a.appointment_time, s.start_time) DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
// phone layout: Upcoming / Past tabs (same rule as the cancel button below)
$upCount = 0;
foreach ($rows as $r) {
    if (in_array($r['status'], ['waiting', 'confirmed'], true) && strtotime($r['schedule_date'] . ' ' . $r['end_time']) >= time()) $upCount++;
}
$pastCount = count($rows) - $upCount;

$success = $_GET['success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
$activePage = 'appointments';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.appt.title') ?></title>

    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>

    <?= smartq_head('../') ?>
</head>
<body class="role-user">

<?php include('partials/navbar.php'); ?>

<div class="container sqm-appts">
    <div class="sqm-pagehead">
        <h1><?= t('m.my_bookings') ?></h1>
        <p><?= t('m.upcoming_count', ['n' => $upCount]) ?></p>
    </div>
    <div class="sqm-seg" role="tablist" data-sqm-seg>
        <button type="button" role="tab" data-when="upcoming" aria-selected="true" class="is-active"><?= t('m.tab_upcoming', ['n' => $upCount]) ?></button>
        <button type="button" role="tab" data-when="past" aria-selected="false"><?= t('m.tab_past', ['n' => $pastCount]) ?></button>
    </div>
    <div class="page-header compact-header">
        <div>
            <span class="dashboard-kicker"><i class="fa-solid fa-ticket"></i> <?= t('user.appt.kicker') ?></span>
            <h1><?= t('user.appt.heading') ?></h1>
            <p><?= t('user.appt.intro') ?></p>
        </div>

        <a href="places.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i>
            <?= t('user.appt.new') ?>
        </a>
    </div>

    <?php if ($success === 'cancelled'): ?>
        <div class="alert success-alert" data-sq-toast="<?= t('user.appt.cancelled_toast') ?>">
            <?= t('user.appt.cancelled') ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert error-alert"><?= htmlspecialchars(t_msg($flashError)) ?></div>
    <?php endif; ?>

    <div class="appointments-list">
        <?php if ($result->num_rows > 0): ?>
            <?php foreach ($rows as $row): ?>
                <?php
                    $status = $row['status'];

                    if ($status === 'missed') {
                        $statusText = t('user.appt.status_missed');
                    } elseif ($status === 'done') {
                        $statusText = t('user.appt.status_done');
                    } else {
                        $statusText = t_status($status);
                    }

                    $canCancel = in_array($status, ['waiting', 'confirmed'], true)
                        && strtotime($row['schedule_date'] . ' ' . $row['end_time']) >= time();
                    $timeLabel = !empty($row['appointment_time'])
                        ? htmlspecialchars(sq_time($row['appointment_time']))
                        : '<span class="sq-ltr">' . htmlspecialchars(substr($row['start_time'], 0, 5) . ' – ' . substr($row['end_time'], 0, 5)) . '</span>';
                ?>

                <div class="appointment-card" data-status="<?= htmlspecialchars($status) ?>" data-when="<?= $canCancel ? 'upcoming' : 'past' ?>">
                    <div class="appointment-top">
                        <span class="sqm-thumb"><i class="<?= smartq_category_icon((string)$row['category']) ?>"></i></span>
                        <div>
                            <h3><?= htmlspecialchars($row['place_name']) ?></h3>

                            <div class="appointment-meta-line">
                                <span class="place-badge">
                                    <?= t_cat($row['category']) ?>
                                </span>

                                <span class="meta-chip">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($row['city']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="status-badge status-<?= htmlspecialchars($status) ?>">
                            <?= $statusText ?>
                        </div>
                    </div>

                    <div class="appointment-details">
                        <div class="detail-box highlight-box">
                            <span><span class="sqm-d"><?= t('user.appt.queue') ?></span><span class="sqm-m"><?= t('m.queue_no') ?></span></span>
                            <strong class="sq-ltr">Q-<?= str_pad((string)(int)$row['queue_number'], 3, '0', STR_PAD_LEFT) ?></strong>
                        </div>

                        <div class="detail-box">
                            <span><?= t('user.date') ?></span>
                            <strong><span class="sqm-d sq-ltr"><?= htmlspecialchars($row['schedule_date']) ?></span><span class="sqm-m"><?= sqm_day_label($row['schedule_date']) ?></span></strong>
                        </div>

                        <div class="detail-box">
                            <span><?= t('user.time') ?></span>
                            <strong>
                                <?= $timeLabel ?>
                            </strong>
                        </div>

                        <div class="detail-box">
                            <span><?= t('user.appt.booked_for') ?></span>
                            <strong><?= htmlspecialchars($row['customer_name']) ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($row['notes'])): ?>
                        <div class="notes-box">
                            <span><?= t('user.appt.notes') ?></span>
                            <p><?= htmlspecialchars($row['notes']) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="appointment-actions">
                        <?php if ($canCancel): ?>
                            <form method="POST" action="delete-appointment.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('user.appt.confirm_cancel'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                <?= csrf_input() ?>
                                <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn danger-btn">
                                    <i class="fa-solid fa-trash sqm-d-inline"></i><i class="fa-solid fa-xmark sqm-m-inline"></i>
                                    <span class="sqm-d"><?= t('user.appt.cancel') ?></span><span class="sqm-m"><?= t('m.cancel_booking') ?></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn disabled sqm-d-flex" disabled>
                                <?= t('user.appt.no_actions') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-box">
                <h3><?= t('user.appt.empty') ?></h3>
                <p><?= t('user.appt.empty_note') ?></p>

                <a href="places.php" class="btn btn-primary">
                    <?= t('user.appt.book_now') ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="sqm-segempty" data-empty="upcoming" hidden><?= sqm_icon('ticket') ?><b><?= t('m.no_upcoming') ?></b><a href="places.php" class="sqm-btn sqm-btn--primary"><?= t('m.qa_book') ?></a></div>
        <div class="sqm-segempty" data-empty="past" hidden><?= sqm_icon('history') ?><b><?= t('m.no_past') ?></b></div>
    </div>
</div>

<script>
/* Phone: Upcoming / Past tabs (desktop shows every booking) */
(function () {
    var seg = document.querySelector('[data-sqm-seg]');
    if (!seg) return;
    var cards = document.querySelectorAll('.appointment-card[data-when]');
    function show(when) {
        var n = 0;
        seg.querySelectorAll('button').forEach(function (b) { var on = b.dataset.when === when; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
        cards.forEach(function (c) { var on = c.dataset.when === when; c.classList.toggle('sqm-off', !on); if (on) n++; });
        document.querySelectorAll('.sqm-segempty').forEach(function (e) { e.hidden = !(cards.length && e.dataset.empty === when && n === 0); });
    }
    seg.addEventListener('click', function (e) { var b = e.target.closest('button[data-when]'); if (b) show(b.dataset.when); });
    show(<?= ($upCount === 0 && $pastCount > 0) ? "'past'" : "'upcoming'" ?>);
})();
</script>

</body>
</html>
