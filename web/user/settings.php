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
    if (isset($_POST['change_password'])) {
        [$ok, $message] = smartq_change_password($conn, (int)$user['id'], (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
    } else {
        [$ok, $message] = [false, 'Nothing to save.'];
    }
    $messageType = $ok ? 'success' : 'error';
    $user = $_SESSION['user'];
}

$activePage = 'settings';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.settings.title') ?></title>
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

<div class="container">
    <div class="settings-hero">
        <div>
            <h1><?= t('user.settings.heading') ?></h1>
            <p><?= t('user.settings.intro') ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="settings-modern-grid">
        <div class="settings-profile-card">
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

            <h2><?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="account-email"><span class="sq-ltr"><?= htmlspecialchars($user['email']) ?></span></p>

            <div class="security-badge">
                <i class="fa-solid fa-shield-halved"></i>
                <?= t('user.settings.secure') ?>
            </div>

            <div class="settings-quick-links">
                <a href="profile.php">
                    <i class="fa-solid fa-user"></i>
                    <?= t('user.settings.edit_profile') ?>
                </a>
                <a href="appointment.php">
                    <i class="fa-solid fa-calendar-check"></i>
                    <?= t('user.settings.my_appts') ?>
                </a>
                <a href="../logout.php" class="danger-link">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <?= t('user.settings.logout') ?>
                </a>
            </div>
        </div>

        <div class="settings-content">
            <div class="account-panel">
                <h3><?= t('user.settings.pw_security') ?></h3>
                <div class="panel-note"><?= t('user.settings.pw_note') ?></div>

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
                            <label><?= t('user.settings.confirm_new') ?></label>
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

            <div class="account-panel">
                <h3><?= t('user.settings.recs') ?></h3>
                <div class="settings-tips-grid">
                    <div class="settings-tip-box">
                        <i class="fa-solid fa-lock"></i>
                        <h4><?= t('user.settings.tip1') ?></h4>
                        <p><?= t('user.settings.tip1_note') ?></p>
                    </div>

                    <div class="settings-tip-box">
                        <i class="fa-solid fa-envelope"></i>
                        <h4><?= t('user.settings.tip2') ?></h4>
                        <p><?= t('user.settings.tip2_note') ?></p>
                    </div>

                    <div class="settings-tip-box">
                        <i class="fa-solid fa-user-check"></i>
                        <h4><?= t('user.settings.tip3') ?></h4>
                        <p><?= t('user.settings.tip3_note') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
