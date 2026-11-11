<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/booking.php';

$user = require_role('manager');

$managerId = (int)$user['id'];
$message = '';
$messageType = 'success';
$createdSlots = [];

function oldValue($name, $default = '') {
    return htmlspecialchars($_POST[$name] ?? $default, ENT_QUOTES, 'UTF-8');
}

$placesStmt = $conn->prepare("SELECT id, place_name FROM places WHERE manager_id = ? ORDER BY place_name ASC");
$placesStmt->bind_param("i", $managerId);
$placesStmt->execute();
$places = $placesStmt->get_result();

/*
 * One schedule row = one working session (e.g. 09:00-12:00) split into slots of `slot_duration`
 * minutes. This is the model the booking engine (config/booking.php), the customer pages and the
 * mobile app use. (Before: one schedule row was created per slot and slot_duration was never saved,
 * so e.g. 15-minute schedules had zero bookable slots.)
 * One customer per slot (enforced by a unique index), so capacity defaults to - and can't exceed - the slot count.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $placeId = (int)($_POST['place_id'] ?? 0);
    $scheduleDate = trim($_POST['schedule_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $capacityInput = trim((string)($_POST['capacity'] ?? ''));
    $status = ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';

    $validTime = fn($t) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
    $dateOk = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)
        && checkdate((int)substr($scheduleDate, 5, 2), (int)substr($scheduleDate, 8, 2), (int)substr($scheduleDate, 0, 4));
    $messageType = 'error';

    if ($placeId <= 0 || !$dateOk || !$validTime($startTime) || !$validTime($endTime) || $duration <= 0) {
        $message = t_raw('manager.addsch.err_required');
    } elseif ($scheduleDate < date('Y-m-d')) {
        $message = t_raw('manager.addsch.err_past');
    } elseif (strtotime("2000-01-01 $endTime") <= strtotime("2000-01-01 $startTime")) {
        $message = t_raw('manager.addsch.err_end');
    } elseif ($duration < 5 || $duration > 240) {
        $message = t_raw('manager.addsch.err_duration');
    } else {
        $startTime = date('H:i:s', strtotime("2000-01-01 $startTime"));
        $endTime = date('H:i:s', strtotime("2000-01-01 $endTime"));
        $slotCount = smartq_slot_count($startTime, $endTime, $duration);
        $capacity = $capacityInput === '' ? $slotCount : (int)$capacityInput;

        $checkStmt = $conn->prepare("SELECT id FROM places WHERE id = ? AND manager_id = ? LIMIT 1");
        $checkStmt->bind_param("ii", $placeId, $managerId);
        $checkStmt->execute();

        if (!$checkStmt->get_result()->fetch_assoc()) {
            $message = t_raw('manager.addsch.err_place');
        } elseif ($slotCount < 1) {
            $message = t_raw('manager.addsch.err_short');
        } elseif ($capacity < 1 || $capacity > $slotCount) {
            $message = t_raw('manager.addsch.err_capacity', ['max' => $slotCount]);
        } else {
            $overlapStmt = $conn->prepare("SELECT COUNT(*) AS total FROM schedules
                                           WHERE place_id = ? AND schedule_date = ? AND start_time < ? AND end_time > ?");
            $overlapStmt->bind_param("isss", $placeId, $scheduleDate, $endTime, $startTime);
            $overlapStmt->execute();
            if ((int)$overlapStmt->get_result()->fetch_assoc()['total'] > 0) {
                $message = t_raw('manager.addsch.err_overlap');
            } else {
                $insertStmt = $conn->prepare("INSERT INTO schedules (place_id, schedule_date, start_time, end_time, capacity, slot_duration, status)
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param("isssiis", $placeId, $scheduleDate, $startTime, $endTime, $capacity, $duration, $status);
                if ($insertStmt->execute()) {
                    $startTs = strtotime("2000-01-01 $startTime");
                    for ($i = 0; $i < $slotCount; $i++) {
                        $a = $startTs + $i * $duration * 60;
                        $createdSlots[] = [date('H:i:s', $a), date('H:i:s', $a + $duration * 60)];
                    }
                    $message = t_raw('manager.addsch.success', ['slots' => $slotCount, 'duration' => $duration, 'capacity' => $capacity]);
                    $messageType = 'success';
                    $_POST = [];
                } else {
                    $message = t_raw('manager.addsch.err_failed');
                }
            }
        }
    }
}

$activePage = 'schedules';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.addsch.title') ?></title>
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
    <div class="page-header">
        <h1><?= t('manager.add_schedule') ?></h1>
        <p><?= t('manager.addsch.intro') ?></p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="form-card schedule-builder-card">
        <div class="schedule-help-box">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <div>
                <strong><?= t('manager.addsch.how_title') ?></strong>
                <p><?= th('manager.addsch.how_text') ?></p>
            </div>
        </div>

        <form method="POST" class="manager-form" id="scheduleForm">
            <?= csrf_input() ?>
            <div class="form-group">
                <label><?= t('manager.addsch.place') ?></label>
                <select name="place_id" required>
                    <option value=""><?= t('manager.addsch.select_place') ?></option>
                    <?php while ($place = $places->fetch_assoc()): ?>
                        <option value="<?= (int)$place['id'] ?>" <?= (int)($_POST['place_id'] ?? 0) === (int)$place['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($place['place_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.schedule_date') ?></label>
                    <input type="date" name="schedule_date" value="<?= oldValue('schedule_date') ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.addsch.capacity') ?></label>
                    <input type="number" name="capacity" min="1" value="<?= oldValue('capacity') ?>" placeholder="<?= t('manager.addsch.capacity_ph') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.start_time') ?></label>
                    <input type="time" dir="ltr" name="start_time" value="<?= oldValue('start_time') ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.end_time') ?></label>
                    <input type="time" dir="ltr" name="end_time" value="<?= oldValue('end_time') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.addsch.duration') ?></label>
                    <input type="number" name="duration" min="5" max="240" step="5" value="<?= oldValue('duration', '30') ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.status') ?></label>
                    <select name="status">
                        <option value="open" <?= oldValue('status', 'open') === 'open' ? 'selected' : '' ?>><?= t_status('open') ?></option>
                        <option value="closed" <?= oldValue('status') === 'closed' ? 'selected' : '' ?>><?= t_status('closed') ?></option>
                    </select>
                </div>
            </div>

            <div class="schedule-preview" aria-live="polite">
                <div class="schedule-preview-head">
                    <strong><?= t('manager.addsch.preview_title') ?></strong>
                    <span id="slotsCount"><?= t('manager.addsch.slots_zero') ?></span>
                </div>
                <div class="slot-preview-list" id="slotsPreview">
                    <span class="slot-pill"><?= t('manager.addsch.hint_choose') ?></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-layer-group"></i>
                    <?= t('manager.addsch.submit') ?>
                </button>
                <a href="schedules.php" class="btn btn-secondary"><?= t('common.back') ?></a>
            </div>
        </form>

        <?php if (!empty($createdSlots)): ?>
            <div class="created-slots-box">
                <strong><?= t('manager.addsch.created') ?></strong>
                <div class="slot-preview-list">
                    <?php foreach ($createdSlots as $slot): ?>
                        <span class="slot-pill sq-ltr"><?= htmlspecialchars(substr($slot[0], 0, 5)) ?> - <?= htmlspecialchars(substr($slot[1], 0, 5)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('scheduleForm');
    if (!form) return;

    const startInput = form.querySelector('[name="start_time"]');
    const endInput = form.querySelector('[name="end_time"]');
    const durationInput = form.querySelector('[name="duration"]');
    const preview = document.getElementById('slotsPreview');
    const count = document.getElementById('slotsCount');
    const L = {
        zero: <?= json_encode(t_raw('manager.addsch.slots_zero'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        one: <?= json_encode(t_raw('manager.addsch.slot_one'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        many: <?= json_encode(t_raw('manager.addsch.slot_many'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        more: <?= json_encode(t_raw('manager.addsch.more'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        choose: <?= json_encode(t_raw('manager.addsch.hint_choose'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        end: <?= json_encode(t_raw('manager.addsch.hint_end'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        tooLong: <?= json_encode(t_raw('manager.addsch.hint_too_long'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
    };
    function hint(text) {
        const pill = document.createElement('span');
        pill.className = 'slot-pill';
        pill.textContent = text;
        preview.appendChild(pill);
    }

    function toMinutes(value) {
        if (!value || !value.includes(':')) return null;
        const parts = value.split(':').map(Number);
        return parts[0] * 60 + parts[1];
    }

    function toTime(minutes) {
        const h = String(Math.floor(minutes / 60)).padStart(2, '0');
        const m = String(minutes % 60).padStart(2, '0');
        return `${h}:${m}`;
    }

    function renderPreview() {
        const start = toMinutes(startInput.value);
        const end = toMinutes(endInput.value);
        const duration = Number(durationInput.value);

        preview.innerHTML = '';

        if (start === null || end === null || !duration || duration <= 0) {
            count.textContent = L.zero;
            hint(L.choose);
            return;
        }

        if (end <= start) {
            count.textContent = L.zero;
            hint(L.end);
            return;
        }

        const slots = [];
        for (let slotStart = start; slotStart + duration <= end; slotStart += duration) {
            slots.push([slotStart, slotStart + duration]);
        }

        count.textContent = (slots.length === 1 ? L.one : (slots.length === 0 ? L.zero : L.many)).replace('{n}', slots.length);

        if (!slots.length) {
            hint(L.tooLong);
            return;
        }

        slots.slice(0, 10).forEach(([slotStart, slotEnd]) => {
            const pill = document.createElement('span');
            pill.className = 'slot-pill sq-ltr';
            pill.textContent = `${toTime(slotStart)} - ${toTime(slotEnd)}`;
            preview.appendChild(pill);
        });

        if (slots.length > 10) {
            const more = document.createElement('span');
            more.className = 'slot-pill more';
            more.textContent = L.more.replace('{n}', slots.length - 10);
            preview.appendChild(more);
        }
    }

    ['input', 'change'].forEach((eventName) => {
        startInput.addEventListener(eventName, renderPreview);
        endInput.addEventListener(eventName, renderPreview);
        durationInput.addEventListener(eventName, renderPreview);
    });

    renderPreview();
})();
</script>

</body>
</html>
