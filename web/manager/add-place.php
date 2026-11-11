<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';
require_once '../config/places.php';

$user = require_role('manager');

$managerId = (int)$user['id'];
$message = '';
$messageType = 'success';

$place = smartq_manager_place($conn, $managerId);
$categories = smartq_categories($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$data, $message] = smartq_place_input($conn, $_POST);
    $messageType = 'error';

    if ($message === '') {
        try {
            // Places store only the file name (served from uploads/places/).
            $uploaded = smartq_store_image_upload($_FILES['place_image'] ?? null, 'places', 'place_' . $managerId);
            $placeImage = $uploaded !== null ? basename($uploaded) : ($place['place_image'] ?? null);

            if ($place) {
                $updateStmt = $conn->prepare("
                    UPDATE places
                    SET place_name = ?, category = ?, city = ?, address = ?, description = ?, place_image = ?
                    WHERE id = ? AND manager_id = ?
                ");
                $placeId = (int)$place['id'];
                $updateStmt->bind_param("ssssssii", $data['place_name'], $data['category'], $data['city'], $data['address'], $data['description'], $placeImage, $placeId, $managerId);
                if (!$updateStmt->execute()) {
                    throw new RuntimeException('Failed to update place.');
                }
                if ($uploaded !== null && !empty($place['place_image'])) {
                    smartq_delete_upload($place['place_image'], 'places');
                }
                $message = t_raw('manager.place.msg_updated');
            } else {
                smartq_insert_place($conn, $managerId, $data, $placeImage);
                $message = t_raw('manager.place.msg_created');
            }
            $messageType = 'success';
            $place = smartq_manager_place($conn, $managerId);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }
    }
}

$activePage = 'places';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.place.title') ?></title>
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
        <h1><?= t('manager.place.heading') ?></h1>
        <p><?= t('manager.place.intro') ?></p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data" class="manager-form">
            <?= csrf_input() ?>
            <h3><?= t('manager.place.form_title') ?></h3>
            <div class="manager-note"><?= t('manager.place.form_note') ?></div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.place.name') ?></label>
                    <input type="text" name="place_name" value="<?= htmlspecialchars($place['place_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.place.category') ?></label>
                    <select name="category" required>
                        <option value=""><?= t('manager.place.select_category') ?></option>
                        <?php
                        $currentCategory = $place['category'] ?? '';
                        foreach ($categories as $categoryOption):
                        ?>
                            <option value="<?= htmlspecialchars($categoryOption) ?>" <?= $currentCategory === $categoryOption ? 'selected' : '' ?>>
                                <?= t_cat($categoryOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('manager.place.city') ?></label>
                    <input type="text" name="city" value="<?= htmlspecialchars($place['city'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label><?= t('manager.place.address') ?></label>
                    <input type="text" name="address" value="<?= htmlspecialchars($place['address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label><?= t('manager.place.description') ?></label>
                <textarea name="description" placeholder="<?= t('manager.place.description_ph') ?>"><?= htmlspecialchars($place['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label><?= t('manager.place.image') ?></label>
                <input type="file" name="place_image" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= t('manager.place.save') ?>
                </button>
            </div>
        </form>
    </div>

    <?php if ($place): ?>
        <div class="table-card">
            <h3><?= t('manager.place.preview') ?></h3>
            <div class="manager-note"><?= t('manager.place.preview_note') ?></div>

            <div class="manager-places-grid">
                <div class="place-card-modern">
                    <?php if (!empty($place['place_image'])): ?>
                        <img src="../uploads/places/<?= htmlspecialchars($place['place_image']) ?>" alt="<?= t('manager.place.image_alt') ?>" class="place-card-image">
                    <?php else: ?>
                        <img src="../img/place-placeholder.svg" alt="<?= t('manager.place.image_alt') ?>" class="place-card-image">
                    <?php endif; ?>

                    <div class="place-card-body">
                        <span class="place-badge"><?= t_cat($place['category']) ?></span>
                        <h3><?= htmlspecialchars($place['place_name']) ?></h3>
                        <div class="place-meta"><strong><?= t('manager.place.city_label') ?></strong> <?= htmlspecialchars($place['city']) ?></div>
                        <div class="place-meta"><strong><?= t('manager.place.address_label') ?></strong> <?= htmlspecialchars($place['address'] ?: '-') ?></div>

                        <?php if (!empty($place['description'])): ?>
                            <div class="place-description"><?= htmlspecialchars($place['description']) ?></div>
                        <?php endif; ?>

                        <div class="place-actions">
                            <a href="schedules.php?place_id=<?= (int)$place['id'] ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-calendar-days"></i>
                                <?= t('manager.place.view_schedules') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
