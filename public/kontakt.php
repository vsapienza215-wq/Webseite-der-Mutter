<?php
/**
 * SAPiENZA – Versand des Kontaktformulars
 * Läuft auf dem eigenen Hosting (PHP 7.4+), nutzt mail().
 * Spam-Schutz ohne Drittanbieter: Honeypot-Feld + Mindest-Ausfüllzeit + einfache Drosselung.
 */

declare(strict_types=1);

// ---- Einstellungen -------------------------------------------------------
const MAIL_TO      = 'csapienza@gmx.de';
// Absender muss eine Adresse der eigenen Domain sein (sonst landet die Mail im Spam).
const MAIL_FROM    = 'website@concetta.sapienza.com';
const MIN_SECONDS  = 3;     // schneller ausgefüllt = Bot
const RATE_LIMIT   = 5;     // max. Anfragen pro IP …
const RATE_WINDOW  = 3600;  // … pro Stunde
// --------------------------------------------------------------------------

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

function clean_line(string $value, int $max): string
{
    $value = trim(preg_replace('/[\r\n\t\0]+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max, 'UTF-8');
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

// Einfache Drosselung pro IP (gehasht, keine Klartext-IP gespeichert)
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

$vorname = clean_line((string) ($_POST['vorname'] ?? ''), 60);
$telefon = clean_line((string) ($_POST['telefon'] ?? ''), 30);
$anliegen = mb_substr(trim((string) ($_POST['anliegen'] ?? '')), 0, 2000, 'UTF-8');
$einwilligung = ($_POST['einwilligung'] ?? '') === 'ja';

if ($vorname === '' || $telefon === '' || !$einwilligung) {
    respond(false, 'Bitte füllen Sie alle Pflichtfelder aus und bestätigen Sie die Einwilligung.', 422);
}
if (!preg_match('/^[0-9+()\/\-\s]{6,30}$/', $telefon)) {
    respond(false, 'Bitte prüfen Sie die Telefonnummer.', 422);
}

$subject = 'Neue Anfrage über die Website – ' . $vorname;
$body = "Neue Anfrage über das Kontaktformular\n"
      . "=====================================\n\n"
      . "Vorname:  {$vorname}\n"
      . "Telefon:  {$telefon}\n\n"
      . "Worum geht es?\n"
      . ($anliegen !== '' ? $anliegen : '(keine Angabe)') . "\n\n"
      . "-------------------------------------\n"
      . "Einwilligung Datenschutz (inkl. Gesundheitsdaten): erteilt\n"
      . 'Gesendet am: ' . date('d.m.Y, H:i') . " Uhr\n";

$headers = [
    'From: SAPiENZA Website <' . MAIL_FROM . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: SAPiENZA',
];

$sent = mail(
    MAIL_TO,
    mb_encode_mimeheader($subject, 'UTF-8', 'B'),
    $body,
    implode("\r\n", $headers),
    '-f' . MAIL_FROM
);

if (!$sent) {
    respond(false, 'Das hat leider nicht geklappt. Bitte versuchen Sie es später erneut oder schreiben Sie eine E-Mail an ' . MAIL_TO . '.', 500);
}

$hits[] = time();
@file_put_contents($rateFile, implode(',', $hits), LOCK_EX);

respond(true, 'Vielen Dank. Ihre Anfrage ist angekommen. Ich melde mich telefonisch bei Ihnen.');
