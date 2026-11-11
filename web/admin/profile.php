<?php
// "My account": profile + password, shared logic in config/account.php.
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';
require_once '../config/account.php';

$user = require_role('admin');
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['update_profile'])) {
        [$ok, $message] = smartq_update_account($conn, $user, $_POST, $_FILES['profile_image'] ?? null);
    } elseif (isset($_POST['change_password'])) {
        [$ok, $message] = smartq_change_password($conn, (int)$user['id'], (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
    } else {
        [$ok, $message] = [false, 'Nothing to save.'];
    }
    $messageType = $ok ? 'success' : 'error';
    $user = $_SESSION['user'];
}

$activePage = '';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.profile.title') ?></title>
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

<?php $activePage = 'profile'; include('partials/navbar.php'); ?>

<div class="container">
    <div class="profile-hero">
        <div>
            <h1><?= t('admin.profile.heading') ?></h1>
            <p><?= t('admin.profile.intro') ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <div class="profile-side-card">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="<?= htmlspecialchars(getProfileImageUrl($user['profile_image'] ?? '')) ?>" alt="<?= t('admin.profile.img_alt') ?>" class="account-avatar-image">
            <?php else: ?>
                <div class="account-avatar"><?= smartq_initial($user['full_name']) ?></div>
            <?php endif; ?>

            <h2><?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="account-email"><span class="sq-ltr"><?= htmlspecialchars($user['email']) ?></span></p>

            <div class="account-badges">
                <span class="role-chip"><?= t_role('admin') ?></span>
            </div>
        </div>

        <div>
            <div class="account-panel">
                <h3><?= t('admin.profile.personal') ?></h3>

                <form method="POST" enctype="multipart/form-data" class="account-form">
<?= csrf_input() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('admin.form.full_name') ?></label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><?= t('admin.form.email') ?></label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" dir="ltr" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('admin.form.phone') ?></label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" dir="ltr">
                        </div>

                        <div class="form-group">
                            <label><?= t('admin.form.birth_date') ?></label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= t('admin.profile.image') ?></label>
                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="account-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <?= t('admin.profile.save') ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="account-panel">
                <h3><?= t('admin.profile.security') ?></h3>

                <form method="POST" class="account-form">
<?= csrf_input() ?>
                    <div class="form-group">
                        <label><?= t('admin.profile.current_password') ?></label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('admin.profile.new_password') ?></label>
                            <input type="password" name="new_password" required>
                        </div>

                        <div class="form-group">
                            <label><?= t('admin.profile.confirm_password') ?></label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="account-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fa-solid fa-key"></i>
                            <?= t('admin.profile.update_password') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
