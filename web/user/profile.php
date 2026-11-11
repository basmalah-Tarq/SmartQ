<?php
// "My account": profile + password, shared logic in config/account.php.
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';
require_once '../config/account.php';

$user = require_role('user');
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

$activePage = 'profile';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.profile.title') ?></title>
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

<div class="container sqm-profile">
    <div class="profile-hero">
        <div>
            <h1><?= t('user.profile.heading') ?></h1>
            <p><?= t('user.profile.intro') ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <div class="profile-side-card">
            <?php
            $profileImageUrl = getUserProfileImageUrl($user['profile_image'] ?? '');
            $profileImageSrc = preg_match('#^https?://#i', $profileImageUrl)
                ? $profileImageUrl
                : ($profileImageUrl !== '' ? $profileImageUrl . '?v=' . time() : '');
            ?>
            <?php if ($profileImageSrc !== ''): ?>
                <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="<?= t('user.profile_alt') ?>" class="account-avatar-image">
            <?php else: ?>
                <div class="account-avatar"><?= smartq_initial($user['full_name']) ?></div>
            <?php endif; ?>
            <label for="sqmPhoto" class="sqm-camera" title="<?= t('m.change_photo') ?>"><?= sqm_icon('camera') ?><span class="sr-only"><?= t('m.change_photo') ?></span></label>

            <h2><?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="account-email"><span class="sq-ltr"><?= htmlspecialchars($user['email']) ?></span></p>

            <div class="account-badges">
                <span class="role-chip"><?= t_role('user') ?></span>
            </div>

            <div class="profile-info-list">
                <div class="profile-info-item">
                    <span><?= t('user.profile.phone') ?></span>
                    <strong><?= $user['phone'] ? '<span class="sq-ltr">' . htmlspecialchars($user['phone']) . '</span>' : t('user.not_added') ?></strong>
                </div>

                <div class="profile-info-item">
                    <span><?= t('user.profile.birth') ?></span>
                    <strong><?= $user['birth_date'] ? '<span class="sq-ltr">' . htmlspecialchars($user['birth_date']) . '</span>' : t('user.not_added') ?></strong>
                </div>
            </div>
        </div>

        <div>
            <div class="account-panel">
                <h3><span class="sqm-d"><?= t('user.profile.personal') ?></span><span class="sqm-m"><?= t('m.personal') ?></span></h3>
                <div class="panel-note"><?= t('user.profile.personal_note') ?></div>

                <form method="POST" enctype="multipart/form-data" class="account-form">
<?= csrf_input() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('user.profile.full_name') ?></label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><?= t('user.profile.email') ?></label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" dir="ltr" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('user.profile.phone') ?></label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" dir="ltr">
                        </div>

                        <div class="form-group">
                            <label><?= t('user.profile.birth') ?></label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= t('user.profile.image') ?></label>
                        <input type="file" id="sqmPhoto" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="account-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <?= t('user.profile.save') ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="sqm-settings">
                <h3 class="sqm-settings__title"><?= t('m.settings') ?></h3>
                <div class="sqm-card sqm-setlist">
                    <a href="<?= smartq_lang_url(smartq_lang() === 'ar' ? 'en' : 'ar') ?>" data-sq-lang="<?= smartq_lang() === 'ar' ? 'en' : 'ar' ?>" class="sqm-setrow"><span class="sqm-setrow__ic"><?= sqm_icon('globe') ?></span><span><b><?= t('m.language') ?></b><small><?= t('m.language_val') ?></small></span><?= sqm_icon('chev', 'sqm-flip sqm-setrow__chev') ?></a>
                    <button type="button" data-sq-theme-toggle class="sqm-setrow"><span class="sqm-setrow__ic"><?= sqm_icon('contrast') ?></span><span><b><?= t('m.theme') ?></b><small><?= t('m.theme_sub') ?></small></span><?= sqm_icon('chev', 'sqm-flip sqm-setrow__chev') ?></button>
                    <a href="#sqmSecurity" class="sqm-setrow"><span class="sqm-setrow__ic"><?= sqm_icon('lock') ?></span><span><b><?= t('m.password') ?></b><small><?= t('m.password_sub') ?></small></span><?= sqm_icon('chev', 'sqm-flip sqm-setrow__chev') ?></a>
                    <a href="../logout.php" class="sqm-setrow sqm-setrow--danger"><span class="sqm-setrow__ic"><?= sqm_icon('logout', 'sqm-flip') ?></span><span><b><?= t('m.logout') ?></b></span></a>
                </div>
            </div>

            <div class="account-panel" id="sqmSecurity">
                <h3><?= t('user.profile.security') ?></h3>
                <div class="panel-note"><?= t('user.profile.security_note') ?></div>

                <form method="POST" class="account-form">
<?= csrf_input() ?>
                    <div class="form-group">
                        <label><?= t('user.profile.current_pw') ?></label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><?= t('user.profile.new_pw') ?></label>
                            <input type="password" name="new_password" required>
                        </div>

                        <div class="form-group">
                            <label><?= t('user.profile.confirm_pw') ?></label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="account-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fa-solid fa-key"></i>
                            <?= t('user.profile.update_pw') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
