<?php

/**
 * Return the correct relative URL for a stored profile image.
 *
 * Some images are stored as:
 * uploads/profile_images/user_8_123.jpg
 *
 * Older images are stored as a filename only:
 * image.jpg
 *
 * This function supports both cases.
 */
function getProfileImageUrl($profileImage, $prefix = '../')
{
    if (empty($profileImage)) {
        return '';
    }

    $profileImage = ltrim(trim((string)$profileImage), '/');

    if ($profileImage === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $profileImage)) {
        return $profileImage;
    }

    if (
        strpos($profileImage, 'uploads/profile_images/') === 0 ||
        strpos($profileImage, 'uploads/profiles/') === 0
    ) {
        return $prefix . $profileImage;
    }

    return $prefix . 'uploads/profiles/' . $profileImage;
}
/** Same as getProfileImageUrl(); kept because older customer pages / the navbar call this name. */
if (!function_exists('getUserProfileImageUrl')) {
    function getUserProfileImageUrl($profileImage)
    {
        return getProfileImageUrl($profileImage, '../');
    }
}

/** First letter of a name for avatar placeholders (UTF-8 safe, already HTML-escaped). */
function smartq_initial($name, string $fallback = '?'): string
{
    $name = trim((string)$name);
    $letter = $name === '' ? $fallback : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    return htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');
}

/**
 * Optional date field from a form/API: '' -> null (never store '' / 0000-00-00),
 * 'YYYY-MM-DD' or 'YYYY/MM/DD' -> 'YYYY-MM-DD', anything else -> false.
 */
function smartq_optional_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') return null;
    $value = str_replace('/', '-', $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    if (!checkdate($m, $d, $y) || $y < 1900 || $value > date('Y-m-d')) return false;
    return $value;
}

/** CSV cell that spreadsheet apps will not execute as a formula (=, +, -, @ ...). */
function smartq_csv_cell($value): string
{
    $value = (string)$value;
    if (strlen($value) > 1 && strpbrk($value[0], "=+-@\t\r") !== false && !is_numeric($value)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Validate and store an uploaded image (jpg/png/webp, real image content, max 5 MB).
 * $file is one entry of $_FILES. Returns the stored path relative to the web root
 * (e.g. "uploads/places/x.jpg"), null when no file was sent, or throws RuntimeException
 * with a user-friendly message.
 */
function smartq_store_image_upload(?array $file, string $subDir, string $prefix): ?string
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('The image is too large (max 5 MB).');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The image could not be uploaded. Please try again.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('The image is too large (max 5 MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($types[$info[2]])) {
        throw new RuntimeException('Please choose a JPG, PNG or WEBP image.');
    }
    $subDir = trim($subDir, '/');
    $dirFs = dirname(__DIR__) . '/uploads/' . $subDir;
    if (!is_dir($dirFs) && !mkdir($dirFs, 0755, true)) {
        throw new RuntimeException('The image could not be saved.');
    }
    $name = preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dirFs . '/' . $name)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'uploads/' . $subDir . '/' . $name;
}

/** Delete a previously uploaded file, but only if it really is inside uploads/ (never anything else). */
function smartq_delete_upload(?string $stored, string $legacyDir = 'profiles'): void
{
    $stored = ltrim(trim((string)$stored), '/');
    if ($stored === '' || preg_match('#^https?://#i', $stored)) return;
    if (strpos($stored, 'uploads/') !== 0) {
        $stored = 'uploads/' . $legacyDir . '/' . basename($stored);
    }
    $root = realpath(dirname(__DIR__) . '/uploads');
    $path = realpath(dirname(__DIR__) . '/' . $stored);
    if ($root && $path && strpos($path, $root . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        @unlink($path);
    }
}
