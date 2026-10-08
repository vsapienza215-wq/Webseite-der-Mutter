<?php
/**
 * SAPiENZA – Versand des Kontaktformulars (PHP 7.4+)
 *
 * Versandweg: SMTP über ein echtes Postfach (empfohlen, zuverlässig),
 * sonst Fallback auf PHP mail(). Zugangsdaten stehen in kontakt-config.php.
 * Spam-Schutz ohne Drittanbieter: Honeypot-Feld + Mindest-Ausfüllzeit + Drosselung.
 * Fehler (ohne persönliche Daten) landen in kontakt-fehler.log (per .htaccess gesperrt).
 */

declare(strict_types=1);

$config = array_merge([
    'mail_to'     => 'kontakt@concetta-sapienza.com',
    'mail_from'   => 'kontakt@concetta-sapienza.com',
    'smtp_host'   => '',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',
    'smtp_user'   => '',
    'smtp_pass'   => '',
], is_file(__DIR__ . '/kontakt-config.php') ? (array) require __DIR__ . '/kontakt-config.php' : []);

const MIN_SECONDS = 3;     // schneller ausgefüllt = Bot
const RATE_LIMIT  = 5;     // max. Anfragen pro IP …
const RATE_WINDOW = 3600;  // … pro Stunde

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(bool $ok, string $message, int $code = 200): void
{
    global $wantsJson;
    http_response_code($code);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $title = $ok ? 'Vielen Dank' : 'Hinweis';
        $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="robots" content="noindex"><title>' . $title . ' – SAPiENZA</title><link rel="stylesheet" href="/assets/css/style.css"></head>'
           . '<body><main class="section notfound"><div class="container container--narrow stack"><h1>' . $title . '</h1><p>' . $msg . '</p>'
           . '<p><a class="text-link" href="/">Zur Startseite</a></p></div></main></body></html>';
    }
    exit;
}

function log_error(string $msg): void
{
    @file_put_contents(__DIR__ . '/kontakt-fehler.log', date('Y-m-d H:i:s') . '  ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function clean_line(string $value, int $max): string
{
    $value = trim(preg_replace('/[\r\n\t\0]+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max, 'UTF-8');
}

/** Minimaler SMTP-Client (SSL auf 465 oder STARTTLS auf 587, AUTH LOGIN). */
function smtp_send(array $c, string $to, string $subject, string $body): void
{
    $remote = ($c['smtp_secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['smtp_host'] . ':' . (int) $c['smtp_port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Verbindung zu {$remote} fehlgeschlagen: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = static function (?string $line, array $expect) use ($fp, $read): string {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
            $shown = $line !== null && stripos($line, 'AUTH') === false && strlen($line) < 100 ? $line : '(Befehl)';
            throw new RuntimeException("SMTP-Fehler bei {$shown}: " . trim($resp));
        }
        return $resp;
    };

    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $cmd(null, [220]);
    $cmd('EHLO ' . $host, [250]);
    if ($c['smtp_secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0))) {
            throw new RuntimeException('STARTTLS fehlgeschlagen');
        }
        $cmd('EHLO ' . $host, [250]);
    }
    if ($c['smtp_user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['smtp_user']), [334]);
        $cmd(base64_encode($c['smtp_pass']), [235]);
    }
    $cmd('MAIL FROM:<' . $c['mail_from'] . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);

    $headers = [
        'Date: ' . date('r'),
        'From: SAPiENZA Website <' . $c['mail_from'] . '>',
        'To: <' . $to . '>',
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'B'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($c['mail_from'], '@'), 1) . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}

function php_mail_send(array $c, string $to, string $subject, string $body): void
{
    if (!function_exists('mail')) {
        throw new RuntimeException('PHP mail() ist auf diesem Server deaktiviert');
    }
    $headers = [
        'From: SAPiENZA Website <' . $c['mail_from'] . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $ok = mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B'), $body, implode("\r\n", $headers), '-f' . $c['mail_from']);
    if (!$ok) {
        throw new RuntimeException('PHP mail() hat false zurückgegeben (Server verschickt keine Mails)');
    }
}

// ---------------------------------------------------------------------------

// mailtest.php nutzt nur Einstellungen und Versandfunktionen
if (defined('SAPIENZA_MAILTEST')) {
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'Bitte nutzen Sie das Formular auf der Website.', 405);
}

// Honeypot: Menschen sehen dieses Feld nicht. Bots erhalten eine scheinbare Erfolgsmeldung.
if (!empty($_POST['website'])) {
    respond(true, 'Vielen Dank.');
}

// Mindest-Ausfüllzeit
$ts = (int) ($_POST['ts'] ?? 0);
if ($ts > 0 && (time() - intdiv($ts, 1000)) < MIN_SECONDS) {
    respond(true, 'Vielen Dank.');
}

// Drosselung pro IP (gehasht, keine Klartext-IP gespeichert)
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . __FILE__);
$rateFile = sys_get_temp_dir() . '/sapienza_rate_' . substr($ipHash, 0, 32);
$hits = [];
if (is_file($rateFile)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string) file_get_contents($rateFile))),
        static fn (int $t): bool => $t > time() - RATE_WINDOW
    );
}
if (count($hits) >= RATE_LIMIT) {
    respond(false, 'Es wurden bereits mehrere Anfragen gesendet. Bitte versuchen Sie es später erneut.', 429);
}

$vorname  = clean_line((string) ($_POST['vorname'] ?? ''), 60);
$telefon  = clean_line((string) ($_POST['telefon'] ?? ''), 30);
$anliegen = mb_substr(trim((string) ($_POST['anliegen'] ?? '')), 0, 2000, 'UTF-8');
$einwilligung = ($_POST['einwilligung'] ?? '') === 'ja';

if ($vorname === '' || $telefon === '' || !$einwilligung) {
    respond(false, 'Bitte füllen Sie alle Pflichtfelder aus und bestätigen Sie die Einwilligung.', 422);
}
if (!preg_match('/^[0-9+()\/\-\s]{6,30}$/', $telefon)) {
    respond(false, 'Bitte prüfen Sie die Telefonnummer.', 422);
}

$subject = 'Neue Anfrage über die Website – ' . $vorname;
$body = "Neue Anfrage über das Kontaktformular\r\n"
      . "=====================================\r\n\r\n"
      . "Vorname:  {$vorname}\r\n"
      . "Telefon:  {$telefon}\r\n\r\n"
      . "Worum geht es?\r\n"
      . ($anliegen !== '' ? str_replace(["\r\n", "\r", "\n"], "\r\n", $anliegen) : '(keine Angabe)') . "\r\n\r\n"
      . "-------------------------------------\r\n"
      . "Einwilligung Datenschutz (inkl. Gesundheitsdaten): erteilt\r\n"
      . 'Gesendet am: ' . date('d.m.Y, H:i') . " Uhr\r\n";

try {
    if ($config['smtp_host'] !== '') {
        smtp_send($config, $config['mail_to'], $subject, $body);
    } else {
        php_mail_send($config, $config['mail_to'], $subject, $body);
    }
} catch (Throwable $e) {
    log_error($e->getMessage());
    respond(false, 'Das hat leider nicht geklappt. Bitte versuchen Sie es später erneut oder nutzen Sie die Kontaktdaten im Impressum.', 500);
}

$hits[] = time();
@file_put_contents($rateFile, implode(',', $hits), LOCK_EX);

respond(true, 'Vielen Dank. Ihre Anfrage ist angekommen. Ich melde mich telefonisch bei Ihnen.');
