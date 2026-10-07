# SAPiENZA – Website

Statische Website (HTML/CSS/JS + ein PHP-Skript für das Kontaktformular). Kein CMS, keine Tracker, keine Cookies, keine externen Ressourcen.

## Upload
Den **Inhalt von `public/`** in das Hauptverzeichnis des Webspaces laden (inkl. versteckter Datei `.htaccess`). Voraussetzung: Apache + PHP ≥ 7.4 mit `mail()`.

## Kontaktformular einrichten (einmalig)
1. Beim Hoster ein Postfach `website@concetta-sapienza.com` anlegen.
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
