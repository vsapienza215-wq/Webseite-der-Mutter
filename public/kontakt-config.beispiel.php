<?php
/**
 * VORLAGE: Kopie als kontakt-config.php speichern und dort ausfüllen.
 * Zugangsdaten für den Mailversand des Kontaktformulars.
 * Diese Datei ist per .htaccess gesperrt und wird nie im Browser angezeigt.
 * Passwort NUR auf dem Server eintragen, nicht ins Git-Repository.
 *
 * Empfohlen: beim Hoster ein Postfach kontakt@concetta-sapienza.com anlegen
 * und dessen SMTP-Daten hier eintragen. Bleibt smtp_host leer, wird PHP mail() benutzt.
 *
 * Typische Werte:  IONOS  smtp.ionos.de   587 tls
 *                  Strato smtp.strato.de  465 ssl
 *                  all-inkl  w0xxxxxx.kasserver.com 465 ssl  (Benutzer: m0xxxxxx oder E-Mail-Adresse, siehe KAS → E-Mail)
 *                  Hostinger smtp.hostinger.com 465 ssl
 */
return [
    'mail_to'     => 'mumsapienza@gmail.com',        // Empfänger der Anfragen
    'mail_from'   => 'kontakt@concetta-sapienza.com', // muss zum SMTP-Postfach passen
    'smtp_host'   => '',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',                          // 'tls' (587) oder 'ssl' (465)
    'smtp_user'   => '',                             // meist die volle E-Mail-Adresse
    'smtp_pass'   => '',
];
