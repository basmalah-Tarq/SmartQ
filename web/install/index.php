<?php
/**
 * SmartQ one-time installer for shared hosting (InfinityFree).
 * - Protected by a one-time token created by deploy/build_package.sh (config/install_token.php).
 * - The owner types the database details; the installer imports the database and writes config/config.local.php.
 * - After a successful install the token file is deleted, so this page stops working.
 */
$tokenFile = __DIR__ . '/../config/install_token.php';
$sqlFile = __DIR__ . '/smartq.sql';
$cfgFile = __DIR__ . '/../config/config.local.php';

if (!is_file($tokenFile)) {
    http_response_code(404);
    exit('Not found');
}
$expected = (string)(require $tokenFile);
$given = (string)($_GET['t'] ?? $_POST['t'] ?? '');
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(404);
    exit('Not found');
}

$msg = '';
$ok = null;
$v = ['host' => '', 'name' => '', 'user' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['host'] = trim((string)($_POST['host'] ?? ''));
    $v['name'] = trim((string)($_POST['name'] ?? ''));
    $v['user'] = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($v['host'], $v['user'], $pass, $v['name']);
    if ($db->connect_error) {
        $ok = false;
        $msg = 'ما قدرت أتصل بقاعدة البيانات. تأكدي من الأربع خانات (خصوصًا كلمة السر واسم القاعدة) وجربي مرة ثانية.';
    } else {
        $db->set_charset('utf8mb4');
        $hasTables = $db->query("SHOW TABLES LIKE 'users'");
        $imported = false;
        if ($hasTables && $hasTables->num_rows === 0) {
            $sql = (string)@file_get_contents($sqlFile);
            if ($sql === '' || !$db->multi_query($sql)) {
                $ok = false;
                $msg = 'اتصلت بالقاعدة بس ما قدرت أستورد البيانات: ' . htmlspecialchars($db->error);
            } else {
                do {
                    if ($r = $db->store_result()) $r->free();
                } while ($db->more_results() && $db->next_result());
                if ($db->errno) {
                    $ok = false;
                    $msg = 'صار خطأ أثناء الاستيراد: ' . htmlspecialchars($db->error);
                } else {
                    $imported = true;
                }
            }
        }
        if ($ok !== false) {
            $cfg = is_file($cfgFile) ? (require $cfgFile) : [];
            if (!is_array($cfg)) $cfg = [];
            $cfg['DB_HOST'] = $v['host'];
            $cfg['DB_NAME'] = $v['name'];
            $cfg['DB_USER'] = $v['user'];
            $cfg['DB_PASS'] = $pass;
            $php = "<?php\n// Server settings (written by install/index.php)\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents($cfgFile, $php, LOCK_EX) === false) {
                $ok = false;
                $msg = 'ما قدرت أحفظ ملف الإعدادات (config/config.local.php).';
            } else {
                @unlink($tokenFile);   // one-time installer
                @unlink($sqlFile);
                $ok = true;
                $msg = ($imported ? 'تم استيراد البيانات و' : 'البيانات كانت موجودة أصلًا، و') . 'حفظت الإعدادات ✅ الموقع جاهز!';
            }
        }
    }
}
$home = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/\\') . '/';
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>تثبيت SmartQ</title>
<style>
body{margin:0;font-family:Tahoma,Segoe UI,sans-serif;background:#F5F8FD;color:#0B1B36;display:grid;place-items:center;min-height:100vh}
.card{background:#fff;border:1px solid #E2E9F4;border-radius:20px;padding:32px;max-width:480px;width:90%;box-shadow:0 10px 30px -12px rgba(11,31,69,.18)}
h1{margin:0 0 6px;font-size:22px;color:#0B1F45}p{color:#5B6B85;line-height:1.7;margin:6px 0}
label{display:block;font-weight:700;margin:14px 0 6px}small{color:#5B6B85;font-weight:400}
input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #CBD6E6;border-radius:12px;font-size:15px;direction:ltr}
button,.go{display:block;text-align:center;text-decoration:none;margin-top:20px;width:100%;box-sizing:border-box;padding:13px;border:0;border-radius:999px;background:#1F6FE0;color:#fff;font-size:16px;font-weight:700;cursor:pointer}
.ok{background:#E8F7F0;color:#0E7A4E;padding:12px;border-radius:12px;margin-top:16px}.err{background:#FDECEC;color:#B42318;padding:12px;border-radius:12px;margin-top:16px}
</style></head><body>
<?php if ($ok === true): ?>
<div class="card"><h1>تم التثبيت 🎉</h1><div class="ok"><?= htmlspecialchars($msg) ?></div>
<a class="go" href="<?= htmlspecialchars($home) ?>">افتحي الموقع</a></div>
<?php else: ?>
<form class="card" method="post" autocomplete="off">
  <h1>تثبيت SmartQ</h1>
  <p>انسخي القيم من لوحة «إنفنتي فري» ← <b>MySQL Databases</b> وحطيها هون.</p>
  <input type="hidden" name="t" value="<?= htmlspecialchars($given) ?>">
  <label>MySQL Host Name <small>(مثل sql123.infinityfree.com)</small></label>
  <input name="host" required value="<?= htmlspecialchars($v['host']) ?>">
  <label>MySQL DB Name <small>(مثل if0_12345678_smartq)</small></label>
  <input name="name" required value="<?= htmlspecialchars($v['name']) ?>">
  <label>MySQL User Name <small>(مثل if0_12345678)</small></label>
  <input name="user" required value="<?= htmlspecialchars($v['user']) ?>">
  <label>MySQL Password <small>(كلمة سر الـ vPanel)</small></label>
  <input name="pass" type="password" required>
  <button type="submit">ثبّت الموقع</button>
  <?php if ($ok === false): ?><div class="err"><?= $msg ?></div><?php endif; ?>
</form>
<?php endif; ?>
</body></html>
