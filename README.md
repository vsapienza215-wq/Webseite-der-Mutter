# SAPiENZA – Website

Statische Website (HTML/CSS/JS + ein PHP-Skript für das Kontaktformular). Kein CMS, keine Tracker, keine Cookies, keine externen Ressourcen.

## Upload
Den **Inhalt von `public/`** in das Hauptverzeichnis des Webspaces laden (inkl. versteckter Datei `.htaccess`). Voraussetzung: Apache + PHP ≥ 7.4 mit `mail()`.

## Kontaktformular einrichten (einmalig)
0. Schnelltest: `https://DOMAIN/mailtest.php` aufrufen – zeigt, ob der Server Mails annimmt und warum nicht. Danach `mailtest.php` löschen.
1. Beim Hoster (all-inkl: KAS → E-Mail) ein Postfach `kontakt@concetta-sapienza.com` anlegen.
2. Auf dem Server `kontakt-config.beispiel.php` kopieren als `kontakt-config.php` und SMTP-Daten des Postfachs eintragen (Server, Port, Benutzer, Passwort).
3. Testanfrage senden. Klappt es nicht: Datei `kontakt-fehler.log` per FTP ansehen (enthält nur die Fehlermeldung, keine Kundendaten).

`kontakt-config.php` ist nicht im Upload-Paket und wird bei späteren Uploads nicht überschrieben. Passwort nie ins Repository.

## Ändern
- Texte: `src/pages/*.html` (Kopfblock = Title/Description/URL)
- Header, Footer, Kontaktformular, Domain: `build.py`
- Design: `public/assets/css/style.css`
- Danach: `python3 build.py` → erzeugt `public/` neu (inkl. sitemap.xml, robots.txt, CSP-Hash, Cache-Busting)

## Vor dem Livegang
- `[PLZ]` und `[Telefonnummer]` im Impressum (`src/pages/impressum.html`) ergänzen
- Datenschutzerklärung + Haftungshinweis einfügen und rechtlich prüfen lassen; danach `robots: noindex` dort entfernen
- Domain in `build.py` (`SITE`) und `public/kontakt.php` (`MAIL_FROM`) prüfen
- HTTPS-Weiterleitung in `public/.htaccess` aktivieren
- Testanfrage über das Formular senden (Absenderadresse muss zur Domain gehören, SPF beachten)

## Passwortschutz (vor dem Livegang)
- Alle Aufrufe laufen über `gate.php`; ohne Anmeldung erscheint nur eine weiße Login-Seite.
- Zugangsdaten stehen in `gate-config.php` (nur auf dem Server, nicht im Repository; Vorlage: `gate-config.beispiel.php`).
- Anmeldung gilt 30 Tage pro Browser. Abmelden: `/gate.php?abmelden`.
- **Zum Livegang:** in `.htaccess` den Block „Passwortschutz“ löschen (die Dateien `gate.php` / `gate-config.php` können danach weg).
