<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function jsonResponse($status, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        "status" => (bool)$status,
        "message" => $message,
        "data" => $data === [] ? new stdClass() : $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read the Authorization header on every server type (Apache module, CGI/FastCGI, nginx). */
function getAuthorizationToken()
{
    $candidates = [];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) $candidates[] = $v;
        }
    }
    $candidates[] = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $candidates[] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    // Some shared hosts strip Authorization entirely; the app also sends this custom header.
    $candidates[] = isset($_SERVER['HTTP_X_API_TOKEN']) ? 'Bearer ' . $_SERVER['HTTP_X_API_TOKEN'] : '';
    foreach ($candidates as $c) {
        if (trim((string)$c) !== '') return trim((string)$c);
    }
    return '';
}

function getBearerToken()
{
    $authHeader = getAuthorizationToken();
    if ($authHeader !== '' && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }
    return '';
}

function buildFileUrl($path)
{
    $path = trim((string)$path);
    if ($path === '' || strtolower($path) === 'null') return "";
    if (preg_match('#^https?://#i', $path)) return $path;
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** Places store only a file name (uploaded by managers into uploads/places/). */
function placeImageUrl($stored)
{
    $stored = trim((string)$stored);
    if ($stored === '' || strtolower($stored) === 'null') return "";
    if (preg_match('#^https?://#i', $stored)) return $stored;
    if (strpos(ltrim($stored, '/'), 'uploads/') === 0) return buildFileUrl($stored);
    return buildFileUrl('uploads/places/' . basename($stored));
}

/** Profile images: new ones are "uploads/profile_images/x.jpg", old web ones are a bare file name. */
function profileImageUrl($stored)
{
    $stored = trim((string)$stored);
    if ($stored === '' || strtolower($stored) === 'null') return "";
    if (preg_match('#^https?://#i', $stored)) return $stored;
    if (strpos(ltrim($stored, '/'), 'uploads/') === 0) return buildFileUrl($stored);
    return buildFileUrl('uploads/profiles/' . basename($stored));
}

function userPayload(array $u)
{
    return [
        "id" => (int)$u["id"],
        "full_name" => $u["full_name"],
        "email" => $u["email"],
        "phone" => $u["phone"] ?? "",
        "birth_date" => (string)($u["birth_date"] ?? "") === "0000-00-00" ? "" : (string)($u["birth_date"] ?? ""),
        "profile_image" => $u["profile_image"] ?? "",
        "profile_image_url" => profileImageUrl($u["profile_image"] ?? "")
    ];
}

function placePayload(array $p)
{
    return [
        "id" => (int)$p["id"],
        "place_name" => $p["place_name"],
        "category" => $p["category"],
        "city" => $p["city"],
        "address" => $p["address"] ?? "",
        "description" => $p["description"] ?? "",
        "place_image" => $p["place_image"] ?? "",
        "place_image_url" => placeImageUrl($p["place_image"] ?? "")
    ];
}

function normalizeDateInput($date)
{
    $date = trim((string)$date);
    if ($date === '') return '';
    $normalized = str_replace('/', '-', $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $normalized);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return false;
    return $d->format('Y-m-d');
}

/** Issue a fresh login token valid for TOKEN_TTL_DAYS. */
function issueApiToken(mysqli $conn, int $userId)
{
    global $SMARTQ_CONFIG;
    $token = bin2hex(random_bytes(32));
    $days = (int)($SMARTQ_CONFIG['TOKEN_TTL_DAYS'] ?? 30);
    $expires = date('Y-m-d H:i:s', time() + $days * 86400);
    $stmt = $conn->prepare("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expires, $userId);
    $stmt->execute();
    return $token;
}
