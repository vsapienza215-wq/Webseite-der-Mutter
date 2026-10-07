<?php
/**
 * Einmaliger Test des Mailversands. Aufruf: https://IHRE-DOMAIN/mailtest.php
 * Schickt eine Testmail an den eingestellten Empfänger und zeigt das Ergebnis.
 * NACH DEM TEST VOM SERVER LÖSCHEN.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

// höchstens ein Test pro Minute
$lock = sys_get_temp_dir() . '/sapienza_mailtest';
if (is_file($lock) && time() - (int) filemtime($lock) < 60) {
    exit("Bitte eine Minute warten.\n");
}
@touch($lock);

// kontakt.php-Funktionen und Einstellungen laden, ohne das Formular auszuführen
$_SERVER['REQUEST_METHOD'] = 'MAILTEST';
define('SAPIENZA_MAILTEST', true);
require __DIR__ . '/kontakt.php';

$c = $config;
echo "PHP-Version:   " . PHP_VERSION . "\n";
echo "Empfänger:     {$c['mail_to']}\n";
echo "Absender:      {$c['mail_from']}\n";
echo "Versandweg:    " . ($c['smtp_host'] !== '' ? "SMTP {$c['smtp_host']}:{$c['smtp_port']} ({$c['smtp_secure']})" : 'PHP mail()') . "\n";
echo "Konfiguration: " . (is_file(__DIR__ . '/kontakt-config.php') ? 'kontakt-config.php gefunden' : 'keine kontakt-config.php (Standardwerte)') . "\n\n";

try {
    $body = "Dies ist eine Testmail des Kontaktformulars.\r\nGesendet am " . date('d.m.Y, H:i:s') . " Uhr.\r\n";
    if ($c['smtp_host'] !== '') {
        smtp_send($c, $c['mail_to'], 'Testmail Kontaktformular', $body);
    } else {
        php_mail_send($c, $c['mail_to'], 'Testmail Kontaktformular', $body);
    }
    echo "ERGEBNIS: Der Server hat die Mail angenommen.\n";
    echo "Kommt sie trotzdem nicht an: Spam-Ordner prüfen. Dann ist die Absenderadresse das Problem\n";
    echo "(muss zur Domain auf diesem Server gehören) -> SMTP mit echtem Postfach einrichten.\n";
} catch (Throwable $e) {
    echo "ERGEBNIS: FEHLER\n" . $e->getMessage() . "\n";
}
echo "\nDiese Datei nach dem Test bitte löschen.\n";
