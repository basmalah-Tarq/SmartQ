<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('admin');

$result = $conn->query("
    SELECT p.*, u.full_name AS manager_name
    FROM places p
    LEFT JOIN users u ON p.manager_id = u.id
    ORDER BY p.created_at DESC
");

$activePage = 'places';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.places.title') ?></title>
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
        <h1><?= t('admin.places.heading') ?></h1>
        <p><?= t('admin.places.intro') ?></p>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="admin-places-grid">
            <?php while ($place = $result->fetch_assoc()): ?>
                <div class="place-card-modern">
                    <?php if (!empty($place['place_image'])): ?>
                        <img src="../uploads/places/<?= htmlspecialchars($place['place_image']) ?>" alt="<?= t('admin.places.img_alt') ?>" class="place-card-image">
                    <?php else: ?>
                        <img src="../img/place-placeholder.svg" alt="<?= t('admin.places.img_alt') ?>" class="place-card-image">
                    <?php endif; ?>

                    <div class="place-card-body">
                        <span class="place-badge"><?= t_cat($place['category']) ?></span>
                        <h3><?= htmlspecialchars($place['place_name']) ?></h3>
                        <div class="place-meta"><strong><?= t('admin.places.manager') ?></strong> <?= $place['manager_name'] ? htmlspecialchars($place['manager_name']) : t('admin.places.unknown') ?></div>
                        <div class="place-meta"><strong><?= t('admin.places.city') ?></strong> <?= htmlspecialchars($place['city']) ?></div>
                        <div class="place-meta"><strong><?= t('admin.places.address') ?></strong> <?= htmlspecialchars($place['address'] ?: '-') ?></div>

                        <?php if (!empty($place['description'])): ?>
                            <div class="place-description"><?= htmlspecialchars($place['description']) ?></div>
                        <?php endif; ?>

                        <div class="place-actions">
                            <a href="schedules.php?place_id=<?= (int)$place['id'] ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-calendar-days"></i>
                                <?= t('admin.places.view_schedules') ?>
                            </a>

                            <form method="POST" action="delete-place.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('admin.places.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                <?= csrf_input() ?>
                                <input type="hidden" name="place_id" value="<?= (int)$place['id'] ?>">
                                <button type="submit" class="btn danger-btn">
                                    <i class="fa-solid fa-trash"></i>
                                    <?= t('admin.places.delete') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3><?= t('admin.places.empty_title') ?></h3>
            <p><?= t('admin.places.empty_text') ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
