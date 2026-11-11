<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('manager');

$managerId = (int)$user['id'];
$placeFilter = (int)($_GET['place_id'] ?? 0);

$sql = "
    SELECT 
        s.id,
        s.place_id,
        s.schedule_date,
        s.start_time,
        s.end_time,
        s.capacity,
        s.status,
        p.place_name,
        (
            SELECT COUNT(*)
            FROM appointments a
            WHERE a.schedule_id = s.id
              AND a.status IN ('waiting', 'confirmed', 'done')
        ) AS booked_count
    FROM schedules s
    JOIN places p ON s.place_id = p.id
    WHERE p.manager_id = ?
";

$params = [$managerId];
$types = "i";

if ($placeFilter > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $placeFilter;
    $types .= "i";
}

$sql .= " ORDER BY s.schedule_date DESC, s.start_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$schedules = $stmt->get_result();

$placesStmt = $conn->prepare("SELECT id, place_name FROM places WHERE manager_id = ? ORDER BY place_name ASC");
$placesStmt->bind_param("i", $managerId);
$placesStmt->execute();
$places = $placesStmt->get_result();

$activePage = 'schedules';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.schedules.title') ?></title>
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
        <h1><?= t('manager.schedules.heading') ?></h1>
        <p><?= t('manager.schedules.intro') ?></p>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">
            <select name="place_id">
                <option value=""><?= t('manager.schedules.all_places') ?></option>
                <?php while ($place = $places->fetch_assoc()): ?>
                    <option value="<?= (int)$place['id'] ?>" <?= $placeFilter === (int)$place['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($place['place_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i>
                <?= t('manager.schedules.filter') ?>
            </button>

            <a href="add-schedule.php" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-plus"></i>
                <?= t('manager.add_schedule') ?>
            </a>
        </form>
    </div>

    <?php if ($schedules->num_rows > 0): ?>
        <div class="schedule-grid">
            <?php while ($schedule = $schedules->fetch_assoc()): ?>
                <?php $remaining = (int)$schedule['capacity'] - (int)$schedule['booked_count']; ?>
                <div class="schedule-card">
                    <h3><?= htmlspecialchars($schedule['place_name']) ?></h3>

                    <div class="schedule-meta"><strong><?= t('manager.schedules.date') ?></strong> <span class="sq-ltr"><?= htmlspecialchars($schedule['schedule_date']) ?></span></div>
                    <div class="schedule-meta"><strong><?= t('manager.schedules.time') ?></strong> <span class="sq-ltr"><?= htmlspecialchars(substr($schedule['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($schedule['end_time'], 0, 5)) ?></span></div>
                    <div class="schedule-meta">
                        <strong><?= t('manager.schedules.status') ?></strong>
                        <span class="status-badge status-<?= htmlspecialchars($schedule['status']) ?>">
                            <?= t_status($schedule['status']) ?>
                        </span>
                    </div>

                    <div class="schedule-stats">
                        <div class="schedule-stat-box">
                            <span><?= t('manager.schedules.capacity') ?></span>
                            <strong><?= (int)$schedule['capacity'] ?></strong>
                        </div>
                        <div class="schedule-stat-box">
                            <span><?= t('manager.schedules.booked') ?></span>
                            <strong><?= (int)$schedule['booked_count'] ?></strong>
                        </div>
                        <div class="schedule-stat-box">
                            <span><?= t('manager.schedules.remaining') ?></span>
                            <strong><?= $remaining ?></strong>
                        </div>
                    </div>

                    <div class="schedule-actions">
                        <a href="bookings.php?schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-secondary">
                            <i class="fa-solid fa-list-check"></i>
                            <?= t('manager.view_bookings') ?>
                        </a>

                        <a href="edit-schedule.php?id=<?= (int)$schedule['id'] ?>" class="btn btn-primary">
                            <i class="fa-solid fa-pen"></i>
                            <?= t('manager.schedules.edit') ?>
                        </a>

                        <form method="POST" action="delete-schedule.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('manager.schedules.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                            <?= csrf_input() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <button type="submit" class="btn danger-btn">
                                <i class="fa-solid fa-trash"></i>
                                <?= t('manager.delete') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3><?= t('manager.schedules.empty_title') ?></h3>
            <p><?= t('manager.schedules.empty_text') ?></p>
            <a href="add-schedule.php" class="btn btn-primary">
                <i class="fa-solid fa-calendar-plus"></i>
                <?= t('manager.add_schedule') ?>
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>