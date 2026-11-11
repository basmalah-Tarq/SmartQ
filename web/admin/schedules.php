<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('admin');

$placeFilter = (int)($_GET['place_id'] ?? 0);

$sql = "
    SELECT
        s.*,
        p.place_name,
        (
            SELECT COUNT(*)
            FROM appointments a
            WHERE a.schedule_id = s.id
              AND a.status IN ('waiting','confirmed','done')
        ) AS booked_count
    FROM schedules s
    JOIN places p ON s.place_id = p.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($placeFilter > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $placeFilter;
    $types .= 'i';
}

$sql .= " ORDER BY s.schedule_date DESC, s.start_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$schedules = $stmt->get_result();

$places = $conn->query("SELECT id, place_name FROM places ORDER BY place_name ASC");

$activePage = 'schedules';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.schedules.title') ?></title>
    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
<?= smartq_head('../') ?>
</head>
<body class="role-admin">

<?php include('partials/navbar.php'); ?>

<div class="container">
    <div class="page-header">
        <h1><?= t('admin.schedules.heading') ?></h1>
        <p><?= t('admin.schedules.intro') ?></p>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">
            <select name="place_id">
                <option value=""><?= t('admin.schedules.all_places') ?></option>
                <?php while ($place = $places->fetch_assoc()): ?>
                    <option value="<?= (int)$place['id'] ?>" <?= $placeFilter === (int)$place['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($place['place_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i>
                <?= t('admin.filter') ?>
            </button>
        </form>
    </div>

    <?php if ($schedules->num_rows > 0): ?>
        <div class="admin-schedule-grid">
            <?php while ($schedule = $schedules->fetch_assoc()): ?>
                <?php $remaining = (int)$schedule['capacity'] - (int)$schedule['booked_count']; ?>
                <div class="schedule-card">
                    <h3><?= htmlspecialchars($schedule['place_name']) ?></h3>

                    <div class="schedule-meta"><strong><?= t('admin.schedules.date') ?></strong> <span class="sq-ltr"><?= htmlspecialchars($schedule['schedule_date']) ?></span></div>
                    <div class="schedule-meta"><strong><?= t('admin.schedules.time') ?></strong> <span class="sq-ltr"><?= htmlspecialchars(substr($schedule['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($schedule['end_time'], 0, 5)) ?></span></div>
                    <div class="schedule-meta">
                        <strong><?= t('admin.schedules.status') ?></strong>
                        <span class="status-badge status-<?= htmlspecialchars($schedule['status']) ?>">
                            <?= t_status($schedule['status']) ?>
                        </span>
                    </div>

                    <div class="schedule-stats">
                        <div class="schedule-stat-box">
                            <span><?= t('admin.schedules.capacity') ?></span>
                            <strong><?= (int)$schedule['capacity'] ?></strong>
                        </div>
                        <div class="schedule-stat-box">
                            <span><?= t('admin.schedules.booked') ?></span>
                            <strong><?= (int)$schedule['booked_count'] ?></strong>
                        </div>
                        <div class="schedule-stat-box">
                            <span><?= t('admin.schedules.remaining') ?></span>
                            <strong><?= $remaining ?></strong>
                        </div>
                    </div>

                    <div class="schedule-actions">
                        <a href="bookings.php?schedule_id=<?= (int)$schedule['id'] ?>" class="btn btn-secondary">
                            <i class="fa-solid fa-list-check"></i>
                            <?= t('admin.schedules.view_bookings') ?>
                        </a>

                        <form method="POST" action="delete-schedule.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('admin.schedules.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                            <?= csrf_input() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <button type="submit" class="btn danger-btn">
                                <i class="fa-solid fa-trash"></i>
                                <?= t('admin.delete') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3><?= t('admin.schedules.empty_title') ?></h3>
            <p><?= t('admin.schedules.empty_text') ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
