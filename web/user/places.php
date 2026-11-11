<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';
require_once '../config/categories.php';

$user = current_user_or_guest();   // guests may browse places
$isGuest = $user === null;

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM places WHERE active = 1";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (place_name LIKE ? OR city LIKE ? OR category LIKE ?)";
    $searchLike = "%$search%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Filters use the shared category list (config/categories.php); chips = categories that have places.
$allCategories = smartq_categories($conn);
$chipCategories = smartq_categories($conn, true);

$activePage = 'places';
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.places.title') ?></title>
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

<div class="container sqm-places">
    <div class="sqm-pagehead">
        <h1><?= t('m.find_place') ?></h1>
        <p><?= t('m.find_place_sub') ?></p>
    </div>
    <div class="page-header page-header-actions">
        <div>
            <span class="dashboard-kicker"><i class="fa-solid fa-location-dot"></i> <?= t('user.places.kicker') ?></span>
            <h1><?= t('user.places.heading') ?></h1>
            <p><?= t('user.places.intro') ?></p>
        </div>
    </div>

    <div class="form-card">
        <form method="GET" class="filter-form filter-form-inline">
            <input type="text" name="search" placeholder="<?= t('user.places.search_ph') ?>" value="<?= htmlspecialchars($search) ?>" aria-label="<?= t('user.places.search_aria') ?>">

            <select name="category" id="categorySelect" aria-label="<?= t('user.places.category') ?>">
                <option value=""><?= t('user.places.all_categories') ?></option>
                <?php foreach ($allCategories as $catOption): ?>
                    <option value="<?= htmlspecialchars($catOption, ENT_QUOTES, 'UTF-8') ?>"<?= $category === $catOption ? ' selected' : '' ?>><?= t_cat($catOption) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i>
                <?= t('user.places.search') ?>
            </button>
        </form>
    </div>

    <div class="sq-filter-chips" data-sq-chips="categorySelect" aria-label="<?= t('user.places.chips') ?>">
        <?php
        $chipList = ['' => [t('user.places.all'), 'fa-solid fa-border-all']];
        foreach ($chipCategories as $chipCat) { $chipList[$chipCat] = [t_cat($chipCat), smartq_category_icon($chipCat)]; }
        foreach ($chipList as $chipValue => $chip):
        ?>
            <button type="button" class="sq-chip sq-chip--filter <?= $category === $chipValue ? 'is-active' : '' ?>" data-value="<?= htmlspecialchars($chipValue) ?>"><i class="<?= $chip[1] ?>"></i> <?= $chip[0] ?></button>
        <?php endforeach; ?>
    </div>

    <p class="sqm-count"><?= t('m.places_count', ['n' => $result->num_rows]) ?></p>

    <div class="places-grid compact-places-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($place = $result->fetch_assoc()): ?>
                <article class="place-card compact-place-card">
                    <div class="sq-place-media">
                        <?php if (!empty($place['place_image'])): ?>
                            <img src="../uploads/places/<?= htmlspecialchars($place['place_image']) ?>" alt="<?= htmlspecialchars($place['place_name']) ?>" class="place-card-image compact-image" loading="lazy">
                        <?php else: ?>
                            <img src="../img/place-placeholder.svg" alt="" class="place-card-image compact-image" loading="lazy">
                        <?php endif; ?>
                        <span class="place-badge"><i class="<?= smartq_category_icon((string)$place['category']) ?> sqm-m-inline"></i> <?= t_cat($place['category']) ?></span>
                    </div>

                    <div class="place-card-body compact-place-body">
                        <h3><?= htmlspecialchars($place['place_name']) ?></h3>

                        <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($place['city']) ?><?php if (!empty($place['address'])): ?> · <?= htmlspecialchars($place['address']) ?><?php endif; ?></p>

                        <?php if (!empty($place['description'])): ?>
                            <small><?= htmlspecialchars($place['description']) ?></small>
                        <?php endif; ?>

                        <div class="place-actions">
                            <a href="schedule.php?place_id=<?= (int)$place['id'] ?>" class="btn btn-primary">
                                <span class="sqm-d"><?= t('user.choose_time') ?> <i class="fa-solid fa-arrow-right"></i></span><span class="sqm-m"><?= t('m.book') ?></span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-box">
                <h3><?= t('user.places.empty') ?></h3>
                <p><?= t('user.places.empty_note') ?></p>
                <a href="places.php" class="btn btn-secondary"><?= t('user.places.clear') ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
