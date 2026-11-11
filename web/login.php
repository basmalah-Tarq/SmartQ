<?php
require_once 'config/db.php';
require_once 'config/security.php';
require_once 'config/auth_service.php';
require_once 'config/i18n.php';
$authOptions = smartq_auth_options();

$message = '';
$messageType = '';
$signupSuccess = $_SESSION['signup_success'] ?? '';
unset($_SESSION['signup_success']);

$selectedRoleValue = $_POST['selected_role'] ?? '';
$emailValue = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['selected_role'] ?? '';

    $selectedRoleValue = $selectedRole;
    $emailValue = $email;

    $normalizedEmail = strtolower(trim($email));
    $normalizedRole = strtolower(trim($selectedRole));

    if ($normalizedRole === '' || !in_array($normalizedRole, ['user', 'manager', 'admin'], true)) {
        $message = t_raw('login.err_role');
        $messageType = 'error';
    } else {
        /*
         * Read the account by normalized email first. This avoids login failures
         * caused by accidental spaces or inconsistent role casing in older data.
         */
        $stmt = $conn->prepare("SELECT id, full_name, email, password, phone, birth_date, profile_image, role FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1");
        $stmt->bind_param("s", $normalizedEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $storedRole = strtolower(trim((string)($user['role'] ?? '')));
        $storedPassword = (string)($user['password'] ?? '');
        $passwordValid = false;

        if ($user && $storedRole === $normalizedRole) {
            $passwordValid = password_verify($password, $storedPassword);

            /* Upgrade a legacy plain-text password automatically if one exists. */
            // Only for really old rows that were never hashed (a bcrypt/argon hash must never be accepted as a password).
            $looksHashed = password_get_info($storedPassword)['algo'] !== null && password_get_info($storedPassword)['algo'] !== 0;
            if (!$passwordValid && !$looksHashed && $storedPassword !== '' && hash_equals($storedPassword, $password)) {
                $passwordValid = true;
            }
            // (Removed: a hard-coded "admin@smartq.com / 123456" bypass that let anyone take over the admin account.)
        }

        if ($user && $storedRole === $normalizedRole && $passwordValid) {
            if (!password_verify($password, $storedPassword) || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $freshHash = password_hash($password, PASSWORD_DEFAULT);
                $repairStmt = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
                $repairStmt->bind_param("ssi", $freshHash, $normalizedRole, $user['id']);
                $repairStmt->execute();
                $user['password'] = $freshHash;
                $user['role'] = $normalizedRole;
            }

            session_regenerate_id(true); // new session id after login (prevents session fixation)
            $_SESSION['user'] = $user;

            if ($normalizedRole === 'admin') {
                header('Location: admin/index.php');
            } elseif ($normalizedRole === 'manager') {
                header('Location: manager/index.php');
            } else {
                // Guests who were sent here from "Book" go straight back to what they were doing.
                $next = smartq_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
                header('Location: ' . ($next !== '' ? $next : 'user/index.php'));
            }
            exit();
        }

        $message = t_raw('login.err_invalid');
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F5F8FD">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= t('login.title') ?></title>
    <link rel="icon" type="image/png" href="img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/smartq-ui.css?v=5">
    <link rel="stylesheet" href="css/smartq-motion.css?v=5">
    <script defer src="js/smartq-ui.js?v=5"></script>
    <script defer src="js/smartq-motion.js?v=5"></script>
    <script defer src="js/smartq-auth.js?v=5"></script>
    <?php if (!empty($authOptions['google'])): ?>
    <script src="https://accounts.google.com/gsi/client" async defer onload="window.sqGoogleReady && window.sqGoogleReady()"></script>
    <?php endif; ?>
    <?= smartq_head('') ?>
</head>
<body class="ax">
<div class="ax-shell">
    <aside class="ax-brand">
        <a href="index.html" class="sq-lockup sq-lockup--lg" aria-label="<?= t('shell.home_aria') ?>">
            <span class="sq-logo-mark"><img src="img/logo-small.png" alt=""></span>
            <span><span class="sq-lockup__word" dir="ltr">smart<em>Q</em></span><span class="sq-lockup__tag"><?= t('auth.tagline') ?></span></span>
        </a>
        <div class="ax-brand__copy">
            <h1 class="ax-title" data-split><?= th('login.brand_title') ?></h1>
            <p><?= t('login.brand_text') ?></p>
        </div>

        <div class="ax-scene" aria-hidden="true">
            <div class="ax-scene__rig">
                <div class="ax-ticket">
                    <div class="ax-ticket__top">
                        <div>
                            <small><?= t('login.now_serving') ?></small>
                            <div class="sq-ticker sq-ltr" data-sq-ticker="38" data-mine="42" data-bar="#authBar" data-interval="2800">Q-038</div>
                        </div>
                        <span class="lx-tag"><span class="sq-live-dot"></span> <?= t('auth.live') ?></span>
                    </div>
                    <div class="ax-ticket__bar" id="authBar"><i></i></div>
                    <div class="ax-ticket__cut"></div>
                    <div class="ax-ticket__grid">
                        <div><span><?= t('auth.your_number') ?></span><b class="sq-ltr">Q-042</b></div>
                        <div><span><?= t('auth.date') ?></span><b><?= t('auth.today') ?></b></div>
                        <div><span><?= t('auth.time') ?></span><b><?= htmlspecialchars(sq_time('10:30')) ?></b></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ax-feed" aria-hidden="true">
            <div class="ax-feed__item is-on"><span class="ax-feed__av">S</span><span><?= th('login.feed1') ?></span><span class="ax-feed__time"><?= htmlspecialchars(sq_time('10:30')) ?></span></div>
            <div class="ax-feed__item"><span class="ax-feed__av" style="--ac:#14A39A">O</span><span><?= th('login.feed2') ?></span><span class="ax-feed__time"><?= htmlspecialchars(sq_time('14:00')) ?></span></div>
            <div class="ax-feed__item"><span class="ax-feed__av" style="--ac:#12306B">L</span><span><?= th('login.feed3') ?></span><span class="ax-feed__time"><?= htmlspecialchars(sq_time('11:15')) ?></span></div>
        </div>

        <div class="ax-perks">
            <span><i class="fa-solid fa-check"></i> <?= t('login.perk1') ?></span>
            <span><i class="fa-solid fa-check"></i> <?= t('login.perk2') ?></span>
            <span><i class="fa-solid fa-check"></i> <?= t('login.perk3') ?></span>
        </div>
    </aside>

    <main class="ax-main">
        <div class="ax-top">
            <a href="index.html" class="sq-lockup ax-top__logo" aria-label="<?= t('shell.home_aria') ?>">
                <span class="sq-logo-mark"><img src="img/logo-small.png" alt=""></span>
                <span class="sq-lockup__word" dir="ltr">smart<em>Q</em></span>
            </a>
            <a href="index.html" class="ax-back"><i class="fa-solid fa-arrow-left sq-flip"></i> <span><?= t('auth.back_home') ?></span></a>
            <div class="ax-top__end">
                <?= smartq_prefs('sq-prefs--auth') ?>
                <span class="ax-top__alt"><span class="ax-top__q"><?= t('login.new_here') ?></span> <a href="signup.php"><?= t('login.create_account') ?></a></span>
            </div>
        </div>

        <!-- phone only: app-style welcome header with the live ticket (css/smartq-mobile.css) -->
        <div class="sqm-authhero" aria-hidden="true">
            <div class="sqm-landcard__ticket">
                <span class="sqm-landcard__serving"><i></i> <span><?= t('m.now_serving') ?></span> <b dir="ltr">Q-005</b></span>
                <small><?= t('m.your_turn') ?></small>
                <strong dir="ltr">Q-007</strong>
                <span class="sqm-landcard__cut"></span>
                <span class="sqm-landcard__meta"><i class="fa-solid fa-briefcase-medical"></i> <span><?= htmlspecialchars(sq_time('10:30')) ?> · <?= t('m.city_clinic') ?></span></span>
            </div>
        </div>

        <section class="ax-card" data-auth data-mode="code">
            <header class="ax-head">
                <h2><?= t('login.heading') ?></h2>
                <p class="ax-sub"><span data-sub="code"><?= t('login.sub_code') ?></span><span data-sub="password"><?= t('login.sub_password') ?></span></p>
                <?php if (!empty($_GET['guest'])): ?>
                <p class="ax-sub" role="note" style="margin-top:10px;padding:10px 12px;border-radius:12px;background:var(--surface-alt,#EEF4FC);color:var(--text,#0B1B36)"><i class="fa-solid fa-circle-info"></i> <?= t('login.guest_notice') ?></p>
                <?php endif; ?>
                <p class="ax-sub" style="margin-top:8px"><a href="user/places.php"><i class="fa-solid fa-eye"></i> <?= t('login.browse_guest') ?></a></p>
            </header>

            <?php if ($signupSuccess): ?>
                <div class="message success"><?= htmlspecialchars(t_msg($signupSuccess)) ?></div>
            <?php endif; ?>

            <?php if (!empty($authOptions['email_code']) || !empty($authOptions['google'])): ?>
            <div class="ax-pane ax-pane--code" data-endpoint="auth/email.php">
                <?php if (!empty($authOptions['google'])): ?>
                    <div data-code-only-step1>
                        <div class="ax-google" data-google-btn data-client-id="<?= htmlspecialchars($authOptions['google_client_id']) ?>" data-endpoint="auth/google.php" data-context="signin" data-text="continue_with"></div>
                        <p class="fl__err" data-google-err role="alert"></p>
                        <?php if (!empty($authOptions['email_code'])): ?><div class="ax-or"><span><?= t('auth.or') ?></span></div><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($authOptions['email_code'])): ?>
                <div data-code-step1>
                    <form data-code-start data-validate novalidate>
                        <div class="fl">
                            <label for="codeEmail"><?= t('auth.email') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                <input type="email" id="codeEmail" name="email" placeholder="<?= t('auth.email_ph') ?>" autocomplete="email" inputmode="email" dir="ltr" required>
                            </div>
                            <p class="fl__err" id="codeEmailErr" role="alert"></p>
                        </div>
                        <button type="submit" class="btn btn-primary ax-submit"><span class="ax-spin" aria-hidden="true"></span><span data-label><?= t('auth.continue_email') ?></span> <i class="fa-solid fa-arrow-right sq-flip"></i></button>
                        <p class="ax-hint"><i class="fa-solid fa-shield-halved"></i><span><?= t('login.code_hint') ?></span></p>
                    </form>
                </div>

                <div class="ax-verify" data-code-verify hidden>
                    <span class="ax-verify__icon" aria-hidden="true"><i class="fa-solid fa-envelope-open"></i></span>
                    <h3><?= t('auth.check_email') ?></h3>
                    <p><?= th('auth.code_sent') ?></p>
                    <form data-code-form novalidate>
                        <span class="ax-label" id="otpLabel"><?= t('auth.enter_code') ?></span>
                        <div class="ax-otp" role="group" aria-labelledby="otpLabel" dir="ltr">
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-label="<?= t('auth.digit', ['n' => 1]) ?>">
                            <input type="text" inputmode="numeric" maxlength="1" aria-label="<?= t('auth.digit', ['n' => 2]) ?>">
                            <input type="text" inputmode="numeric" maxlength="1" aria-label="<?= t('auth.digit', ['n' => 3]) ?>">
                            <span class="ax-otp__sep" aria-hidden="true"></span>
                            <input type="text" inputmode="numeric" maxlength="1" aria-label="<?= t('auth.digit', ['n' => 4]) ?>">
                            <input type="text" inputmode="numeric" maxlength="1" aria-label="<?= t('auth.digit', ['n' => 5]) ?>">
                            <input type="text" inputmode="numeric" maxlength="1" aria-label="<?= t('auth.digit', ['n' => 6]) ?>">
                        </div>
                        <p class="fl__err" data-code-err role="alert"></p>
                        <button type="submit" class="btn btn-primary ax-submit"><span class="ax-spin" aria-hidden="true"></span><span data-label><?= t('login.verify') ?></span> <i class="fa-solid fa-arrow-right sq-flip"></i></button>
                    </form>
                    <div class="ax-resend">
                        <button type="button" class="ax-linkbtn" data-code-change><i class="fa-solid fa-arrow-left sq-flip"></i> <?= t('auth.different_email') ?></button>
                        <button type="button" class="ax-linkbtn" data-code-resend disabled><?= t('auth.resend') ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <div data-code-only-step1>
                    <div class="ax-or"><span><?= t('auth.or') ?></span></div>
                    <button type="button" class="btn btn-secondary ax-alt" data-show-pane="password"><i class="fa-solid fa-key"></i> <?= t('login.use_password') ?></button>
                </div>
            </div>
            <?php endif; ?>

            <div class="ax-pane ax-pane--password">
                <?php if (!empty($authOptions['email_code']) || !empty($authOptions['google'])): ?>
                    <button type="button" class="ax-linkbtn ax-pane__back" data-show-pane="code"><i class="fa-solid fa-arrow-left sq-flip"></i> <?= t('login.use_code') ?></button>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateLogin()" data-loading data-validate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="selected_role" id="selected_role" value="<?= htmlspecialchars($selectedRoleValue) ?>">

                    <div class="ax-roles" id="roleGroup">
                        <span class="ax-label" id="roleLabel"><?= t('login.signing_as') ?></span>
                        <div class="role-cards" role="radiogroup" aria-labelledby="roleLabel">
                            <div class="role-card <?= $selectedRoleValue === 'user' ? 'active' : '' ?>" data-role="user" role="radio" aria-checked="<?= $selectedRoleValue === 'user' ? 'true' : 'false' ?>" tabindex="0">
                                <div class="icon"><i class="fa-solid fa-user"></i></div>
                                <h3><?= t('role.user') ?></h3>
                                <p><?= t('login.user_desc') ?></p>
                            </div>

                            <div class="role-card <?= $selectedRoleValue === 'manager' ? 'active' : '' ?>" data-role="manager" role="radio" aria-checked="<?= $selectedRoleValue === 'manager' ? 'true' : 'false' ?>" tabindex="0">
                                <div class="icon"><i class="fa-solid fa-briefcase"></i></div>
                                <h3><?= t('role.manager') ?></h3>
                                <p><?= t('login.manager_desc') ?></p>
                            </div>

                            <div class="role-card <?= $selectedRoleValue === 'admin' ? 'active' : '' ?>" data-role="admin" role="radio" aria-checked="<?= $selectedRoleValue === 'admin' ? 'true' : 'false' ?>" tabindex="0">
                                <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
                                <h3><?= t('role.admin') ?></h3>
                                <p><?= t('login.admin_desc') ?></p>
                            </div>
                        </div>
                        <p class="fl__err" id="roleErr" role="alert"></p>
                    </div>

                    <div class="fl">
                        <label for="loginEmail"><?= t('auth.email') ?></label>
                        <div class="fl__ctl">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <input type="email" id="loginEmail" name="email" value="<?= htmlspecialchars($emailValue) ?>" placeholder="<?= t('auth.email_ph') ?>" autocomplete="email" dir="ltr" required>
                        </div>
                        <p class="fl__err" id="loginEmailErr" role="alert"></p>
                    </div>

                    <div class="fl fl--pw">
                        <label for="loginPassword"><?= t('auth.password') ?></label>
                        <div class="fl__ctl">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <input type="password" id="loginPassword" name="password" placeholder="<?= t('login.password_ph') ?>" autocomplete="current-password" required>
                            <button type="button" class="sq-pw-toggle" data-sq-pw aria-label="<?= t('auth.show_password') ?>"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <p class="fl__err" id="loginPasswordErr" role="alert"></p>
                    </div>

                    <button type="submit" class="btn btn-primary ax-submit" data-loading-text="<?= t('login.submitting') ?>"><span class="ax-spin" aria-hidden="true"></span><span data-label><?= t('login.submit') ?></span> <i class="fa-solid fa-arrow-right sq-flip"></i></button>
                </form>
            </div>

            <p class="ax-foot"><?= t('login.foot') ?> <a href="signup.php"><?= t('login.foot_link') ?></a></p>
        </section>
    </main>
</div>

<script src="js/login-role.js?v=5"></script>
<script>
document.querySelectorAll('.role-card').forEach(function (card) {
    card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
    card.addEventListener('click', function () {
        document.querySelectorAll('.role-card').forEach(function (c) { c.setAttribute('aria-checked', c === card ? 'true' : 'false'); });
        document.getElementById('roleGroup').classList.remove('is-invalid');
        document.getElementById('roleErr').textContent = '';
    });
});
function validateLogin() {
    const selectedRole = document.getElementById('selected_role').value;
    if (!selectedRole) {
        document.getElementById('roleGroup').classList.add('is-invalid');
        document.getElementById('roleErr').textContent = <?= json_encode(t_raw('login.err_role_js'), JSON_UNESCAPED_UNICODE) ?>;
        document.querySelector('.role-card').focus();
        return false;
    }
    return true;
}
</script>
</body>
</html>
