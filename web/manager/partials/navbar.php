<?php
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/i18n.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$user = $_SESSION['user'];
$activePage = $activePage ?? '';
$todayLabel = sq_date('l, M d, Y');
$initial = smartq_initial($user['full_name'] ?? 'M', 'M');
$avatarHtml = !empty($user['profile_image'])
    ? '<img src="' . htmlspecialchars(getProfileImageUrl($user['profile_image'] ?? '')) . '" alt="">'
    : $initial;
?>

<aside class="sq-sidebar" id="sqSidebar" aria-label="<?= t('shell.main_nav') ?>">
    <a href="index.php" class="sq-sidebar__brand sq-lockup" aria-label="<?= t('shell.home_aria') ?>">
        <span class="sq-logo-mark"><img src="../img/logo-small.png" alt=""></span>
        <span><span class="sq-lockup__word" dir="ltr">smart<em>Q</em></span><span class="sq-lockup__role"><?= t('role.manager') ?></span></span>
    </a>

    <span class="sq-sidebar__label"><?= t('shell.menu') ?></span>
    <nav class="sq-sidebar__nav">
        <a href="index.php" class="sq-side-link <?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i><span><?= t('shell.dashboard') ?></span></a>
        <a href="add-place.php" class="sq-side-link <?= $activePage === 'places' ? 'active' : '' ?>"><i class="fa-solid fa-location-dot"></i><span><?= t('shell.manager.place') ?></span></a>
        <a href="schedules.php" class="sq-side-link <?= $activePage === 'schedules' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i><span><?= t('shell.manager.schedules') ?></span></a>
        <a href="bookings.php" class="sq-side-link <?= $activePage === 'bookings' ? 'active' : '' ?>"><i class="fa-solid fa-clipboard-list"></i><span><?= t('shell.manager.bookings') ?></span></a>
        <a href="customers.php" class="sq-side-link <?= $activePage === 'customers' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i><span><?= t('shell.manager.customers') ?></span></a>
    </nav>

    <div class="sq-sidebar__foot">
        <?= smartq_prefs('sq-prefs--side') ?>
        <div class="sq-side-promo">
            <strong><?= t('shell.manager.promo_title') ?></strong>
            <?= t('shell.manager.promo_text') ?>
            <a href="add-schedule.php"><i class="fa-solid fa-calendar-plus"></i> <?= t('shell.manager.promo_cta') ?></a>
        </div>
    </div>
</aside>
<div class="sq-scrim" data-sq-close></div>

<header class="sq-topbar sqm-topbar">
    <button type="button" class="sq-icon-btn sq-topbar__menu" data-sq-toggle="sidebar" aria-label="<?= t('shell.open_menu') ?>" aria-controls="sqSidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <a href="index.php" class="sq-topbar__logo sq-logo-mark" aria-label="<?= t('shell.home_aria') ?>"><img src="../img/logo-small.png" alt=""></a>
    <span class="sqm-hello sqm-hello--staff"><span class="sqm-hello__txt"><small><?= sqm_greeting() ?></small><b><?= htmlspecialchars($user['full_name']) ?></b></span></span>

    <form class="sq-search" action="bookings.php" method="GET" role="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="search" placeholder="<?= t('shell.manager.search') ?>" aria-label="<?= t('shell.search') ?>">
    </form>

    <div class="sq-topbar__right">
        <span class="sq-date-pill"><i class="fa-solid fa-calendar"></i><?= htmlspecialchars($todayLabel) ?></span>
        <?= smartq_prefs('sq-prefs--top') ?>

        <div class="sq-user">
            <button type="button" class="sq-user__btn" aria-label="<?= t('shell.account_menu') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="sqAccountMenu">
                <span class="sq-avatar-sm"><?= $avatarHtml ?></span>
                <span><b><?= htmlspecialchars($user['full_name']) ?></b><small><?= t('role.manager') ?></small></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div class="sq-dropdown" id="sqAccountMenu">
                <div class="sq-dropdown__head">
                    <span class="sq-avatar-sm"><?= $avatarHtml ?></span>
                    <div>
                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                        <span dir="ltr"><?= htmlspecialchars($user['email']) ?></span>
                        <span class="sq-dropdown__role"><?= t('role.manager') ?></span>
                    </div>
                </div>
                <a href="profile.php"><i class="fa-solid fa-user-gear"></i> <?= t('shell.profile_settings') ?></a>
                <?= smartq_prefs_menu() ?>
                <div class="sq-dropdown__sep" role="separator"></div>
                <a href="../logout.php" class="danger-link"><i class="fa-solid fa-right-from-bracket"></i> <?= t('shell.logout') ?></a>
            </div>
        </div>
    </div>
</header>
<?php
// Phone: bottom tab bar (main sections) + "More" opens the sidebar drawer.
echo sqm_tabbar([
    ['index.php', 'grid', t('m.tab.dashboard'), $activePage === 'dashboard'],
    ['schedules.php', 'calendar', t('m.tab.schedules'), $activePage === 'schedules'],
    ['bookings.php', 'list', t('m.tab.bookings'), $activePage === 'bookings'],
    ['customers.php', 'users', t('m.tab.customers'), $activePage === 'customers'],
    [null, 'more', t('m.tab.more'), false, 'data-sq-toggle="sidebar" aria-controls="sqSidebar" aria-expanded="false"'],
], t('m.tabs'));
?>
