<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';

$user = require_role('manager');

$managerId = (int)$user['id'];

$stmt = $conn->prepare("
    SELECT DISTINCT
        a.customer_name,
        a.customer_phone,
        a.customer_email,
        u.profile_image
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE p.manager_id = ?
    ORDER BY a.customer_name ASC
");
$stmt->bind_param("i", $managerId);
$stmt->execute();
$customers = $stmt->get_result();

$activePage = 'customers';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('manager.customers.title') ?></title>
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
    <div class="page-header page-header-actions">
        <div>
            <h1><?= t('manager.customers.heading') ?></h1>
            <p><?= t('manager.customers.intro') ?></p>
        </div>

        <div class="export-actions">
            <a href="export-customers-csv.php" class="btn btn-secondary">
                <i class="fa-solid fa-file-csv"></i>
                <?= t('manager.export_excel') ?>
            </a>
            <a href="export-customers-pdf.php" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-file-pdf"></i>
                <?= t('manager.export_pdf') ?>
            </a>
        </div>
    </div>

    <?php if ($customers->num_rows > 0): ?>
        <div class="table-card">
            <div class="table-wrap">
                <table class="manager-table">
                    <thead>
                        <tr>
                            <th><?= t('manager.th.customer') ?></th>
                            <th><?= t('manager.th.phone_number') ?></th>
                            <th><?= t('manager.th.email_address') ?></th>
                            <th><?= t('manager.th.message') ?></th>
                            <th><?= t('manager.th.delete') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $customers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="customer-mini">
                                        <?php if (!empty($row['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars(getProfileImageUrl($row['profile_image'] ?? '')) ?>" alt="<?= t('manager.customer_alt') ?>" class="customer-mini-img">
                                        <?php else: ?>
                                            <div class="customer-mini-avatar"><?= smartq_initial($row['customer_name']) ?></div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($row['customer_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="sq-ltr"><?= htmlspecialchars($row['customer_phone']) ?></span></td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($row['customer_email']) ?>" class="table-mail-link" dir="ltr">
                                        <?= htmlspecialchars($row['customer_email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($row['customer_email']) ?>" class="btn btn-secondary btn-sm">
                                        <i class="fa-solid fa-envelope"></i>
                                        <?= t('manager.customers.email_btn') ?>
                                    </a>
                                </td>
                                <td>
                                    <form method="POST" action="delete-customer.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('manager.customers.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($row['customer_email']) ?>">
                                        <button type="submit" class="btn danger-btn btn-sm">
                                            <i class="fa-solid fa-trash"></i>
                                            <?= t('manager.delete') ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3><?= t('manager.customers.empty_title') ?></h3>
            <p><?= t('manager.customers.empty_text') ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>