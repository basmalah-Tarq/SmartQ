<?php
/**
 * SmartQ phone UI helpers (shared by user / manager / admin partials).
 * Everything rendered here is only visible at <= 767px (css/smartq-mobile.css),
 * so the desktop layout is unchanged.
 */
if (!defined('SMARTQ_MOBILE_UI')) {
    define('SMARTQ_MOBILE_UI', 1);

    /** Outline icons that mirror the Android app (Material-style, 24px grid, currentColor). */
    function sqm_icon(string $name, string $class = ''): string
    {
        $p = [
            'home' => '<path d="M4 10.4 12 4l8 6.4V19a1 1 0 0 1-1 1h-4.5v-5.5h-5V20H5a1 1 0 0 1-1-1z"/>',
            'store' => '<path d="M4.2 4.5h15.6l1.2 4.6a2.9 2.9 0 0 1-5.4 1.5 3 3 0 0 1-3.6 1.4 3 3 0 0 1-2-1.4 2.9 2.9 0 0 1-5.4-1.5z"/><path d="M5 12.2V20h14v-7.8"/><path d="M9.5 20v-4.5h5V20"/>',
            'ticket' => '<path d="M3.5 7.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v2.6a2 2 0 0 0 0 3.8v2.6a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-2.6a2 2 0 0 0 0-3.8z"/><path d="M12 8.4v1.2M12 11.4v1.2M12 14.4v1.2"/>',
            'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.8 19.6c.6-3.2 3.6-5.2 7.2-5.2s6.6 2 7.2 5.2z"/>',
            'login' => '<path d="M13.5 4.5H18a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-4.5"/><path d="M4.5 12h10M11 8.5l3.5 3.5-3.5 3.5"/>',
            'grid' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"/>',
            'calendar' => '<rect x="4" y="5.5" width="16" height="14.5" rx="2"/><path d="M4 10h16M8.5 3.5v3.5M15.5 3.5v3.5"/>',
            'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.8V12l2.8 1.8"/>',
            'list' => '<rect x="5" y="4.5" width="14" height="16" rx="2"/><path d="M9 4.5V3.5h6v1M8.5 10h7M8.5 13.5h7M8.5 17h4"/>',
            'users' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3.5 19c.5-3 2.8-4.8 5.5-4.8s5 1.8 5.5 4.8z"/><path d="M15.5 5.6a3.2 3.2 0 0 1 0 6M17 14.4c2 .5 3.3 2 3.6 4.6h-2.4"/>',
            'pin' => '<path d="M12 20.5s-6-5.4-6-10.3a6 6 0 0 1 12 0c0 4.9-6 10.3-6 10.3z"/><circle cx="12" cy="10.2" r="2.2"/>',
            'more' => '<circle cx="5.5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="18.5" cy="12" r="1.3"/>',
            'search' => '<circle cx="10.8" cy="10.8" r="6.2"/><path d="m15.5 15.5 4.5 4.5"/>',
            'history' => '<path d="M4.5 12a7.5 7.5 0 1 0 2.2-5.3L4.5 9"/><path d="M4.5 4.5V9H9M12 8v4.2l3 1.8"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'chev' => '<path d="m9.5 6 6 6-6 6"/>',
            'back' => '<path d="M19 12H5.5M11 6l-6 6 6 6"/>',
            'arrow' => '<path d="M5 12h13.5M13 6l6 6-6 6"/>',
            'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.3 3.2 5 3.2 8s-1 5.7-3.2 8c-2.2-2.3-3.2-5-3.2-8s1-5.7 3.2-8z"/>',
            'contrast' => '<circle cx="12" cy="12" r="8"/><path d="M12 4v16a8 8 0 0 0 0-16z" fill="currentColor" stroke="none"/>',
            'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
            'logout' => '<path d="M10.5 4.5H6A1.5 1.5 0 0 0 4.5 6v12A1.5 1.5 0 0 0 6 19.5h4.5"/><path d="M9.5 12h10M16 8.5l3.5 3.5-3.5 3.5"/>',
            'camera' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2.2l1.5-2h5.6l1.5 2h2.2A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5z"/><circle cx="12" cy="12.8" r="3.3"/>',
            'x' => '<path d="m6.5 6.5 11 11M17.5 6.5l-11 11"/>',
            'spark' => '<path d="M12 3.5 13.6 9l5.4 1.6-5.4 1.6L12 17.7l-1.6-5.5L5 10.6 10.4 9z"/><path d="M18.5 15.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
            'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4 7 8 6 8-6"/>',
        ];
        $body = $p[$name] ?? $p['more'];
        return '<svg class="sqm-ic' . ($class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }

    /**
     * Bottom tab bar. $items = [[href, icon, label, isActive, extraAttrs], ...]
     * A null href renders a <button> (e.g. "More" opens the drawer).
     */
    function sqm_tabbar(array $items, string $label = ''): string
    {
        $html = '<nav class="sqm-tabbar" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($items as $it) {
            [$href, $icon, $text, $active] = $it;
            $extra = $it[4] ?? '';
            $cls = 'sqm-tab' . ($active ? ' is-active' : '');
            $inner = '<span class="sqm-tab__pill">' . sqm_icon($icon) . '</span><span class="sqm-tab__label">' . $text . '</span>';
            if ($href === null) {
                $html .= '<button type="button" class="' . $cls . '" ' . $extra . '>' . $inner . '</button>';
            } else {
                $html .= '<a href="' . $href . '" class="' . $cls . '"' . ($active ? ' aria-current="page"' : '') . ($extra !== '' ? ' ' . $extra : '') . '>' . $inner . '</a>';
            }
        }
        return $html . '</nav>';
    }

    /** "Good morning," / "Good afternoon," / "Good evening," (server time). */
    function sqm_greeting(): string
    {
        $h = (int)date('G');
        return t($h < 12 ? 'm.greet_morning' : ($h < 18 ? 'm.greet_afternoon' : 'm.greet_evening'));
    }

    /** Queue code Q-007. */
    function sqm_qcode($n): string
    {
        return 'Q-' . str_pad((string)(int)$n, 3, '0', STR_PAD_LEFT);
    }

    /** "Today" / "Tomorrow" / "Fri, 13 Nov" for a Y-m-d date. */
    function sqm_day_label(string $ymd): string
    {
        $ts = strtotime($ymd);
        $today = strtotime(date('Y-m-d'));
        $diff = (int)round(($ts - $today) / 86400);
        if ($diff === 0) return t('m.today');
        if ($diff === 1) return t('m.tomorrow');
        return htmlspecialchars(sq_date('D, j M', $ts));
    }

    /** "in 25 min" / "in 1 hour" / "in 3 h" / "in 2 days" / "Now" until a Y-m-d H:i:s moment. */
    function sqm_starts_in(string $when): string
    {
        $diff = strtotime($when) - time();
        if ($diff <= 60) return t('m.now');
        $min = (int)ceil($diff / 60);
        if ($min < 60) return t('m.in_min', ['n' => $min]);
        $h = (int)round($min / 60);
        if ($h === 1) return t('m.in_hour');
        if ($h < 24) return t('m.in_hours', ['n' => $h]);
        $d = (int)ceil($diff / 86400);
        if ($d === 1) return t('m.tomorrow');
        return t('m.in_days', ['n' => $d]);
    }
}
