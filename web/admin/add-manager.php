<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';
require_once '../config/places.php';

$user = require_role('admin');

$message = '';
$messageType = 'success';
$categories = smartq_categories($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $birthDate = smartq_optional_date($_POST['birth_date'] ?? '');
    [$placeData, $placeError] = smartq_place_input($conn, $_POST);
    $messageType = 'error';

    if ($fullName === '' || $email === '' || $password === '') {
        $message = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } elseif ($birthDate === false) {
        $message = "Please enter a valid birth date.";
    } elseif ($placeError !== '') {
        $message = $placeError;
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->fetch_assoc()) {
            $message = "Email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $phoneValue = $phone !== '' ? $phone : null;
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, birth_date, role) VALUES (?, ?, ?, ?, ?, 'manager')");
                $stmt->bind_param("sssss", $fullName, $email, $hash, $phoneValue, $birthDate);
                if (!$stmt->execute()) throw new RuntimeException('insert user');
                smartq_insert_place($conn, (int)$conn->insert_id, $placeData);
                $conn->commit();
                $message = "Manager and assigned place added successfully.";
                $messageType = 'success';
                $_POST = [];
            } catch (Throwable $e) {
                $conn->rollback();
                $message = "Failed to add manager.";
            }
        }
    }
}

$activePage = 'add_manager';
$selectedCategory = (string)($_POST['category'] ?? '');
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.addm.title') ?></title>
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
        <h1><?= t('admin.addm.heading') ?></h1>
        <p><?= t('admin.addm.intro') ?></p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'error' ? 'error-alert' : 'success-alert' ?>">
            <?= htmlspecialchars(t_msg($message)) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" class="admin-form">
            <?= csrf_input() ?>
            <h3 class="form-section-title"><?= t('admin.addm.section') ?></h3>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('admin.form.full_name') ?></label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label><?= t('admin.form.email') ?></label>
                    <input type="email" name="email" dir="ltr" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= t('admin.form.password') ?></label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label><?= t('admin.form.phone') ?></label>
                    <input type="text" name="phone" dir="ltr">
                </div>
            </div>

            <div class="form-group">
                <label><?= t('admin.form.birth_date') ?></label>
                <input type="date" name="birth_date">
            </div>

            <div class="manager-assignment-box">
                <div class="manager-assignment-head">
                    <div>
                        <h3><?= t('admin.addm.assigned') ?></h3>
                        <p><?= t('admin.addm.assigned_text') ?></p>
                    </div>
                    <i class="fa-solid fa-building-user"></i>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= t('admin.addm.place_name') ?></label>
                        <input type="text" name="place_name" placeholder="<?= t('admin.addm.place_name_ph') ?>" required>
                    </div>

                    <div class="form-group">
                        <label><?= t('admin.addm.place_type') ?></label>
                        <select name="category" required>
                            <option value=""><?= t('admin.addm.select_cat') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"<?= $selectedCategory === $cat ? ' selected' : '' ?>><?= t_cat($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= t('admin.addm.city') ?></label>
                        <input type="text" name="city" required>
                    </div>

                    <div class="form-group">
                        <label><?= t('admin.addm.address') ?></label>
                        <input type="text" name="address">
                    </div>
                </div>

                <div class="form-group">
                    <label><?= t('admin.addm.description') ?></label>
                    <textarea name="description" placeholder="<?= t('admin.addm.description_ph') ?>"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= t('admin.addm.submit') ?>
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
