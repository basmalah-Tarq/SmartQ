<?php
require_once __DIR__ . '/../../config/i18n.php';
require_once __DIR__ . '/../../config/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guests are allowed only on pages that set $isGuest (places, schedule).
$sqGuest = !isset($_SESSION['user']) && !empty($isGuest);
if (!isset($_SESSION['user']) && !$sqGuest) {
    header("Location: ../login.php");
    exit();
}
$sqNext = rawurlencode(ltrim(str_replace('\\', '/', substr($_SERVER['SCRIPT_NAME'] ?? '', strpos($_SERVER['SCRIPT_NAME'] ?? '', '/user/'))), '/')
    . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));

$user = $sqGuest ? ['full_name' => t('guest.name'), 'email' => '', 'profile_image' => ''] : $_SESSION['user'];

if (!isset($activePage)) {
    $activePage = '';
}

if (!function_exists('getUserProfileImageUrl')) {
    function getUserProfileImageUrl($profileImage)
    {
        if (empty($profileImage)) {
            return '';
        }

        $profileImage = trim($profileImage);

        if (preg_match('#^https?://#i', $profileImage)) {
            return $profileImage;
        }

        if (strpos($profileImage, 'uploads/profile_images/') === 0) {
            return '../' . $profileImage;
        }

        if (strpos($profileImage, 'uploads/profiles/') === 0) {
            return '../' . $profileImage;
        }

        return '../' . ltrim($profileImage, '/');
    }
}

$profileImageUrl = getUserProfileImageUrl($user['profile_image'] ?? '');
$profileImageSrc = preg_match('#^https?://#i', $profileImageUrl)
    ? $profileImageUrl
    : ($profileImageUrl !== '' ? $profileImageUrl . '?v=' . time() : '');
$userInitial = smartq_initial($user['full_name'] ?? 'U', 'U');
$currentDate = sq_date('l, M d, Y');
?>

<aside class="sq-sidebar" id="sqSidebar" aria-label="<?= t('shell.main_nav') ?>">
    <a href="index.php" class="sq-sidebar__brand sq-lockup" aria-label="<?= t('shell.home_aria') ?>">
        <span class="sq-logo-mark"><img src="../img/logo-small.png" alt=""></span>
        <span><span class="sq-lockup__word" dir="ltr">smart<em>Q</em></span><span class="sq-lockup__role"><?= t('role.user') ?></span></span>
    </a>

    <span class="sq-sidebar__label"><?= t('shell.menu') ?></span>
    <nav class="sq-sidebar__nav">
        <?php if ($sqGuest): ?>
        <a href="../index.html" class="sq-side-link"><i class="fa-solid fa-house"></i><span><?= t('guest.home') ?></span></a>
        <a href="places.php" class="sq-side-link <?= $activePage === 'places' ? 'active' : '' ?>"><i class="fa-solid fa-location-dot"></i><span><?= t('shell.user.places') ?></span></a>
        <a href="../login.php?guest=1&amp;next=<?= rawurlencode('user/appointment.php') ?>" class="sq-side-link"><i class="fa-solid fa-ticket"></i><span><?= t('shell.user.appointments') ?></span></a>
        <?php else: ?>
        <a href="index.php" class="sq-side-link <?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i><span><?= t('shell.dashboard') ?></span></a>
        <a href="places.php" class="sq-side-link <?= $activePage === 'places' ? 'active' : '' ?>"><i class="fa-solid fa-location-dot"></i><span><?= t('shell.user.places') ?></span></a>
        <a href="appointment.php" class="sq-side-link <?= $activePage === 'appointments' ? 'active' : '' ?>"><i class="fa-solid fa-ticket"></i><span><?= t('shell.user.appointments') ?></span></a>
        <?php endif; ?>
    </nav>

    <div class="sq-sidebar__foot">
        <?= smartq_prefs('sq-prefs--side') ?>
        <div class="sq-side-promo">
            <strong><?= t('shell.user.promo_title') ?></strong>
            <?= t('shell.user.promo_text') ?>
            <a href="https://drive.usercontent.google.com/download?id=15uEVxpWX8XrNO_Pps8ss4AWoXTe64ZWq&amp;export=download&amp;confirm=t" download><i class="fa-solid fa-mobile-screen-button"></i> <?= t('shell.user.promo_cta') ?></a>
        </div>
    </div>
</aside>
<div class="sq-scrim" data-sq-close></div>

<header class="sq-topbar sqm-topbar">
    <?php if (!$sqGuest): ?>
    <a href="profile.php" class="sqm-hello" aria-label="<?= t('shell.profile_settings') ?>">
        <span class="sqm-hello__av"><?php if ($profileImageSrc !== ''): ?><img src="<?= htmlspecialchars($profileImageSrc) ?>" alt=""><?php else: ?><?= $userInitial ?><?php endif; ?></span>
        <span class="sqm-hello__txt"><small><?= sqm_greeting() ?></small><b><?= htmlspecialchars($user['full_name']) ?></b></span>
    </a>
    <?php endif; ?>
    <button type="button" class="sq-icon-btn sq-topbar__menu" data-sq-toggle="sidebar" aria-label="<?= t('shell.open_menu') ?>" aria-controls="sqSidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <a href="index.php" class="sq-topbar__logo sq-logo-mark" aria-label="<?= t('shell.home_aria') ?>"><img src="../img/logo-small.png" alt=""></a>

    <form action="places.php" method="GET" class="sq-search" role="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" placeholder="<?= t('shell.user.search') ?>" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" aria-label="<?= t('shell.user.search_aria') ?>">
    </form>

    <div class="sq-topbar__right">
        <span class="sq-date-pill"><i class="fa-solid fa-calendar"></i><?= htmlspecialchars($currentDate) ?></span>
        <?= smartq_prefs('sq-prefs--top') ?>

        <?php if ($sqGuest): ?>
        <div class="sq-guest-actions" style="display:flex;gap:8px;align-items:center">
            <a href="../login.php?guest=1&amp;next=<?= $sqNext ?>" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket sq-flip"></i> <span><?= t('guest.login') ?></span></a>
        </div>
        <?php else: ?>
        <div class="sq-user">
            <button type="button" class="sq-user__btn" aria-label="<?= t('shell.account_menu') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="sqAccountMenu">
                <span class="sq-avatar-sm">
                    <?php if ($profileImageSrc !== ''): ?>
                        <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="">
                    <?php else: ?>
                        <?= $userInitial ?>
                    <?php endif; ?>
                </span>
                <span><b><?= htmlspecialchars($user['full_name']) ?></b><small><?= t('role.user') ?></small></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div class="sq-dropdown" id="sqAccountMenu">
                <div class="sq-dropdown__head">
                    <span class="sq-avatar-sm">
                        <?php if ($profileImageSrc !== ''): ?>
                            <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="">
                        <?php else: ?>
                            <?= $userInitial ?>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                        <span dir="ltr"><?= htmlspecialchars($user['email']) ?></span>
                        <span class="sq-dropdown__role"><?= t('role.user') ?></span>
                    </div>
                </div>
                <a href="profile.php"><i class="fa-solid fa-user-gear"></i> <?= t('shell.profile_settings') ?></a>
                <?= smartq_prefs_menu() ?>
                <div class="sq-dropdown__sep" role="separator"></div>
                <a href="../logout.php" class="danger-link"><i class="fa-solid fa-right-from-bracket"></i> <?= t('shell.logout') ?></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>
<?php
// Phone: bottom tab bar like the Android app (hidden on the booking / ticket steps).
if (empty($sqHideTabs)) {
    if ($sqGuest) {
        echo sqm_tabbar([
            ['../index.html', 'home', t('m.tab.home'), false],
            ['places.php', 'store', t('m.tab.places'), $activePage === 'places'],
            ['../login.php?guest=1&amp;next=' . rawurlencode('user/appointment.php'), 'ticket', t('m.tab.bookings'), false],
            ['../login.php?guest=1&amp;next=' . $sqNext, 'login', t('m.tab.login'), false],
        ], t('m.tabs'));
    } else {
        echo sqm_tabbar([
            ['index.php', 'home', t('m.tab.home'), $activePage === 'dashboard'],
            ['places.php', 'store', t('m.tab.places'), $activePage === 'places'],
            ['appointment.php', 'ticket', t('m.tab.bookings'), $activePage === 'appointments'],
            ['profile.php', 'user', t('m.tab.profile'), in_array($activePage, ['profile', 'settings'], true)],
        ], t('m.tabs'));
    }
}
?>
<?php if ($sqGuest): ?>
<div class="sq-guest-banner" role="note" style="margin:12px 24px 0;padding:12px 16px;border-radius:14px;background:var(--surface-alt,#EEF4FC);color:var(--text,#0B1B36);border:1px solid var(--border,#E2E9F4);display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <span><i class="fa-solid fa-eye" style="color:var(--blue-600,#1F6FE0)"></i> <?= t('guest.banner') ?></span>
    <a href="../login.php?guest=1&amp;next=<?= $sqNext ?>" class="btn btn-primary"><?= t('guest.login_to_book') ?></a>
</div>
<?php endif; ?>
