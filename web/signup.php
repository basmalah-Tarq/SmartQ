<?php
require_once 'config/db.php';
require_once 'config/security.php';
require_once 'config/helpers.php';
require_once 'config/places.php';
require_once 'config/i18n.php';
$placeCategories = smartq_categories($conn);
require_once 'config/auth_service.php';
$authOptions = smartq_auth_options();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    $managerPlaceName = trim($_POST['manager_place_name'] ?? '');
    $managerCategory = trim($_POST['manager_category'] ?? '');
    $managerCity = trim($_POST['manager_city'] ?? '');
    $managerAddress = trim($_POST['manager_address'] ?? '');
    $managerDescription = trim($_POST['manager_description'] ?? '');

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $message = t_raw('signup.err_required');
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = t_raw('signup.err_email');
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = t_raw('signup.err_short');
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = t_raw('signup.err_match');
        $messageType = 'error';
    } elseif (!in_array($role, ['user', 'manager'], true)) {
        $message = t_raw('signup.err_role');
        $messageType = 'error';
    } elseif ($role === 'manager' && ($managerPlaceName === '' || $managerCategory === '' || $managerCity === '')) {
        $message = t_raw('signup.err_place');
        $messageType = 'error';
    } elseif ($role === 'manager' && ($placeError = smartq_place_input($conn, $_POST, '', ['place_name' => 'manager_place_name', 'category' => 'manager_category', 'city' => 'manager_city', 'address' => 'manager_address', 'description' => 'manager_description'])[1]) !== '') {
        $message = t_msg($placeError);
        $messageType = 'error';
    } elseif (smartq_optional_date($birthDate) === false) {
        $message = t_raw('signup.err_birth');
        $messageType = 'error';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $existingUser = $checkStmt->get_result()->fetch_assoc();

        if ($existingUser) {
            $message = t_raw('signup.err_exists');
            $messageType = 'error';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $profileImage = null;
            $birthDateValue = smartq_optional_date($birthDate);
            $phoneValue = $phone !== '' ? $phone : null;

            $conn->begin_transaction();

            try {
                $insertStmt = $conn->prepare("
                    INSERT INTO users (full_name, email, password, phone, birth_date, profile_image, role)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param(
                    "sssssss",
                    $fullName,
                    $email,
                    $hashedPassword,
                    $phoneValue,
                    $birthDateValue,
                    $profileImage,
                    $role
                );
                $insertStmt->execute();

                $newUserId = (int)$conn->insert_id;

                if ($role === 'manager') {
                    $placeStmt = $conn->prepare("
                        INSERT INTO places (manager_id, place_name, category, city, address, description, active)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $placeStmt->bind_param(
                        "isssss",
                        $newUserId,
                        $managerPlaceName,
                        $managerCategory,
                        $managerCity,
                        $managerAddress,
                        $managerDescription
                    );
                    $placeStmt->execute();
                }

                $conn->commit();

                $_SESSION['signup_success'] = t_raw('login.signup_success');
                header("Location: login.php");
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $message = t_raw('signup.err_failed');
                $messageType = 'error';
            }
        }
    }
}

$selectedRole = $_POST['role'] ?? 'user';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F5F8FD">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= t('signup.title') ?></title>
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
            <h1 class="ax-title" data-split><?= th('signup.brand_title') ?></h1>
            <p><?= t('signup.brand_text') ?></p>
        </div>

        <div class="ax-scene" aria-hidden="true">
            <div class="ax-scene__rig">
                <div class="ax-ticket">
                    <div class="ax-ticket__top">
                        <div>
                            <small><?= t('signup.first_ticket') ?></small>
                            <div class="sq-ticker sq-ltr" data-sq-ticker="1" data-mine="7" data-bar="#authBar" data-interval="2800">Q-001</div>
                        </div>
                        <span class="lx-tag"><span class="sq-live-dot"></span> <?= t('auth.live') ?></span>
                    </div>
                    <div class="ax-ticket__bar" id="authBar"><i></i></div>
                    <div class="ax-ticket__cut"></div>
                    <div class="ax-ticket__grid">
                        <div><span><?= t('auth.place') ?></span><b>Glow Salon</b></div>
                        <div><span><?= t('auth.date') ?></span><b><?= t('auth.today') ?></b></div>
                        <div><span><?= t('auth.time') ?></span><b><?= htmlspecialchars(sq_time('14:00')) ?></b></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ax-feed" aria-hidden="true">
            <div class="ax-feed__item is-on"><span class="ax-feed__av">S</span><span><?= th('signup.feed1') ?></span><span class="ax-feed__time"><?= htmlspecialchars(sq_time('10:30')) ?></span></div>
            <div class="ax-feed__item"><span class="ax-feed__av" style="--ac:#14A39A">M</span><span><?= th('signup.feed2') ?></span><span class="ax-feed__time"><?= t('signup.just_now') ?></span></div>
            <div class="ax-feed__item"><span class="ax-feed__av" style="--ac:#12306B">K</span><span><?= th('signup.feed3') ?></span><span class="ax-feed__time"><?= htmlspecialchars(sq_time('18:30')) ?></span></div>
        </div>

        <div class="ax-perks">
            <span><i class="fa-solid fa-check"></i> <?= t('signup.perk1') ?></span>
            <span><i class="fa-solid fa-check"></i> <?= t('signup.perk2') ?></span>
            <span><i class="fa-solid fa-check"></i> <?= t('signup.perk3') ?></span>
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
                <span class="ax-top__alt"><span class="ax-top__q"><?= t('signup.have_account') ?></span> <a href="login.php"><?= t('signup.login') ?></a></span>
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

        <section class="ax-card ax-card--wide" data-auth data-mode="code">
            <header class="ax-head">
                <h2><?= t('signup.heading') ?></h2>
                <p class="ax-sub"><span data-sub="code"><?= t('signup.sub_code') ?></span><span data-sub="password"><?= t('signup.sub_password') ?></span></p>
            </header>

            <?php if (!empty($authOptions['email_code']) || !empty($authOptions['google'])): ?>
            <div class="ax-pane ax-pane--code" data-endpoint="auth/email.php">
                <?php if (!empty($authOptions['google'])): ?>
                    <div data-code-only-step1>
                        <div class="ax-google" data-google-btn data-client-id="<?= htmlspecialchars($authOptions['google_client_id']) ?>" data-endpoint="auth/google.php" data-context="signup" data-text="signup_with"></div>
                        <p class="fl__err" data-google-err role="alert"></p>
                        <?php if (!empty($authOptions['email_code'])): ?><div class="ax-or"><span><?= t('auth.or') ?></span></div><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($authOptions['email_code'])): ?>
                <div data-code-step1>
                    <form data-code-start data-validate novalidate>
                        <div class="fl">
                            <label for="codeName"><?= t('signup.full_name') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <input type="text" id="codeName" name="full_name" placeholder="<?= t('signup.name_ph') ?>" autocomplete="name" required minlength="2">
                            </div>
                            <p class="fl__err" id="codeNameErr" role="alert"></p>
                        </div>
                        <div class="fl">
                            <label for="codeEmail"><?= t('auth.email') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                <input type="email" id="codeEmail" name="email" placeholder="<?= t('auth.email_ph') ?>" autocomplete="email" inputmode="email" dir="ltr" required>
                            </div>
                            <p class="fl__err" id="codeEmailErr" role="alert"></p>
                        </div>
                        <button type="submit" class="btn btn-primary ax-submit"><span class="ax-spin" aria-hidden="true"></span><span data-label><?= t('auth.continue_email') ?></span> <i class="fa-solid fa-arrow-right sq-flip"></i></button>
                        <p class="ax-hint"><i class="fa-solid fa-shield-halved"></i><span><?= t('signup.code_hint') ?></span></p>
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
                        <button type="submit" class="btn btn-primary ax-submit"><span class="ax-spin" aria-hidden="true"></span><span data-label><?= t('signup.verify') ?></span> <i class="fa-solid fa-arrow-right sq-flip"></i></button>
                    </form>
                    <div class="ax-resend">
                        <button type="button" class="ax-linkbtn" data-code-change><i class="fa-solid fa-arrow-left sq-flip"></i> <?= t('auth.different_email') ?></button>
                        <button type="button" class="ax-linkbtn" data-code-resend disabled><?= t('auth.resend') ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <div data-code-only-step1>
                    <div class="ax-or"><span><?= t('auth.or') ?></span></div>
                    <button type="button" class="btn btn-secondary ax-alt" data-show-pane="password" data-role="manager"><i class="fa-solid fa-briefcase"></i> <?= t('signup.register_business') ?></button>
                    <p class="ax-hint" style="justify-content:center"><button type="button" class="ax-linkbtn" data-show-pane="password" data-role="user"><?= t('signup.use_password') ?></button></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="ax-pane ax-pane--password">
                <?php if (!empty($authOptions['email_code']) || !empty($authOptions['google'])): ?>
                    <button type="button" class="ax-linkbtn ax-pane__back" data-show-pane="code"><i class="fa-solid fa-arrow-left sq-flip"></i> <?= t('signup.use_code') ?></button>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="signup-form" id="signupForm" data-loading data-validate>
                    <?= csrf_input() ?>
                    <ol class="ax-steps" aria-hidden="true">
                        <li data-s="role"><b><span>1</span></b><?= t('signup.step_role') ?></li>
                        <li data-s="profile"><b><span>2</span></b><?= t('signup.step_profile') ?></li>
                        <li data-s="security"><b><span>3</span></b><?= t('signup.step_security') ?></li>
                        <li data-s="place" class="ax-steps__place"><b><span>4</span></b><?= t('signup.step_place') ?></li>
                    </ol>
                    <div class="ax-progress" aria-hidden="true"><i></i></div>

                    <span class="ax-label" id="roleLabel"><?= t('signup.register_as') ?></span>
                    <div class="sq-segment" id="roleSelect" role="radiogroup" aria-labelledby="roleLabel">
                        <label><input type="radio" name="role" value="user" <?= $selectedRole !== 'manager' ? 'checked' : '' ?>><i class="fa-solid fa-user"></i><span><?= t('role.user') ?><small><?= t('signup.user_desc') ?></small></span></label>
                        <label><input type="radio" name="role" value="manager" <?= $selectedRole === 'manager' ? 'checked' : '' ?>><i class="fa-solid fa-briefcase"></i><span><?= t('role.manager') ?><small><?= t('signup.manager_desc') ?></small></span></label>
                    </div>

                    <div class="fl-row">
                        <div class="fl">
                            <label for="suName"><?= t('signup.full_name') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <input type="text" id="suName" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="<?= t('signup.name_ph') ?>" autocomplete="name" required>
                            </div>
                            <p class="fl__err" id="suNameErr" role="alert"></p>
                        </div>
                        <div class="fl">
                            <label for="suEmail"><?= t('auth.email') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                <input type="email" id="suEmail" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="<?= t('auth.email_ph') ?>" autocomplete="email" dir="ltr" required>
                            </div>
                            <p class="fl__err" id="suEmailErr" role="alert"></p>
                        </div>
                    </div>

                    <div class="fl">
                        <label for="suPhone"><?= t('signup.phone') ?> <small><?= t('common.optional') ?></small></label>
                        <div class="fl__ctl">
                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <input type="text" id="suPhone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="<?= t('signup.phone_ph') ?>" autocomplete="tel" inputmode="tel" dir="ltr">
                        </div>
                        <p class="fl__err" id="suPhoneErr" role="alert"></p>
                    </div>

                    <!-- Enhanced by JS into Day / Month / Year selects writing the same YYYY-MM-DD value -->
                    <div class="fl">
                        <label for="suBirth"><?= t('signup.birth') ?> <small><?= t('signup.birth_hint') ?></small></label>
                        <div class="fl__ctl">
                            <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                            <input type="text" id="suBirth" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" title="<?= t('signup.birth_title') ?>" inputmode="numeric" dir="ltr" data-datefield>
                        </div>
                        <p class="fl__err" id="suBirthErr" role="alert"></p>
                    </div>

                    <div class="fl-row">
                        <div class="fl fl--pw">
                            <label for="suPass"><?= t('auth.password') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <input type="password" id="suPass" name="password" placeholder="<?= t('signup.password_ph') ?>" autocomplete="new-password" required minlength="6">
                                <button type="button" class="sq-pw-toggle" data-sq-pw aria-label="<?= t('auth.show_password') ?>"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <p class="fl__err" id="suPassErr" role="alert"></p>
                        </div>
                        <div class="fl">
                            <label for="suPass2"><?= t('signup.confirm') ?></label>
                            <div class="fl__ctl">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <input type="password" id="suPass2" name="confirm_password" placeholder="<?= t('signup.confirm_ph') ?>" autocomplete="new-password" required data-match="suPass" data-err-required="<?= t('signup.confirm_required') ?>">
                                <i class="fa-solid fa-circle-check fl__ok" aria-hidden="true"></i>
                            </div>
                            <p class="fl__err" id="suPass2Err" role="alert"></p>
                        </div>
                    </div>
                    <div class="ax-meter" data-meter-for="suPass" data-score="0" aria-live="polite"><i></i><i></i><i></i><i></i><span></span></div>

                    <div class="manager-choice-card" id="managerFields">
                        <div class="sq-mgr-inner">
                            <div class="sq-mgr-box">
                                <div class="manager-choice-head">
                                    <div>
                                        <h3><?= t('signup.your_place') ?></h3>
                                        <p><?= t('signup.place_note') ?></p>
                                    </div>
                                    <i class="fa-solid fa-building-user"></i>
                                </div>

                                <div class="fl-row">
                                    <div class="fl fl--noicon">
                                        <label for="mgPlace"><?= t('signup.place_name') ?></label>
                                        <div class="fl__ctl"><input type="text" id="mgPlace" name="manager_place_name" value="<?= htmlspecialchars($_POST['manager_place_name'] ?? '') ?>" placeholder="<?= t('signup.place_name_ph') ?>"></div>
                                        <p class="fl__err" id="mgPlaceErr" role="alert"></p>
                                    </div>

                                    <div class="fl fl--noicon">
                                        <label for="mgType"><?= t('signup.place_type') ?></label>
                                        <div class="fl__ctl">
                                            <select id="mgType" name="manager_category">
                                                <option value=""><?= t('signup.choose_type') ?></option>
                                                <?php
                                                $types = $placeCategories; // shared list: config/categories.php
                                                $chosenType = $_POST['manager_category'] ?? '';
                                                foreach ($types as $type):
                                                ?>
                                                    <option value="<?= htmlspecialchars($type) ?>" <?= $chosenType === $type ? 'selected' : '' ?>><?= t_cat($type) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class="fl__err" id="mgTypeErr" role="alert"></p>
                                    </div>
                                </div>

                                <div class="fl-row">
                                    <div class="fl fl--noicon">
                                        <label for="mgCity"><?= t('signup.city') ?></label>
                                        <div class="fl__ctl"><input type="text" id="mgCity" name="manager_city" value="<?= htmlspecialchars($_POST['manager_city'] ?? '') ?>" placeholder="<?= t('signup.city_ph') ?>"></div>
                                        <p class="fl__err" id="mgCityErr" role="alert"></p>
                                    </div>

                                    <div class="fl fl--noicon">
                                        <label for="mgAddress"><?= t('signup.address') ?> <small><?= t('common.optional') ?></small></label>
                                        <div class="fl__ctl"><input type="text" id="mgAddress" name="manager_address" value="<?= htmlspecialchars($_POST['manager_address'] ?? '') ?>" placeholder="<?= t('signup.address_ph') ?>"></div>
                                    </div>
                                </div>

                                <div class="fl fl--noicon">
                                    <label for="mgDesc"><?= t('signup.description') ?> <small><?= t('common.optional') ?></small></label>
                                    <div class="fl__ctl"><textarea id="mgDesc" name="manager_description" placeholder="<?= t('signup.description_ph') ?>"><?= htmlspecialchars($_POST['manager_description'] ?? '') ?></textarea></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary ax-submit" data-loading-text="<?= t('signup.submitting') ?>"><span class="ax-spin" aria-hidden="true"></span><i class="fa-solid fa-user-plus"></i><span data-label><?= t('signup.submit') ?></span></button>
                </form>
            </div>

            <p class="ax-foot"><?= t('signup.foot') ?> <a href="login.php"><?= t('signup.login') ?></a></p>
        </section>
    </main>
</div>
<script src="js/signup-manager.js?v=5"></script>
</body>
</html>
