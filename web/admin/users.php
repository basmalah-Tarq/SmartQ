<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/helpers.php';

$user = require_role('admin');

$roleFilter = trim($_GET['role'] ?? '');

$sql = "SELECT * FROM users WHERE role IN ('user','manager')";
$params = [];
$types = '';

if ($roleFilter !== '' && in_array($roleFilter, ['user', 'manager'], true)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

$activePage = 'users';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.users.title') ?></title>
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
        <h1><?= t('admin.users.heading') ?></h1>
        <p><?= t('admin.users.intro') ?></p>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">
            <select name="role">
                <option value=""><?= t('admin.users.all_roles') ?></option>
                <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>><?= t('admin.users.opt_users') ?></option>
                <option value="manager" <?= $roleFilter === 'manager' ? 'selected' : '' ?>><?= t('admin.users.opt_managers') ?></option>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i>
                <?= t('admin.filter') ?>
            </button>

            <a href="add-manager.php" class="btn btn-secondary">
                <i class="fa-solid fa-user-plus"></i>
                <?= t('admin.add_manager') ?>
            </a>

            <?php $exportQuery = $roleFilter !== '' ? '?role=' . urlencode($roleFilter) : ''; ?>
            <a href="export-users-csv.php<?= $exportQuery ?>" class="btn btn-secondary">
                <i class="fa-solid fa-file-csv"></i>
                <?= t('admin.export_excel') ?>
            </a>

            <a href="export-users-pdf.php<?= $exportQuery ?>" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-file-pdf"></i>
                <?= t('admin.export_pdf') ?>
            </a>
        </form>
    </div>

    <?php if ($users->num_rows > 0): ?>
        <div class="table-card">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= t('admin.th.user') ?></th>
                            <th><?= t('admin.th.email') ?></th>
                            <th><?= t('admin.th.phone') ?></th>
                            <th><?= t('admin.th.birth_date') ?></th>
                            <th><?= t('admin.th.role') ?></th>
                            <th><?= t('admin.th.joined') ?></th>
                            <th><?= t('admin.th.delete') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="admin-user-mini">
                                        <?php if (!empty($row['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars(getProfileImageUrl($row['profile_image'] ?? '')) ?>" alt="<?= t('admin.users.img_alt') ?>" class="admin-user-mini-img">
                                        <?php else: ?>
                                            <div class="admin-user-mini-avatar"><?= smartq_initial($row['full_name']) ?></div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($row['full_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="sq-ltr"><?= htmlspecialchars($row['email']) ?></span></td>
                                <td><span class="sq-ltr"><?= htmlspecialchars($row['phone'] ?: '-') ?></span></td>
                                <td><span class="sq-ltr"><?= htmlspecialchars($row['birth_date'] ?: '-') ?></span></td>
                                <td>
                                    <span class="status-badge <?= $row['role'] === 'manager' ? 'status-done' : 'status-confirmed' ?>">
                                        <?= in_array($row['role'], ['user', 'manager'], true) ? t('admin.role.' . $row['role']) : htmlspecialchars(ucfirst($row['role'])) ?>
                                    </span>
                                </td>
                                <td><span class="sq-ltr"><?= htmlspecialchars($row['created_at']) ?></span></td>
                                <td>
                                    <form method="POST" action="delete-user.php" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t_raw('admin.users.confirm_delete'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn danger-btn btn-sm">
                                            <i class="fa-solid fa-trash"></i>
                                            <?= t('admin.delete') ?>
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
            <h3><?= t('admin.users.empty_title') ?></h3>
            <p><?= t('admin.users.empty_text') ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
