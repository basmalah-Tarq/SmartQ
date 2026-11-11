<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/booking.php';

$user = require_role('manager');

$managerId = (int)$user['id'];
$scheduleId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$message = '';
$messageType = 'success';

$stmt = $conn->prepare("
    SELECT s.*, p.place_name
    FROM schedules s
    JOIN places p ON s.place_id = p.id
    WHERE s.id = ? AND p.manager_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $scheduleId, $managerId);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

if (!$schedule) {
    header("Location: schedules.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $scheduleDate = trim($_POST['schedule_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $status = ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
    $validTime = fn($t) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
    $messageType = 'error';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate) || !$validTime($startTime) || !$validTime($endTime) || $capacity <= 0) {
        $message = t_raw('manager.editsch.err_required');
    } elseif (strtotime("2000-01-01 $endTime") <= strtotime("2000-01-01 $startTime")) {
        $message = t_raw('manager.editsch.err_end');
    } else {
        $startTime = date('H:i:s', strtotime("2000-01-01 $startTime"));
        $endTime = date('H:i:s', strtotime("2000-01-01 $endTime"));
        // Never orphan booked customers or shrink below the active bookings (config/booking.php).
        $message = smartq_check_schedule_change($conn, $schedule, $scheduleDate, $startTime, $endTime, $capacity);
    }

    if ($message === '') {
        $update = $conn->prepare("
            UPDATE schedules s
            JOIN places p ON p.id = s.place_id
            SET s.schedule_date = ?, s.start_time = ?, s.end_time = ?, s.capacity = ?, s.status = ?
            WHERE s.id = ? AND p.manager_id = ?
        ");
        $update->bind_param("sssisii", $scheduleDate, $startTime, $endTime, $capacity, $status, $scheduleId, $managerId);

        if ($update->execute()) {
            header("Location: schedules.php");
            exit();
        } else {
            $message = t_raw('manager.editsch.err_failed');
            $messageType = 'error';
        }
    }
}

$activePage = 'schedules';

/** Display text for the (English) messages of smartq_check_schedule_change(); other text passes through t_msg(). */
function manager_schedule_msg(string $m): string
{
    if (smartq_lang() === 'en') return $m;
    $patterns = [
        '/^The time range must fit at least one (\d+)-minute slot\.$/' => ['manager.schedmsg.fit', 'duration'],
        '/^Capacity can\'t be more than the (\d+) time slots of this schedule/' => ['manager.schedmsg.cap_max', 'slots'],
        '/^Capacity can\'t be lower than the (\d+) active booking/' => ['manager.schedmsg.cap_min', 'active'],
        '/^These booked times would no longer fit the new hours: (.+?)\. Keep them inside/' => ['manager.schedmsg.orphans', 'times'],
    ];
    foreach ($patterns as $re => [$key, $var]) {
        if (preg_match($re, $m, $mm)) return t_raw($key, [$var => strtr($mm[1], ['AM' => 'ص', 'PM' => 'م', ', ' => '، '])]);
    }
    return t_msg($m);
}
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.editsch.title') ?></title>
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
        <h1><?= t('manager.editsch.heading') ?></h1>
        <p><?= htmlspecialchars($schedule['place_name']) ?></p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(manager_schedule_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" class="manager-form">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$scheduleId ?>">

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.schedule_date') ?></label>
                    <input type="date" name="schedule_date" value="<?= htmlspecialchars($schedule['schedule_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.editsch.capacity') ?></label>
                    <input type="number" name="capacity" min="1" value="<?= (int)$schedule['capacity'] ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.start_time') ?></label>
                    <input type="time" dir="ltr" name="start_time" value="<?= htmlspecialchars(substr($schedule['start_time'], 0, 5)) ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.end_time') ?></label>
                    <input type="time" dir="ltr" name="end_time" value="<?= htmlspecialchars(substr($schedule['end_time'], 0, 5)) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label><?= t('manager.status') ?></label>
                <select name="status">
                    <option value="open" <?= $schedule['status'] === 'open' ? 'selected' : '' ?>><?= t_status('open') ?></option>
                    <option value="closed" <?= $schedule['status'] === 'closed' ? 'selected' : '' ?>><?= t_status('closed') ?></option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= t('manager.editsch.save') ?>
                </button>

                <a href="schedules.php" class="btn btn-secondary"><?= t('common.back') ?></a>
            </div>
        </form>
    </div>
</div>

</body>
</html>