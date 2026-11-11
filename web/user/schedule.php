<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = current_user_or_guest();   // guests may see schedules; booking asks them to log in
$isGuest = $user === null;
$placeId = (int)($_GET['place_id'] ?? 0);

if ($placeId <= 0) {
    header("Location: places.php");
    exit();
}

$placeStmt = $conn->prepare("SELECT * FROM places WHERE id = ? AND active = 1 LIMIT 1");
$placeStmt->bind_param("i", $placeId);
$placeStmt->execute();
$placeResult = $placeStmt->get_result();
$place = $placeResult->fetch_assoc();

if (!$place) {
    header("Location: places.php");
    exit();
}

$scheduleStmt = $conn->prepare("
    SELECT s.*,
           (
             SELECT COUNT(*) 
             FROM appointments a 
             WHERE a.schedule_id = s.id AND a.status IN ('waiting','confirmed','done')
           ) AS booked_count
    FROM schedules s
    WHERE s.place_id = ?
      AND s.status = 'open'
      AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
    ORDER BY s.schedule_date ASC, s.start_time ASC
");
$scheduleStmt->bind_param("i", $placeId);
$scheduleStmt->execute();
$schedules = $scheduleStmt->get_result();

$activePage = 'places';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.sched.title') ?></title>
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

<div class="container sqm-sched">
    <a href="places.php" class="sqm-backbtn sqm-backbtn--float" aria-label="<?= t('m.back') ?>"><?= sqm_icon('back', 'sqm-flip') ?></a>
    <nav class="sq-crumbs" aria-label="<?= t('user.crumbs') ?>">
        <a href="places.php"><?= t('user.crumb_places') ?></a> <i class="fa-solid fa-chevron-right"></i>
        <span><?= htmlspecialchars($place['place_name']) ?></span>
    </nav>

    <div class="sq-place-hero">
        <?php if (!empty($place['place_image'])): ?>
            <img src="../uploads/places/<?= htmlspecialchars($place['place_image']) ?>" alt="<?= htmlspecialchars($place['place_name']) ?>">
        <?php else: ?>
            <img src="../img/place-placeholder.svg" alt="">
        <?php endif; ?>
        <div>
            <span class="place-badge"><?= t_cat($place['category']) ?></span>
            <h1><?= htmlspecialchars($place['place_name']) ?></h1>
            <span class="sqm-open-pill"><i></i> <?= t('m.booking_open') ?></span>
            <p><i class="fa-solid fa-location-dot" style="color:var(--accent)"></i> <?= htmlspecialchars($place['city']) ?><?php if (!empty($place['address'])): ?> · <?= htmlspecialchars($place['address']) ?><?php endif; ?></p>
            <?php if (!empty($place['description'])): ?><p class="sqm-place-desc"><?= htmlspecialchars($place['description']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="sq-steps-bar" aria-label="<?= t('user.steps') ?>">
        <span class="is-done"><b><i class="fa-solid fa-check"></i></b> <?= t('user.step_place') ?></span><hr>
        <span class="is-current"><b>2</b> <?= t('user.step_schedule') ?></span><hr>
        <span><b>3</b> <?= t('user.step_slot') ?></span><hr>
        <span><b>4</b> <?= t('user.step_ticket') ?></span>
    </div>

    <div class="sqm-sechead sqm-sechead--day"><div><h2><?= t('m.choose_day') ?></h2><p><?= t('m.choose_day_sub') ?></p></div><span class="sqm-countpill"><?= t('m.days', ['n' => $schedules->num_rows]) ?></span></div>

    <div class="schedule-grid">
        <?php if ($schedules->num_rows > 0): ?>
            <?php while ($schedule = $schedules->fetch_assoc()): ?>
                <?php
                    $remaining = (int)$schedule['capacity'] - (int)$schedule['booked_count'];
                    $capacity = max(1, (int)$schedule['capacity']);
                    $fillPct = min(100, round(((int)$schedule['booked_count'] / $capacity) * 100));
                    $dateTs = strtotime($schedule['schedule_date']);
                ?>

                <article class="schedule-card">
                    <div class="schedule-card-top">
                        <div class="sq-date-tile">
                            <div class="sq-date-tile__cal"><em class="sqm-wd"><?= htmlspecialchars(sq_date('D', $dateTs)) ?></em><small><?= htmlspecialchars(sq_date('M', $dateTs)) ?></small><b><?= date('d', $dateTs) ?></b></div>
                            <div>
                                <strong><span class="sqm-d"><?= htmlspecialchars(sq_date('l', $dateTs)) ?></span><span class="sqm-m"><?= sqm_day_label($schedule['schedule_date']) ?></span></strong>
                                <span><i class="fa-solid fa-clock"></i> <span class="sq-ltr"><?= htmlspecialchars(substr($schedule['start_time'], 0, 5)) ?> – <?= htmlspecialchars(substr($schedule['end_time'], 0, 5)) ?></span></span>
                            </div>
                        </div>
                        <span class="schedule-remaining <?= $remaining > 0 ? 'available' : 'full' ?>">
                            <?= $remaining > 0 ? t('user.sched.left', ['n' => $remaining]) : t('user.full') ?>
                        </span>
                    </div>

                    <div class="sq-seats">
                        <span class="sqm-seatsleft<?= $remaining > 0 ? '' : ' is-full' ?>"><?= $remaining > 0 ? t('m.seats_left', ['n' => $remaining, 'c' => (int)$schedule['capacity']]) : t('user.sched.fully_booked') ?></span>
                        <div class="sq-seats__label"><span><?= t('user.sched.booked') ?></span><b class="sq-ltr"><?= (int)$schedule['booked_count'] ?> / <?= (int)$schedule['capacity'] ?></b></div>
                        <div class="sq-seats__bar <?= $fillPct >= 80 ? 'is-low' : '' ?>"><i style="--w: <?= max(4, $fillPct) ?>%"></i></div>
                    </div>

                    <div class="place-actions">
                        <?php if ($remaining > 0): ?>
                            <a href="booking.php?schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-primary" style="flex:1">
                                <?= t('user.choose_time') ?> <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn disabled" disabled style="flex:1"><?= t('user.sched.fully_booked') ?></button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-box">
                <h3><?= t('user.sched.empty') ?></h3>
                <p><?= t('user.sched.empty_note') ?></p>
                <a href="places.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> <?= t('user.back_places') ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
