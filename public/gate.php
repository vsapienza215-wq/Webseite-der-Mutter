<?php
/**
 * SAPiENZA – Passwortschutz (vor dem Livegang)
 *
 * Alle Aufrufe laufen per .htaccess durch diese Datei. Ohne gültige Anmeldung
 * erscheint nur eine weiße Login-Seite – von der Website ist nichts zu sehen.
 * Nach der Anmeldung liefert diese Datei die angefragten Seiten/Dateien aus.
 *
 * Zugangsdaten: gate-config.php (liegt nur auf dem Server).
 * Zum Livegang: den Block „Passwortschutz“ in der .htaccess entfernen.
 */

declare(strict_types=1);

$cfgFile = __DIR__ . '/gate-config.php';
if (!is_file($cfgFile)) {
    http_response_code(503);
    exit('Passwortschutz: gate-config.php fehlt.');
}
$cfg = (array) require $cfgFile;
$secret = (string) ($cfg['secret'] ?? '');
if (strlen($secret) < 32) {
    http_response_code(503);
    exit('Passwortschutz: Geheimschlüssel in gate-config.php fehlt.');
}

const GATE_COOKIE = 'sapienza_zugang';
const GATE_DAYS   = 30;

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

function gate_sign(string $secret, int $exp): string
{
    return $exp . '.' . hash_hmac('sha256', 'zugang|' . $exp, $secret);
}

function gate_valid(string $secret): bool
{
    $c = $_COOKIE[GATE_COOKIE] ?? '';
    if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $c, $m)) return false;
    if ((int) $m[1] < time()) return false;
    return hash_equals(gate_sign($secret, (int) $m[1]), $c);
}

function safe_target(string $t): string
{
    $t = '/' . ltrim(parse_url($t, PHP_URL_PATH) ?: '/', '/');
    return strpos($t, '..') !== false ? '/' : $t;
}

$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$path   = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Abmelden: /gate.php?abmelden
if (isset($_GET['abmelden'])) {
    setcookie(GATE_COOKIE, '', ['expires' => 1, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: /', true, 303);
    exit;
}

// ---- Anmeldung -----------------------------------------------------------
if (!gate_valid($secret)) {
    $error = '';
    if ($method === 'POST' && isset($_POST['gate_user'])) {
        // einfache Bremse gegen Durchprobieren
        $lock = sys_get_temp_dir() . '/sapienza_gate_' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . $secret), 0, 24);
        $tries = is_file($lock) ? array_filter(array_map('intval', explode(',', (string) file_get_contents($lock))), static fn ($t) => $t > time() - 900) : [];
        if (count($tries) >= 8) {
            $error = 'Zu viele Versuche. Bitte warten Sie 15 Minuten.';
        } else {
            $userOk = hash_equals((string) ($cfg['user'] ?? ''), (string) $_POST['gate_user']);
            $passOk = hash_equals((string) ($cfg['pass'] ?? ''), (string) ($_POST['gate_pass'] ?? ''));
            if ($userOk && $passOk && ($cfg['pass'] ?? '') !== '') {
                @unlink($lock);
                $exp = time() + GATE_DAYS * 86400;
                setcookie(GATE_COOKIE, gate_sign($secret, $exp), ['expires' => $exp, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
                header('Location: ' . safe_target((string) ($_POST['gate_target'] ?? '/')), true, 303);
                exit;
            }
            $tries[] = time();
            @file_put_contents($lock, implode(',', $tries), LOCK_EX);
            sleep(1);
            $error = 'Benutzername oder Passwort stimmt nicht.';
        }
    }

    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $target = htmlspecialchars(safe_target($method === 'POST' ? (string) ($_POST['gate_target'] ?? '/') : $path), ENT_QUOTES, 'UTF-8');
    $err = $error !== '' ? '<p class="gate__error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    echo <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Anmeldung</title>
<link rel="stylesheet" href="/assets/css/gate.css">
</head>
<body>
<main class="gate">
  <img class="gate__logo" src="/assets/img/logo.svg" alt="SAPiENZA" width="413" height="61">
  <p class="gate__note">Diese Website ist noch nicht öffentlich.</p>
  <form class="gate__form" method="post" action="/gate.php">
    <input type="hidden" name="gate_target" value="{$target}">
    <label for="gate-user">Benutzername</label>
    <input id="gate-user" name="gate_user" type="text" autocomplete="username" required autofocus>
    <label for="gate-pass">Passwort</label>
    <input id="gate-pass" name="gate_pass" type="password" autocomplete="current-password" required>
    {$err}
    <button type="submit">Anmelden</button>
  </form>
</main>
</body>
</html>
HTML;
    exit;
}

// ---- Angemeldet: angefragte Datei ausliefern -----------------------------
$root = realpath(__DIR__);
$file = realpath($root . $path);
if ($file !== false && is_dir($file)) {
    $file = realpath($file . '/index.html');
}

$blocked = ['gate.php', 'gate-config.php', 'kontakt-config.php', 'kontakt-fehler.log', '.htaccess'];
if ($file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || in_array(basename($file), $blocked, true)
    || substr(basename($file), 0, 1) === '.') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/404.html');
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext === 'php') {
    // z. B. kontakt.php, mailtest.php
    chdir(dirname($file));
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
    exit;
}

$types = [
    'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
    'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'woff2' => 'font/woff2', 'xml' => 'application/xml; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
    'ico' => 'image/x-icon', 'json' => 'application/json',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: ' . ($ext === 'html' ? 'private, no-cache' : 'private, max-age=31536000, immutable'));
readfile($file);
