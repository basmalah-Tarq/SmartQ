<?php
/**
 * SmartQ place categories: ONE source for every category list on the website
 * (customer filters, manager "My place", manager signup, admin "Add manager").
 * The mobile API (api/user/places.php) returns the categories that have active places.
 */

const SMARTQ_CANONICAL_CATEGORIES = [
    'Hospital',
    'Clinic',
    'Dental Clinic',
    'Bank',
    'Beauty Center',
    'Barbershop',
    'Gym',
    'Government Office',
    'Pharmacy',
    'Laboratory',
];

/**
 * Canonical list + any other category already stored in the database (so older or custom
 * values never disappear from a dropdown). Canonical order first, extras alphabetically.
 */
function smartq_categories(?mysqli $conn = null, bool $onlyWithActivePlaces = false): array
{
    $list = $onlyWithActivePlaces ? [] : SMARTQ_CANONICAL_CATEGORIES;
    $extra = [];
    if ($conn) {
        $sql = "SELECT DISTINCT category FROM places WHERE category <> ''" . ($onlyWithActivePlaces ? " AND active = 1" : "");
        $res = $conn->query($sql);
        while ($res && ($row = $res->fetch_assoc())) {
            $cat = trim((string)$row['category']);
            if ($cat === '') continue;
            if (in_array($cat, SMARTQ_CANONICAL_CATEGORIES, true)) {
                if ($onlyWithActivePlaces) $list[] = $cat;
            } elseif (!in_array($cat, $extra, true)) {
                $extra[] = $cat;
            }
        }
    }
    if ($onlyWithActivePlaces) {
        // keep canonical order for the ones that exist
        $list = array_values(array_intersect(SMARTQ_CANONICAL_CATEGORIES, $list));
    }
    sort($extra, SORT_NATURAL | SORT_FLAG_CASE);
    return array_merge($list, $extra);
}

/** Font Awesome icon for a category chip. */
function smartq_category_icon(string $category): string
{
    $icons = [
        'Hospital' => 'fa-solid fa-hospital',
        'Clinic' => 'fa-solid fa-stethoscope',
        'Dental Clinic' => 'fa-solid fa-tooth',
        'Bank' => 'fa-solid fa-building-columns',
        'Beauty Center' => 'fa-solid fa-spa',
        'Barbershop' => 'fa-solid fa-scissors',
        'Gym' => 'fa-solid fa-dumbbell',
        'Government Office' => 'fa-solid fa-landmark',
        'Pharmacy' => 'fa-solid fa-prescription-bottle-medical',
        'Laboratory' => 'fa-solid fa-flask',
    ];
    return $icons[$category] ?? 'fa-solid fa-location-dot';
}

/** Render <option> tags for a category <select>. */
function smartq_category_options(array $categories, string $selected = ''): string
{
    $html = '';
    foreach ($categories as $cat) {
        $html .= '<option value="' . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '"'
            . ($selected === $cat ? ' selected' : '') . '>'
            . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

/** Trimmed category from a form; must be non-empty, max 80 chars (column size). */
function smartq_clean_category(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return mb_substr($value, 0, 80);
}
