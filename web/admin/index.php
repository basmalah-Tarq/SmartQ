<?php
require_once '../config/db.php';
require_once __DIR__ . '/../config/i18n.php';
require_once '../config/security.php';

$user = require_role('admin');

/* =========================
   COUNTS
========================= */
$usersCount = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'user'")->fetch_assoc()['c'];
$managersCount = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'manager'")->fetch_assoc()['c'];
$placesCount = (int)$conn->query("SELECT COUNT(*) AS c FROM places")->fetch_assoc()['c'];
$schedulesCount = (int)$conn->query("SELECT COUNT(*) AS c FROM schedules")->fetch_assoc()['c'];
$bookingsCount = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments")->fetch_assoc()['c'];

/* =========================
   RECENT BOOKINGS
========================= */
$recentBookings = $conn->query("
    SELECT 
        a.customer_name,
        a.customer_phone,
        a.queue_number,
        a.status,
        p.place_name,
        s.schedule_date,
        s.start_time
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    ORDER BY a.created_at DESC
    LIMIT 6
");

/* =========================
   SMART INSIGHTS
========================= */
$topPlace = 'No data';
$peakHourText = 'No data';
$busyDay = 'No data';
$lowActivityText = 'No enough data';

/* Most active place */
$topPlaceQuery = "
    SELECT p.place_name, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    GROUP BY p.place_name
    ORDER BY total DESC
    LIMIT 1
";
$topPlaceResult = $conn->query($topPlaceQuery);
if ($topPlaceResult && $topPlaceResult->num_rows > 0) {
    $topPlaceRow = $topPlaceResult->fetch_assoc();
    $topPlace = $topPlaceRow['place_name'];
}

/* Peak hour */
$peakHourQuery = "
    SELECT HOUR(COALESCE(a.appointment_time, s.start_time)) AS hour_slot, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    GROUP BY hour_slot
    ORDER BY total DESC
    LIMIT 1
";
$peakHourResult = $conn->query($peakHourQuery);
if ($peakHourResult && $peakHourResult->num_rows > 0) {
    $peakHourRow = $peakHourResult->fetch_assoc();
    $hour = (int)$peakHourRow['hour_slot'];
    $peakHourText = sprintf('%02d:00 - %02d:00', $hour, $hour + 1);
}

/* Busiest day */
$busyDayQuery = "
    SELECT DAYNAME(s.schedule_date) AS day_name, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    GROUP BY DAYNAME(s.schedule_date)
    ORDER BY total DESC
    LIMIT 1
";
$busyDayResult = $conn->query($busyDayQuery);
if ($busyDayResult && $busyDayResult->num_rows > 0) {
    $busyDayRow = $busyDayResult->fetch_assoc();
    $busyDay = $busyDayRow['day_name'];
}

/* Lowest activity hour */
$lowActivityQuery = "
    SELECT HOUR(COALESCE(a.appointment_time, s.start_time)) AS hour_slot, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    GROUP BY hour_slot
    ORDER BY total ASC
    LIMIT 1
";
$lowActivityResult = $conn->query($lowActivityQuery);
if ($lowActivityResult && $lowActivityResult->num_rows > 0) {
    $lowActivityRow = $lowActivityResult->fetch_assoc();
    $lowHour = (int)$lowActivityRow['hour_slot'];
    $lowActivityText = sprintf('%02d:00 - %02d:00', $lowHour, $lowHour + 1);
}

/* =========================
   CHART DATA
========================= */
$placesLabels = [];
$placesData = [];

$q1 = $conn->query("
    SELECT p.place_name, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    JOIN places p ON s.place_id = p.id
    GROUP BY p.place_name
    ORDER BY total DESC
");
if ($q1) {
    while ($row = $q1->fetch_assoc()) {
        $placesLabels[] = $row['place_name'];
        $placesData[] = (int)$row['total'];
    }
}

$daysLabels = [];
$daysData = [];

$q2 = $conn->query("
    SELECT DAYNAME(s.schedule_date) AS day_name, COUNT(*) AS total
    FROM appointments a
    JOIN schedules s ON a.schedule_id = s.id
    GROUP BY DAYNAME(s.schedule_date)
    ORDER BY total DESC
");
if ($q2) {
    while ($row = $q2->fetch_assoc()) {
        $daysLabels[] = $row['day_name'];
        $daysData[] = (int)$row['total'];
    }
}

$statusLabels = [];
$statusData = [];

$q3 = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM appointments
    GROUP BY status
");
if ($q3) {
    while ($row = $q3->fetch_assoc()) {
        $statusLabels[] = ucfirst($row['status']);
        $statusData[] = (int)$row['total'];
    }
}

$activePage = 'dashboard';

/* View-only labels (data above is untouched) */
$daysLabelsView = array_map(static function ($d) {
    $ts = strtotime((string)$d);
    return $ts ? sq_date('l', $ts) : (string)$d;
}, $daysLabels);
$statusNames = [];
foreach (['waiting', 'confirmed', 'done', 'cancelled', 'missed'] as $st) {
    $statusNames[$st] = t_raw('status.' . $st);
}
?>
<!DOCTYPE html>
<html <?= smartq_html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin.dash.title') ?></title>

    <link rel="icon" type="image/png" href="../img/logo-small.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/smartq-ui.css?v=5">
    <script src="../js/smartq-ui.js?v=5" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?= smartq_head('../') ?>
</head>
<body class="role-admin">

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container">
    <div class="admin-shell">

        <div class="admin-topbar workspace-hero">
            <div>
                <span class="dashboard-kicker"><?= t('admin.dash.kicker') ?></span>
                <h1><?= t('admin.dash.heading') ?></h1>
                <p><?= t('admin.dash.intro') ?></p>
            </div>
            <div class="workspace-hero-badge">
                <i class="fa-solid fa-shield-halved"></i>
                <div><strong><?= t('admin.dash.badge_title') ?></strong><span><?= t('admin.dash.badge_text') ?></span></div>
            </div>
        </div>

        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-head">
                    <span><?= t('admin.dash.users') ?></span>
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="admin-stat-number" data-count="<?= $usersCount ?>"><?= $usersCount ?></div>
                <div class="admin-stat-note"><?= t('admin.dash.users_note') ?></div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-head">
                    <span><?= t('admin.dash.managers') ?></span>
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div class="admin-stat-number" data-count="<?= $managersCount ?>"><?= $managersCount ?></div>
                <div class="admin-stat-note"><?= t('admin.dash.managers_note') ?></div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-head">
                    <span><?= t('admin.dash.places') ?></span>
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="admin-stat-number" data-count="<?= $placesCount ?>"><?= $placesCount ?></div>
                <div class="admin-stat-note"><?= t('admin.dash.places_note') ?></div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-head">
                    <span><?= t('admin.dash.schedules') ?></span>
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="admin-stat-number" data-count="<?= $schedulesCount ?>"><?= $schedulesCount ?></div>
                <div class="admin-stat-note"><?= t('admin.dash.schedules_note') ?></div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-head">
                    <span><?= t('admin.dash.bookings') ?></span>
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="admin-stat-number" data-count="<?= $bookingsCount ?>"><?= $bookingsCount ?></div>
                <div class="admin-stat-note"><?= t('admin.dash.bookings_note') ?></div>
            </div>
        </div>

        <div class="admin-dashboard-row">
            <div class="admin-insight-box">
                <h3><i class="fa-solid fa-brain"></i> <?= t('admin.dash.insights') ?></h3>
                <ul class="admin-insight-list">
                    <li>
                        <i class="fa-solid fa-chart-column"></i>
                        <?= t('admin.dash.top_place') ?> <?= $topPlace === 'No data' ? t('admin.dash.no_data') : htmlspecialchars($topPlace) ?>
                    </li>
                    <li>
                        <i class="fa-solid fa-clock"></i>
                        <?= t('admin.dash.peak') ?> <?= $peakHourText === 'No data' ? t('admin.dash.no_data') : '<span class="sq-ltr">' . htmlspecialchars($peakHourText) . '</span>' ?>
                    </li>
                    <li>
                        <i class="fa-solid fa-calendar"></i>
                        <?= t('admin.dash.busy_day') ?> <?= $busyDay === 'No data' ? t('admin.dash.no_data') : htmlspecialchars(strtotime((string)$busyDay) ? sq_date('l', strtotime((string)$busyDay)) : (string)$busyDay) ?>
                    </li>
                    <li>
                        <i class="fa-solid fa-wave-square"></i>
                        <?= t('admin.dash.low') ?> <?= $lowActivityText === 'No enough data' ? t('admin.dash.not_enough') : '<span class="sq-ltr">' . htmlspecialchars($lowActivityText) . '</span>' ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="admin-charts-grid">
            <div class="chart-card">
                <h3><?= t('admin.dash.chart_places') ?></h3>
                <div class="chart-wrap">
                    <canvas id="placesChart" role="img" aria-label="<?= t('admin.dash.chart_places') ?>"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><?= t('admin.dash.chart_days') ?></h3>
                <div class="chart-wrap">
                    <canvas id="daysChart" role="img" aria-label="<?= t('admin.dash.chart_days') ?>"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><?= t('admin.dash.chart_status') ?></h3>
                <div class="chart-wrap">
                    <canvas id="statusChart" role="img" aria-label="<?= t('admin.dash.chart_status') ?>"></canvas>
                </div>
            </div>
        </div>

        <div class="admin-actions">
            <a href="users.php" class="btn btn-primary">
                <i class="fa-solid fa-users"></i>
                <?= t('admin.dash.manage_users') ?>
            </a>

            <a href="places.php" class="btn btn-secondary">
                <i class="fa-solid fa-building"></i>
                <?= t('admin.dash.manage_places') ?>
            </a>

            <a href="schedules.php" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-days"></i>
                <?= t('admin.dash.manage_schedules') ?>
            </a>

            <a href="bookings.php" class="btn btn-secondary">
                <i class="fa-solid fa-list-check"></i>
                <?= t('admin.dash.manage_bookings') ?>
            </a>
        </div>

        <div class="table-card">
            <h3><?= t('admin.dash.recent') ?></h3>
            <div class="admin-note"><?= t('admin.dash.recent_note') ?></div>

            <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= t('admin.th.customer') ?></th>
                                <th><?= t('admin.th.phone') ?></th>
                                <th><?= t('admin.th.place') ?></th>
                                <th><?= t('admin.th.date') ?></th>
                                <th><?= t('admin.th.time') ?></th>
                                <th><?= t('admin.th.queue') ?></th>
                                <th><?= t('admin.th.status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($booking['customer_name']) ?></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($booking['customer_phone']) ?></span></td>
                                    <td><?= htmlspecialchars($booking['place_name']) ?></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars($booking['schedule_date']) ?></span></td>
                                    <td><span class="sq-ltr"><?= htmlspecialchars(substr($booking['start_time'], 0, 5)) ?></span></td>
                                    <td><span class="sq-ltr">#<?= (int)$booking['queue_number'] ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($booking['status']) ?>">
                                            <?= t_status($booking['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    <h3><?= t('admin.dash.empty_title') ?></h3>
                    <p><?= t('admin.dash.empty_text') ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
(function () {
const placesLabels = <?= json_encode($placesLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const placesData = <?= json_encode($placesData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const daysLabels = <?= json_encode($daysLabelsView, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const daysData = <?= json_encode($daysData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const statusLabels = <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const statusData = <?= json_encode($statusData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const statusNames = <?= json_encode($statusNames, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const datasetLabel = <?= json_encode(t_raw('admin.dash.dataset'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const isRtl = document.documentElement.getAttribute('dir') === 'rtl';

if (!window.Chart) return;

const sqStatusColors = { waiting: "#F59E0B", confirmed: "#1F6FE0", done: "#16A36B", cancelled: "#E5484D", missed: "#94A3B8" };
const statusKeys = statusLabels.map(l => String(l).toLowerCase());
const statusLabelsView = statusKeys.map((k, i) => statusNames[k] || statusLabels[i]);

function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

function palette() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
        muted: cssVar('--muted', '#5B6B85'),
        border: cssVar('--border', '#E2E9F4'),
        surface: cssVar('--surface', '#FFFFFF'),
        text: cssVar('--text', '#0B1B36'),
        navy: dark ? '#8EC5FF' : '#12306B'
    };
}

function applyDefaults(p) {
    Chart.defaults.font.family = isRtl ? "'IBM Plex Sans Arabic', Inter, system-ui, sans-serif" : "Inter, system-ui, sans-serif";
    Chart.defaults.color = p.muted;
    Chart.defaults.borderColor = p.border;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.color = p.muted;
    Chart.defaults.plugins.legend.rtl = isRtl;
    Chart.defaults.plugins.tooltip.rtl = isRtl;
    Chart.defaults.plugins.tooltip.textDirection = isRtl ? 'rtl' : 'ltr';
}

function axes(p) {
    return {
        x: { ticks: { color: p.muted }, grid: { color: p.border }, border: { color: p.border } },
        y: { ticks: { color: p.muted, precision: 0 }, grid: { color: p.border }, border: { color: p.border }, beginAtZero: true }
    };
}

const charts = [];
let p = palette();
applyDefaults(p);

if (document.getElementById('placesChart')) {
    charts.push({ kind: 'bar', chart: new Chart(document.getElementById('placesChart'), {
        type: 'bar',
        data: {
            labels: placesLabels,
            datasets: [{
                label: datasetLabel,
                data: placesData,
                borderWidth: 1,
                backgroundColor: "rgba(31,111,224,.85)",
                hoverBackgroundColor: p.navy,
                borderColor: "#1F6FE0",
                maxBarThickness: 46,
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: axes(p)
        }
    }) });
}

if (document.getElementById('daysChart')) {
    charts.push({ kind: 'line', chart: new Chart(document.getElementById('daysChart'), {
        type: 'line',
        data: {
            labels: daysLabels,
            datasets: [{
                label: datasetLabel,
                data: daysData,
                fill: true,
                backgroundColor: "rgba(20,163,154,.12)",
                borderColor: "#14A39A",
                pointBackgroundColor: "#F5B82E",
                pointBorderColor: p.surface,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: axes(p)
        }
    }) });
}

if (document.getElementById('statusChart')) {
    charts.push({ kind: 'doughnut', chart: new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabelsView,
            datasets: [{
                data: statusData,
                backgroundColor: statusKeys.map(k => sqStatusColors[k] || "#8EC5FF"),
                borderColor: p.surface,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    }) });
}

document.addEventListener('sq:theme', function () {
    /* wait one frame so the new [data-theme] tokens are applied */
    requestAnimationFrame(function () {
        p = palette();
        applyDefaults(p);
        charts.forEach(function (item) {
            const c = item.chart;
            const ds = c.data.datasets[0];
            if (item.kind === 'doughnut') {
                ds.borderColor = p.surface;
            } else {
                c.options.scales = axes(p);
                if (item.kind === 'bar') ds.hoverBackgroundColor = p.navy;
                if (item.kind === 'line') ds.pointBorderColor = p.surface;
            }
            if (c.options.plugins && c.options.plugins.legend && c.options.plugins.legend.labels) {
                c.options.plugins.legend.labels.color = p.muted;
            }
            c.update();
        });
    });
});
})();
</script>

</body>
</html>
