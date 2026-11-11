<?php
/**
 * SmartQ i18n + theme helper (website only).
 *
 *   t('key')                 -> HTML-escaped text in the current language (fallback: English, then the key)
 *   t('key', ['name' => $x]) -> replaces {name}; values are escaped too
 *   th('key', [...])         -> same, but the dictionary text itself may contain trusted HTML (<b>, <br>)
 *   t_msg($english)          -> translate a known English server message (config/*), else return it unchanged (raw)
 *   t_status($s), t_cat($c)  -> status pill / category labels (escaped)
 *   sq_date($fmt, $ts)       -> date() with Arabic day/month names + ص/م when Arabic
 *
 * Language: cookie "smartq_lang" (en|ar), default English. "?lang=ar|en" also sets it (no-JS fallback).
 * Theme:    cookie "smartq_theme" (light|dark); none = follow the system (resolved by an inline script before paint).
 * Dictionaries: lang/en.php, lang/ar.php (+ lang/{lang}.{section}.php files, merged).
 */

if (!defined('SMARTQ_I18N')) {
    define('SMARTQ_I18N', 1);

    function smartq_lang(): string
    {
        static $lang = null;
        if ($lang !== null) return $lang;
        $q = $_GET['lang'] ?? null;
        if (is_string($q) && in_array($q, ['en', 'ar'], true)) {
            if (!headers_sent()) {
                setcookie('smartq_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
            }
            $_COOKIE['smartq_lang'] = $q;
        }
        $c = $_COOKIE['smartq_lang'] ?? 'en';
        $lang = in_array($c, ['en', 'ar'], true) ? $c : 'en';
        return $lang;
    }

    function smartq_is_rtl(): bool { return smartq_lang() === 'ar'; }

    function smartq_theme(): string
    {
        $c = $_COOKIE['smartq_theme'] ?? '';
        return in_array($c, ['light', 'dark'], true) ? $c : '';
    }

    function smartq_dict(string $lang): array
    {
        static $cache = [];
        if (isset($cache[$lang])) return $cache[$lang];
        $dir = dirname(__DIR__) . '/lang';
        $dict = [];
        $main = $dir . '/' . $lang . '.php';
        if (is_file($main)) {
            $d = require $main;
            if (is_array($d)) $dict = $d;
        }
        foreach (glob($dir . '/' . $lang . '.*.php') ?: [] as $file) {
            $d = require $file;
            if (!is_array($d)) continue;
            $msgs = array_merge($dict['_msg'] ?? [], is_array($d['_msg'] ?? null) ? $d['_msg'] : []);
            $dict = array_merge($dict, $d);
            $dict['_msg'] = $msgs;
        }
        return $cache[$lang] = $dict;
    }

    function smartq_text(string $key): string
    {
        $lang = smartq_lang();
        $d = smartq_dict($lang);
        if (isset($d[$key]) && is_string($d[$key])) return $d[$key];
        if ($lang !== 'en') {
            $en = smartq_dict('en');
            if (isset($en[$key]) && is_string($en[$key])) return $en[$key];
        }
        return $key;
    }

    function smartq_fill(string $text, array $vars, bool $escapeTemplate): string
    {
        $out = $escapeTemplate ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) $map['{' . $k . '}'] = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            $out = strtr($out, $map);
        }
        return $out;
    }

    /** Escaped translation. */
    function t(string $key, array $vars = []): string { return smartq_fill(smartq_text($key), $vars, true); }

    /** Translation whose dictionary text may contain trusted HTML. Vars are escaped. */
    function th(string $key, array $vars = []): string { return smartq_fill(smartq_text($key), $vars, false); }

    /** Raw (unescaped) translation, for JS strings / attributes you escape yourself. */
    function t_raw(string $key, array $vars = []): string
    {
        $text = smartq_text($key);
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
            $text = strtr($text, $map);
        }
        return $text;
    }

    /** Translate a known English message produced by config/* (raw text, escape on output). */
    function t_msg($message): string
    {
        $message = (string)$message;
        if (smartq_lang() === 'en' || $message === '') return $message;
        $d = smartq_dict(smartq_lang());
        $msgs = $d['_msg'] ?? [];
        return is_array($msgs) && isset($msgs[$message]) ? $msgs[$message] : $message;
    }

    function t_status($status): string
    {
        $s = strtolower(trim((string)$status));
        $text = smartq_text('status.' . $s);
        if ($text === 'status.' . $s) $text = ucfirst($s);
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    function t_cat($category): string
    {
        $c = trim((string)$category);
        $text = smartq_text('cat.' . $c);
        if ($text === 'cat.' . $c) $text = $c;
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    function t_role($role): string
    {
        $r = strtolower(trim((string)$role));
        $text = smartq_text('role.' . $r);
        if ($text === 'role.' . $r) $text = ucfirst($r);
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /** date() with Arabic names when the UI is Arabic (digits stay Latin). Returns raw text. */
    function sq_date(string $format, $timestamp = null): string
    {
        if ($timestamp === null) $timestamp = time();
        elseif (!is_int($timestamp)) $timestamp = is_numeric($timestamp) ? (int)$timestamp : strtotime((string)$timestamp);
        if ($timestamp === false) return '';
        $out = date($format, $timestamp);
        if (smartq_lang() !== 'ar') return $out;
        static $map = [
            'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس',
            'Friday' => 'الجمعة', 'Saturday' => 'السبت', 'Sunday' => 'الأحد',
            'Mon' => 'الاثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت', 'Sun' => 'الأحد',
            'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس', 'April' => 'أبريل', 'June' => 'يونيو',
            'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر', 'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر',
            'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل', 'May' => 'مايو', 'Jun' => 'يونيو',
            'Jul' => 'يوليو', 'Aug' => 'أغسطس', 'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر',
            'AM' => 'ص', 'PM' => 'م', 'am' => 'ص', 'pm' => 'م',
        ];
        $out = strtr($out, $map);
        return str_replace(', ', '، ', $out);
    }

    /** Time like "10:30 AM" / "10:30 ص" (raw text). */
    function sq_time($time, string $format = 'g:i A'): string
    {
        $ts = is_int($time) ? $time : strtotime((string)$time);
        if ($ts === false) return (string)$time;
        $out = sq_date($format, $ts);
        // Arabic: isolate "10:30 ص" (U+2067 RLI ... U+2069 PDI) so ranges like "8:00 ص - 2:00 م" keep reading order
        return smartq_lang() === "ar" ? "\u{2067}" . $out . "\u{2069}" : $out;
    }

    /** Attributes for <html>: lang, dir and (when chosen) data-theme. */
    function smartq_html_attrs(): string
    {
        $lang = smartq_lang();
        $attrs = 'lang="' . $lang . '" dir="' . ($lang === 'ar' ? 'rtl' : 'ltr') . '"';
        $theme = smartq_theme();
        if ($theme !== '') $attrs .= ' data-theme="' . $theme . '"';
        return $attrs;
    }

    /** URL of the current page with ?lang=xx (no-JS fallback for the switch). */
    function smartq_lang_url(string $lang): string
    {
        $q = $_GET;
        $q['lang'] = $lang;
        return htmlspecialchars('?' . http_build_query($q), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Everything a page <head> needs for language + theme. Print it right before </head> (after the page CSS).
     * $base = path to the web root ('' or '../').
     */
    function smartq_head(string $base = ''): string
    {
        $lang = smartq_lang();
        $v = '5';
        $boot = "(function(d){try{var c=document.cookie,m=c.match(/(?:^|; )smartq_theme=(light|dark)/),t=null;"
            . "try{t=localStorage.getItem('smartq_theme')}catch(e){}"
            . "if(t!=='light'&&t!=='dark')t=m?m[1]:null;"
            . "if(t!=='light'&&t!=='dark'){t=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';d.setAttribute('data-theme-auto','')}"
            . "d.setAttribute('data-theme',t);d.classList.add('sq-js')}catch(e){}})(document.documentElement);";
        $out = '<script>' . $boot . '</script>' . "\n";
        $out .= '    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">' . "\n";
        $msgs = [];
        if ($lang !== 'en') {
            $d = smartq_dict($lang);
            $msgs = is_array($d['_msg'] ?? null) ? $d['_msg'] : [];
        }
        $out .= '    <script>window.SQ_LANG=' . json_encode($lang) . ';window.SQ_MSG=' . json_encode((object)$msgs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n";
        $out .= '    <script src="' . $base . 'js/i18n.js?v=' . $v . '"></script>' . "\n";
        $out .= '    <link rel="stylesheet" href="' . $base . 'css/smartq-theme.css?v=' . $v . '">' . "\n";
        // phone (<= 767px) app-like layout; no effect on larger screens
        $mcss = dirname(__DIR__) . '/css/smartq-mobile.css';
        $out .= '    <link rel="stylesheet" href="' . $base . 'css/smartq-mobile.css?v=' . (is_file($mcss) ? filemtime($mcss) : $v) . '">' . "\n";
        return $out;
    }

    /** The "EN | ع" language switch + sun/moon theme toggle. $extra = additional class. */
    function smartq_prefs(string $extra = ''): string
    {
        $lang = smartq_lang();
        $en = $lang === 'en' ? ' is-active' : '';
        $ar = $lang === 'ar' ? ' is-active' : '';
        return '<div class="sq-prefs ' . htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') . '">'
            . '<div class="sq-lang" role="group" aria-label="' . t('prefs.language') . '">'
            . '<a href="' . smartq_lang_url('en') . '" class="sq-lang__opt' . $en . '" data-sq-lang="en" lang="en" hreflang="en"' . ($en ? ' aria-current="true"' : '') . '>EN</a>'
            . '<span class="sq-lang__sep" aria-hidden="true"></span>'
            . '<a href="' . smartq_lang_url('ar') . '" class="sq-lang__opt' . $ar . '" data-sq-lang="ar" lang="ar" hreflang="ar"' . ($ar ? ' aria-current="true"' : '') . '>ع</a>'
            . '</div>'
            . '<button type="button" class="sq-theme-btn" data-sq-theme-toggle aria-label="' . t('prefs.theme') . '" title="' . t('prefs.theme') . '">'
            . '<i class="fa-solid fa-sun sq-theme-btn__sun" aria-hidden="true"></i><i class="fa-solid fa-moon sq-theme-btn__moon" aria-hidden="true"></i>'
            . '</button>'
            . '</div>';
    }

    /** Language + theme rows for the account dropdown. */
    function smartq_prefs_menu(): string
    {
        $other = smartq_lang() === 'ar' ? 'en' : 'ar';
        return '<a href="' . smartq_lang_url($other) . '" data-sq-lang="' . $other . '"><i class="fa-solid fa-language"></i> '
            . ($other === 'ar' ? '<span lang="ar">العربية</span>' : '<span lang="en">English</span>') . '</a>'
            . '<a href="#" data-sq-theme-toggle role="button"><i class="fa-solid fa-circle-half-stroke"></i> <span>' . t('prefs.theme_toggle') . '</span></a>';
    }
}


require_once __DIR__ . '/mobile_ui.php';
