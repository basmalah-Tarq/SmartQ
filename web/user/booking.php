<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/booking.php';
require_once '../config/categories.php';

$schedule_id = intval($_GET['schedule_id'] ?? 0);
// Guests who press "Book" are asked to log in, then come straight back to this schedule.
$user = require_user_or_login('user/booking.php?schedule_id=' . $schedule_id);

// Same booking engine as the mobile app (config/booking.php).
$schedule = smartq_get_schedule($conn, $schedule_id);
if (!$schedule || $schedule['status'] !== 'open' || (int)$schedule['active'] !== 1) {
    header('Location: places.php?error=schedule_unavailable');
    exit();
}

$slotDuration = max(5, (int)($schedule['slot_duration'] ?? 30));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $appointment_time = $_POST['appointment_time'] ?? '';

    if (!$appointment_time) {
        $error = "Please choose an appointment time.";
    } else {
        try {
            $booking = smartq_book($conn, (int)$user['id'], $schedule_id, [
                'customer_name' => $user['full_name'],
                'customer_phone' => $user['phone'] ?? '',
                'customer_email' => $user['email'],
                'appointment_time' => $appointment_time,
            ]);
            header("Location: booking-complete.php?id=" . $booking['appointment_id']);
            exit();
        } catch (BookingException $e) {
            $error = $e->getMessage();
        }
    }
}

// Same slot list and "Help me choose" suggestion as the mobile app (api/user/slots.php).
$slotView = smartq_bookable_slots($conn, $schedule);
$slotList = $slotView['slots'];
$seatsLeft = $slotView['seats_left'];
$suggestedTime = $slotView['suggested_time'];
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.book.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
    <?= smartq_head('../') ?>
</head>
<body class="role-user sqm-page-flow">

<?php $activePage = 'places'; $sqHideTabs = true; include('partials/navbar.php'); ?>

<?php
    $freeCount = 0;
    foreach ($slotList as $slotItem) {
        if ($slotItem['available'] && $seatsLeft > 0) { $freeCount++; }
    }
    $bookingDateTs = strtotime($schedule['schedule_date']);
?>

<div class="container sqm-booking">
    <div class="sqm-flowhead">
        <a href="schedule.php?place_id=<?= (int)$schedule['place_id'] ?>" class="sqm-backbtn" aria-label="<?= t('m.back') ?>"><?= sqm_icon('back', 'sqm-flip') ?></a>
        <div><h1><?= t('m.choose_time') ?></h1><p><?= t('m.step_slot') ?></p></div>
    </div>
    <div class="sqm-card sqm-placecard">
        <span class="sqm-thumb"><i class="<?= smartq_category_icon((string)$schedule['category']) ?>"></i></span>
        <div>
            <b><?= htmlspecialchars($schedule['place_name']) ?></b>
            <span class="sqm-placecard__date"><?= sqm_icon('calendar') ?> <?= htmlspecialchars(sq_date('l, j F Y', $bookingDateTs)) ?></span>
            <small><?= t('m.open_hours', ['a' => sq_time($schedule['start_time']), 'b' => sq_time($schedule['end_time']), 'n' => $slotDuration]) ?></small>
        </div>
    </div>

    <nav class="sq-crumbs" aria-label="<?= t('user.crumbs') ?>">
        <a href="places.php"><?= t('user.crumb_places') ?></a> <i class="fa-solid fa-chevron-right"></i>
        <a href="schedule.php?place_id=<?= (int)$schedule['place_id'] ?>"><?= htmlspecialchars($schedule['place_name']) ?></a> <i class="fa-solid fa-chevron-right"></i>
        <span><?= t('user.choose_time') ?></span>
    </nav>

    <div class="page-header">
        <span class="dashboard-kicker"><i class="fa-solid fa-clock"></i> <?= t('user.book.kicker') ?></span>
        <h1><?= t('user.book.heading') ?></h1>
        <p><?= t('user.book.intro') ?></p>
    </div>

    <div class="sq-steps-bar" aria-label="<?= t('user.steps') ?>">
        <span class="is-done"><b><i class="fa-solid fa-check"></i></b> <?= t('user.step_place') ?></span><hr>
        <span class="is-done"><b><i class="fa-solid fa-check"></i></b> <?= t('user.step_schedule') ?></span><hr>
        <span class="is-current"><b>3</b> <?= t('user.step_slot') ?></span><hr>
        <span><b>4</b> <?= t('user.step_ticket') ?></span>
    </div>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars(t_msg($error)) ?></div>
    <?php endif; ?>

    <form method="POST" class="sq-booking" id="bookingForm">
        <?= csrf_input() ?>
        <input type="hidden" name="appointment_time" id="appointment_time">

        <section class="sq-booking__main sq-panel">
            <div class="panel-heading-row slots-title">
                <div>
                    <h2 style="font-size:20px"><span class="sqm-d"><?= t('user.book.slots') ?></span><span class="sqm-m"><?= t('m.available_times') ?></span></h2>
                    <p><?= t('user.book.slots_note') ?></p>
                </div>
                <span class="sq-chip"><i class="fa-solid fa-clock"></i> <?= t('user.book.every', ['n' => $slotDuration]) ?></span>
                <span class="sqm-countpill sqm-countpill--ok"><?= t('m.n_free', ['n' => $freeCount]) ?></span>
            </div>

            <div class="sq-slot-legend">
                <span><i></i> <?= t('user.book.available') ?></span>
                <span><i class="is-sel"></i> <?= t('user.book.selected') ?></span>
                <span><i class="is-booked"></i> <?= t('user.book.booked_passed') ?></span>
            </div>

            <div class="slots-grid">
                <?php $slotIndex = 0; foreach ($slotList as $slot): ?>
                    <?php $isFree = $slot['available'] && $seatsLeft > 0; ?>
                    <button
                        type="button"
                        class="slot-card <?= $isFree ? 'available' : 'booked' ?>"
                        data-time="<?= htmlspecialchars($slot['time']) ?>"
                        style="--i: <?= $slotIndex++ ?>"
                        <?= $isFree ? '' : 'disabled' ?>
                    >
                        <span><?= htmlspecialchars(sq_time($slot['time'])) ?></span>
                        <small><?= $isFree ? t('user.book.available') : ($slot['reason'] === 'past' ? t('user.book.passed') : ($seatsLeft === 0 ? t('user.full') : t('user.book.booked'))) ?></small>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($slotList)): ?>
                <div class="empty-box" style="margin-top:12px">
                    <h3><?= t('user.book.no_slots') ?></h3>
                    <p><?= t('user.book.no_slots_note') ?></p>
                </div>
            <?php endif; ?>

            <div class="ai-helper-box">
                <div>
                    <h3><i class="fa-solid fa-wand-magic-sparkles"></i> <?= t('user.book.help_title') ?></h3>
                    <p><?= t('user.book.help_note') ?></p>
                </div>

                <button type="button" class="btn ai-magic-btn" id="suggestBtn" data-suggested-time="<?= htmlspecialchars((string)$suggestedTime) ?>" <?= $freeCount === 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-wand-magic-sparkles"></i> <?= t('user.book.help_btn') ?>
                </button>
            </div>
        </section>

        <aside class="sq-panel sq-summary">
            <div class="sq-summary__head">
                <span class="place-badge"><?= t_cat($schedule['category']) ?></span>
                <h2><?= htmlspecialchars($schedule['place_name']) ?></h2>
                <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($schedule['city']) ?></p>
            </div>
            <div class="sq-summary__body">
                <div class="sq-summary__row"><span><?= t('user.date') ?></span><b><?= htmlspecialchars(sq_date('D, M j, Y', $bookingDateTs)) ?></b></div>
                <div class="sq-summary__row"><span><?= t('user.book.seats_left') ?></span><b><?= (int)$seatsLeft ?></b></div>
                <div class="sq-summary__row"><span><?= t('user.book.free_slots') ?></span><b><?= (int)$freeCount ?></b></div>

                <div class="sqm-turnbar"><small><?= t('m.your_turn') ?></small><b class="sqm-turnbar__val" id="sqmTurnVal"><?= t('m.pick_time') ?></b><span id="sqmTurnHint"><?= t('m.tap_free') ?></span></div>
                <div class="selected-preview" id="selectedPreview" aria-live="polite">
                    <?= t('user.book.pick') ?>
                </div>

                <button type="submit" class="btn btn-primary sq-btn--lg confirm-btn" id="confirmBtn" disabled>
                    <i class="fa-solid fa-ticket"></i> <?= t('user.book.confirm') ?>
                </button>
                <a href="schedule.php?place_id=<?= (int)$schedule['place_id'] ?>" class="btn sq-btn--ghost"><?= t('user.book.back') ?></a>
            </div>
        </aside>
    </form>
</div>

<script>
(function () {
    const L = {
        selectedTime: <?= json_encode(t_raw('user.book.selected_time'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        picked: <?= json_encode(t_raw('user.book.picked'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        chooseErr: <?= json_encode(t_raw('user.book.choose_err'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        booking: <?= json_encode(t_raw('user.book.booking'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
    };
    const slots = document.querySelectorAll('.slot-card.available');
    const input = document.getElementById('appointment_time');
    const preview = document.getElementById('selectedPreview');
    const confirmBtn = document.getElementById('confirmBtn');

    slots.forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.slot-card').forEach(b => { b.classList.remove('selected', 'is-suggested'); b.setAttribute('aria-pressed', 'false'); });
            btn.classList.add('selected');
            btn.setAttribute('aria-pressed', 'true');
            input.value = btn.dataset.time;
            preview.textContent = L.selectedTime;
            preview.appendChild(document.createElement('strong'));
            preview.querySelector('strong').textContent = btn.querySelector('span').innerText;
            preview.classList.remove('active');
            void preview.offsetWidth;
            preview.classList.add('active');
            confirmBtn.disabled = false;
            var tv = document.getElementById('sqmTurnVal'), th = document.getElementById('sqmTurnHint');
            if (tv) tv.textContent = btn.querySelector('span').innerText;
            if (th) th.textContent = <?= json_encode(sq_date('D, j M', $bookingDateTs), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        });
    });

    document.getElementById('suggestBtn').addEventListener('click', () => {
        // Server-side suggestion (config/booking.php smartq_suggest_slot) - same as the mobile app.
        const wanted = document.getElementById('suggestBtn').dataset.suggestedTime;
        const available = (wanted && document.querySelector('.slot-card.available[data-time="' + wanted + '"]'))
            || document.querySelector('.slot-card.available');
        if (available) {
            available.click();
            available.classList.add('is-suggested');
            available.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (window.SQ) SQ.toast(L.picked.replace('{time}', available.querySelector('span').innerText));
        }
    });

    document.getElementById('bookingForm').addEventListener('submit', (e) => {
        if (!input.value) {
            e.preventDefault();
            if (window.SQ) SQ.toast(L.chooseErr, 'error');
            return;
        }
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ';
        confirmBtn.appendChild(document.createTextNode(L.booking));
    });
})();
</script>

</body>
</html>
