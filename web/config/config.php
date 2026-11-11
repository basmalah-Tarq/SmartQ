<?php
/**
 * SmartQ central configuration.
 * Values come from (highest priority first):
 *   1. config/config.local.php  (per-environment file, NOT committed / NOT shared)
 *   2. environment variables     (Docker)
 *   3. the defaults below        (local XAMPP)
 */
$SMARTQ_CONFIG = [
    'DB_HOST'  => getenv('DB_HOST') ?: 'localhost',
    'DB_USER'  => getenv('DB_USER') ?: 'root',
    'DB_PASS'  => getenv('DB_PASS') ?: '',
    'DB_NAME'  => getenv('DB_NAME') ?: 'smartq_db',
    'DB_PORT'  => (int)(getenv('DB_PORT') ?: 3306),
    // App timezone: PHP and MySQL must agree, otherwise "schedule ended" checks break.
    'TIMEZONE' => getenv('APP_TIMEZONE') ?: 'Asia/Gaza',
    // Mobile API tokens expire after this many days.
    'TOKEN_TTL_DAYS' => 30,

    // ---- Email (one-time login codes, booking confirmations, reminders) ----
    // MAIL_DRIVER: 'brevo' (real emails via https://www.brevo.com, free 300/day),
    //              'log'   (local testing: emails are written to config/mail.log),
    //              'none'  (disabled).
    'MAIL_DRIVER'    => 'none',
    'BREVO_API_KEY'  => '',
    'MAIL_FROM'      => 'no-reply@example.com',   // must be a sender verified in Brevo
    'MAIL_FROM_NAME' => 'SmartQ',
    'APP_NAME'       => 'SmartQ',
    'REMINDER_MINUTES_BEFORE' => 60,

    // ---- Google sign-in ----
    // OAuth client IDs from Google Cloud Console (Web client ID; the Android app also uses the Web client ID).
    'GOOGLE_CLIENT_IDS' => [],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $SMARTQ_CONFIG = array_merge($SMARTQ_CONFIG, $local);
    }
}

date_default_timezone_set($SMARTQ_CONFIG['TIMEZONE']);
