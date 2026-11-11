<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

require_once '../config/categories.php';

// Customers see their dashboard; visitors without an account get the guest home (like the app).
$user = current_user_or_guest();
$isGuest = $user === null;
$userId = $isGuest ? 0 : (int)$user['id'];

/* Total bookings */
$totalStmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE user_id = ?");
$totalStmt->bind_param("i", $userId);
$totalStmt->execute();
$totalBookings = (int)$totalStmt->get_result()->fetch_assoc()['total'];

/* Active bookings (waiting/confirmed and the schedule has not ended yet) */
$activeStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    WHERE a.user_id = ?
      AND a.status IN ('waiting', 'confirmed')
      AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
");
$activeStmt->bind_param("i", $userId);
$activeStmt->execute();
$activeBookings = (int)$activeStmt->get_result()->fetch_assoc()['total'];

/* Next booking */
$nextStmt = $conn->prepare("
    SELECT
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
    WHERE a.user_id = ?
      AND a.status IN ('waiting', 'confirmed')
      AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
    ORDER BY s.schedule_date ASC, COALESCE(a.appointment_time, s.start_time) ASC
    LIMIT 1
");
$nextStmt->bind_param("i", $userId);
$nextStmt->execute();
$nextBooking = $nextStmt->get_result()->fetch_assoc();

/* Recent appointments for the dashboard activity panel */
$recentStmt = $conn->prepare("
    SELECT
        a.queue_number,
        a.status,
        p.place_name,
        p.category,
        s.schedule_date,
        COALESCE(a.appointment_time, s.start_time) AS start_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 4
");
$recentStmt->bind_param("i", $userId);
$recentStmt->execute();
$recentAppointments = $recentStmt->get_result();

/* Phone layout (app-like home): completed count + the next few upcoming turns */
$doneStmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE user_id = ? AND status = 'done'");
$doneStmt->bind_param("i", $userId);
$doneStmt->execute();
$doneBookings = (int)$doneStmt->get_result()->fetch_assoc()['total'];

$upStmt = $conn->prepare("
    SELECT a.queue_number, a.status, p.place_name, s.schedule_date,
           COALESCE(a.appointment_time, s.start_time) AS start_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    WHERE a.user_id = ?
      AND a.status IN ('waiting', 'confirmed')
      AND (s.schedule_date > CURDATE() OR (s.schedule_date = CURDATE() AND s.end_time >= CURTIME()))
    ORDER BY s.schedule_date ASC, COALESCE(a.appointment_time, s.start_time) ASC
    LIMIT 4
");
$upStmt->bind_param("i", $userId);
$upStmt->execute();
$upcomingList = $upStmt->get_result()->fetch_all(MYSQLI_ASSOC);
if ($nextBooking && $upcomingList) {
    array_shift($upcomingList); // the first one is already the big ticket
}
$homeCategories = smartq_categories($conn, true);

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.dash.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
    <?= smartq_head('../') ?>
</head>
<body class="role-user sqm-page-dash<?= $isGuest ? ' sqm-guest-home' : '' ?>">

<?php include('partials/navbar.php'); ?>

<?php /* ===== Phone home (<= 767px): same order and look as the Android app ===== */ ?>
<main class="sqm-home<?= $isGuest ? ' sqm-home--always' : '' ?>">
    <section class="sqm-hero">
        <?php if ($isGuest): ?>
            <h1 class="sqm-hero__title"><?= t('m.guest_title1') ?><br><?= t('m.guest_title2') ?></h1>
            <p class="sqm-hero__sub"><?= t('m.guest_sub') ?></p>
        <?php elseif ($nextBooking): ?>
            <?php
                $nbTime = !empty($nextBooking['appointment_time']) ? $nextBooking['appointment_time'] : $nextBooking['start_time'];
                $nbIsToday = $nextBooking['schedule_date'] === date('Y-m-d');
            ?>
            <a href="appointment.php" class="sqm-turn">
                <div class="sqm-turn__top">
                    <span class="sqm-turn__kicker"><?= t('m.next_turn') ?></span>
                    <span class="sqm-turn__pills">
                        <?php if ($nbIsToday): ?><span class="sqm-pill sqm-pill--gold"><?= t('m.today') ?></span><?php endif; ?>
                        <span class="sqm-pill sqm-pill--glass"><?= t_status($nextBooking['status']) ?></span>
                    </span>
                </div>
                <div class="sqm-turn__mid">
                    <strong class="sqm-turn__code" dir="ltr"><?= sqm_qcode($nextBooking['queue_number']) ?></strong>
                    <span class="sqm-turn__starts"><small><?= t('m.starts') ?></small><b><?= sqm_starts_in($nextBooking['schedule_date'] . ' ' . $nbTime) ?></b></span>
                </div>
                <div class="sqm-turn__cut"></div>
                <div class="sqm-turn__place">
                    <span class="sqm-turn__icon"><i class="<?= smartq_category_icon((string)$nextBooking['category']) ?>"></i></span>
                    <span><b><?= htmlspecialchars($nextBooking['place_name']) ?></b><small><?= t_cat($nextBooking['category']) ?> · <?= htmlspecialchars($nextBooking['city']) ?></small></span>
                </div>
                <div class="sqm-turn__when">
                    <span><?= sqm_icon('calendar') ?> <?= sqm_day_label($nextBooking['schedule_date']) ?></span>
                    <span><?= sqm_icon('clock') ?> <?= htmlspecialchars(sq_time($nbTime)) ?></span>
                </div>
            </a>
        <?php else: ?>
            <div class="sqm-turn sqm-turn--empty">
                <span class="sqm-turn__kicker"><?= t('m.next_turn') ?></span>
                <strong class="sqm-turn__emptytitle"><?= t('m.no_next_title') ?></strong>
                <p><?= t('m.no_next_text') ?></p>
                <a href="places.php" class="sqm-btn sqm-btn--light"><?= sqm_icon('search') ?> <?= t('m.qa_book') ?></a>
            </div>
        <?php endif; ?>
    </section>

    <form action="places.php" method="GET" class="sqm-search" role="search">
        <?= sqm_icon('search') ?>
        <input type="text" name="search" placeholder="<?= t('m.search_ph') ?>" aria-label="<?= t('shell.user.search_aria') ?>">
        <button type="submit" class="sqm-search__btn"><?= t('m.find') ?></button>
    </form>

    <?php if ($isGuest): ?>
        <section class="sqm-card sqm-guestcard">
            <svg class="sqm-queue-art" viewBox="0 0 320 120" aria-hidden="true">
                <line x1="8" y1="112" x2="312" y2="112" stroke="currentColor" stroke-opacity=".18" stroke-width="3" stroke-linecap="round"/>
                <g fill="#12306B"><circle cx="48" cy="40" r="10"/><rect x="36" y="56" width="24" height="56" rx="12"/></g>
                <g fill="#14A39A"><circle cx="92" cy="40" r="10"/><rect x="80" y="56" width="24" height="56" rx="12"/></g>
                <g fill="#12306B"><circle cx="136" cy="40" r="10"/><rect x="124" y="56" width="24" height="56" rx="12"/></g>
                <g fill="#1F6FE0"><circle cx="180" cy="40" r="10"/><rect x="168" y="56" width="24" height="56" rx="12"/><rect x="190" y="72" width="9" height="7" rx="2" fill="#F5B82E"/></g>
                <rect x="214" y="70" width="88" height="42" rx="3" fill="#1F6FE0" fill-opacity=".1"/>
                <rect x="208" y="62" width="100" height="10" rx="5" fill="#12306B"/>
                <rect x="252" y="30" width="4" height="34" fill="#12306B" fill-opacity=".25"/>
                <rect x="220" y="6" width="68" height="30" rx="6" fill="#0B1F45"/>
                <circle cx="228" cy="12" r="2.5" fill="#F5B82E"/><circle cx="280" cy="12" r="2.5" fill="#14A39A"/>
                <text x="254" y="28" text-anchor="middle" font-family="Plus Jakarta Sans, Inter, sans-serif" font-weight="800" font-size="15" fill="#fff">Q-041</text>
            </svg>
            <h2><?= t('m.guest_card_title') ?></h2>
            <p><?= t('m.guest_card_text') ?></p>
            <div class="sqm-row2">
                <a href="../login.php?guest=1&amp;next=<?= rawurlencode('user/index.php') ?>" class="sqm-btn sqm-btn--primary"><?= t('m.login') ?> <?= sqm_icon('arrow', 'sqm-flip') ?></a>
                <a href="places.php" class="sqm-btn sqm-btn--outline"><?= t('m.browse') ?></a>
            </div>
        </section>
    <?php else: ?>
        <div class="sqm-stats">
            <a href="appointment.php" class="sqm-stat"><span class="sqm-stat__ic"><?= sqm_icon('calendar') ?></span><b><?= $activeBookings ?></b><small><?= t('m.upcoming') ?></small></a>
            <a href="appointment.php" class="sqm-stat sqm-stat--ok"><span class="sqm-stat__ic"><?= sqm_icon('check') ?></span><b><?= $doneBookings ?></b><small><?= t('m.completed') ?></small></a>
            <a href="appointment.php" class="sqm-stat sqm-stat--teal"><span class="sqm-stat__ic"><?= sqm_icon('ticket') ?></span><b><?= $totalBookings ?></b><small><?= t('m.all_bookings') ?></small></a>
        </div>

        <?php if ($upcomingList): ?>
            <div class="sqm-sechead"><h2><?= t('m.coming_up') ?></h2><a href="appointment.php"><?= t('m.see_all') ?></a></div>
            <div class="sqm-list">
                <?php foreach ($upcomingList as $up): $upTs = strtotime($up['schedule_date']); ?>
                    <a href="appointment.php" class="sqm-li">
                        <span class="sqm-datetile"><b><?= date('j', $upTs) ?></b><small><?= htmlspecialchars(sq_date('M', $upTs)) ?></small></span>
                        <span class="sqm-li__txt"><b><?= htmlspecialchars($up['place_name']) ?></b><small><?= htmlspecialchars(sq_date('D, j M', $upTs)) ?> · <?= htmlspecialchars(sq_time($up['start_time'])) ?></small></span>
                        <span class="sqm-qpill" dir="ltr"><?= sqm_qcode($up['queue_number']) ?></span>
                        <?= sqm_icon('chev', 'sqm-flip sqm-li__chev') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="sqm-sechead"><h2><?= t('m.quick') ?></h2></div>
    <div class="sqm-qa">
        <a href="places.php" class="sqm-qa__item"><span class="sqm-qa__ic"><?= sqm_icon('search') ?></span><span><b><?= t('m.qa_book') ?></b><small><?= t('m.qa_book_sub') ?></small></span></a>
        <a href="<?= $isGuest ? '../login.php?guest=1&amp;next=' . rawurlencode('user/appointment.php') : 'appointment.php' ?>" class="sqm-qa__item sqm-qa__item--teal"><span class="sqm-qa__ic"><?= sqm_icon('ticket') ?></span><span><b><?= t('m.qa_bookings') ?></b><small><?= t('m.qa_bookings_sub') ?></small></span></a>
        <a href="<?= $isGuest ? '../login.php?guest=1&amp;next=' . rawurlencode('user/appointment.php') : 'appointment.php' ?>" class="sqm-qa__item"><span class="sqm-qa__ic"><?= sqm_icon('history') ?></span><span><b><?= t('m.qa_history') ?></b><small><?= t('m.qa_history_sub') ?></small></span></a>
        <a href="<?= $isGuest ? '../login.php?guest=1&amp;next=' . rawurlencode('user/profile.php') : 'profile.php' ?>" class="sqm-qa__item sqm-qa__item--teal"><span class="sqm-qa__ic"><?= sqm_icon('user') ?></span><span><b><?= t('m.qa_profile') ?></b><small><?= t('m.qa_profile_sub') ?></small></span></a>
    </div>

    <?php if ($homeCategories): ?>
        <div class="sqm-sechead"><h2><?= t('m.categories') ?></h2></div>
        <div class="sqm-cats">
            <?php foreach ($homeCategories as $hc): ?>
                <a href="places.php?category=<?= rawurlencode($hc) ?>" class="sqm-cat"><i class="<?= smartq_category_icon($hc) ?>"></i><span><?= t_cat($hc) ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php if (!$isGuest): ?>
<div class="container sqm-desktop-only">
    <div class="welcome-section dashboard-hero-row workspace-hero">
        <div>
            <span class="dashboard-kicker"><span class="sq-live-dot"></span> <?= t('user.dash.kicker') ?></span>
            <h1><?= t('user.dash.welcome', ['name' => $user['full_name']]) ?></h1>
            <p><?= t('user.dash.intro') ?></p>
        </div>

        <div class="dashboard-hero-actions">
            <a href="places.php" class="btn btn-primary sq-btn--lg">
                <i class="fa-solid fa-plus"></i>
                <?= t('user.dash.book') ?>
            </a>
        </div>
    </div>

    <div class="dashboard-grid dashboard-grid-3">
        <div class="dashboard-card">
            <div class="card-head">
                <span><?= t('user.dash.total') ?></span>
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="card-big-number" data-count="<?= $totalBookings ?>"><?= $totalBookings ?></div>
            <p class="card-note"><?= t('user.dash.total_note') ?></p>
        </div>

        <div class="dashboard-card">
            <div class="card-head">
                <span><?= t('user.dash.active') ?></span>
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div class="card-big-number" data-count="<?= $activeBookings ?>"><?= $activeBookings ?></div>
            <p class="card-note"><?= t('user.dash.active_note') ?></p>
        </div>

        <div class="dashboard-card">
            <div class="card-head">
                <span><?= t('user.dash.next') ?></span>
                <i class="fa-solid fa-ticket"></i>
            </div>
            <?php if ($nextBooking): ?>
                <div class="next-place"><?= htmlspecialchars($nextBooking['place_name']) ?></div>
                <p class="card-note">
                    <i class="fa-solid fa-calendar"></i> <span class="sq-ltr"><?= htmlspecialchars($nextBooking['schedule_date']) ?></span> ·
                    <?php if (!empty($nextBooking['appointment_time'])): ?>
                        <?= htmlspecialchars(sq_time($nextBooking['appointment_time'])) ?>
                    <?php else: ?>
                        <span class="sq-ltr"><?= htmlspecialchars(substr($nextBooking['start_time'], 0, 5)) ?> – <?= htmlspecialchars(substr($nextBooking['end_time'], 0, 5)) ?></span>
                    <?php endif; ?>
                </p>
                <p class="card-note" style="margin-top:8px">
                    <span class="sq-chip sq-ltr" style="font-family:var(--font-head);font-weight:800">Q-<?= str_pad((string)(int)$nextBooking['queue_number'], 3, '0', STR_PAD_LEFT) ?></span>
                    <?= htmlspecialchars($nextBooking['city']) ?> · <?= t_cat($nextBooking['category']) ?>
                </p>
            <?php else: ?>
                <div class="empty-title"><?= t('user.dash.no_upcoming') ?></div>
                <p class="card-note"><?= t('user.dash.no_upcoming_note') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="quick-actions-panel">
        <h2><?= t('user.dash.quick') ?></h2>
        <p><?= t('user.dash.quick_note') ?></p>

        <div class="quick-actions-grid">
            <a href="places.php" class="quick-action-card">
                <i class="fa-solid fa-plus"></i>
                <div>
                    <strong><?= t('user.dash.book') ?></strong>
                    <span><?= t('user.dash.qa_book_note') ?></span>
                </div>
            </a>

            <a href="appointment.php" class="quick-action-card">
                <i class="fa-solid fa-list-check"></i>
                <div>
                    <strong><?= t('user.my_appointments') ?></strong>
                    <span><?= t('user.dash.qa_appts_note') ?></span>
                </div>
            </a>

            <a href="https://drive.usercontent.google.com/download?id=15uEVxpWX8XrNO_Pps8ss4AWoXTe64ZWq&amp;export=download&amp;confirm=t" class="quick-action-card" download>
                <i class="fa-solid fa-mobile-screen-button"></i>
                <div>
                    <strong><?= t('user.dash.qa_app') ?></strong>
                    <span><?= t('user.dash.qa_app_note') ?></span>
                </div>
            </a>
        </div>
    </div>

    <div class="dashboard-bottom-grid">
        <section class="activity-panel">
            <div class="panel-heading-row">
                <div>
                    <h2><?= t('user.dash.recent') ?></h2>
                    <p><?= t('user.dash.recent_note') ?></p>
                </div>
                <a href="appointment.php" class="panel-link"><?= t('user.dash.view_all') ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <div class="activity-list">
                <?php if ($recentAppointments->num_rows > 0): ?>
                    <?php while ($recent = $recentAppointments->fetch_assoc()): ?>
                        <article class="activity-row">
                            <div class="activity-icon"><i class="fa-solid fa-calendar-check"></i></div>
                            <div class="activity-copy">
                                <strong><?= htmlspecialchars($recent['place_name']) ?></strong>
                                <span><?= t_cat($recent['category']) ?> · <span class="sq-ltr"><?= htmlspecialchars($recent['schedule_date']) ?></span> · <span class="sq-ltr"><?= htmlspecialchars(substr($recent['start_time'], 0, 5)) ?></span></span>
                            </div>
                            <div class="activity-meta">
                                <b class="sq-ltr">Q-<?= str_pad((string)(int)$recent['queue_number'], 3, '0', STR_PAD_LEFT) ?></b>
                                <span class="status-badge status-<?= htmlspecialchars($recent['status']) ?>">
                                    <?= t_status($recent['status']) ?>
                                </span>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="activity-empty">
                        <i class="fa-solid fa-calendar"></i>
                        <div><strong><?= t('user.dash.no_activity') ?></strong><span><?= t('user.dash.no_activity_note') ?></span></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="booking-guide-card">
            <span class="dashboard-kicker"><?= t('user.dash.flow') ?></span>
            <h2><?= t('user.dash.flow_title') ?></h2>
            <div class="guide-steps">
                <div><b>01</b><span><?= t('user.dash.flow1') ?></span></div>
                <div><b>02</b><span><?= t('user.dash.flow2') ?></span></div>
                <div><b>03</b><span><?= t('user.dash.flow3') ?></span></div>
            </div>
            <a href="places.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?= t('user.dash.flow_cta') ?></a>
        </aside>
    </div>
</div>
<?php endif; ?>

</body>
</html>
