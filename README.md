# Taikai – Karate

<img src="public/assets/logo/taikai-logo.svg" alt="Taikai – Karate" width="300">

**Taikai** (jap. 大会 – „Turnier“) ist eine freie Wettkampfsoftware für Karate-Turniere · [taikai-karate.de](https://taikai-karate.de)

Sie begleitet ein Turnier von der Online-Anmeldung der Dojos über Klasseneinteilung, Auslosung und Tatami-Plan bis zur Ergebniseingabe am Tablet, zu Urkunden, Siegerlisten und einer öffentlichen Statusseite – für Kata, Kumite, Kata-Team und Kumite-Team nach der DJKB-Wettkampfordnung 2024.

> **Dieses Repository** enthält den Quellcode der Software, wie er auf unseren Servern läuft – veröffentlicht nach der GNU Affero General Public License (AGPL). Jeder Commit entspricht einer Programmversion (`JJJJ.MM.TT-xxxxxxx`, angezeigt unter „Über diese Software“). Entwickelt wird in einem eigenen Repository; Dokumentation, Tests und Entwicklungswerkzeuge sind hier nicht enthalten.

## Was die Software kann

- **Online-Anmeldung** für Dojos über einen nicht erratbaren Link: Teilnehmer, Disziplinen und Teams, Startgeld, Zahlungsaufforderung mit Überweisungs-QR-Code (GiroCode), Bearbeitungsstand bis zur Freigabe
- **Klassen** nach Geschlecht, Alter, Graduierung und Disziplin; Vorlagen (z. B. DJKB-Standard), Zusammenlegen kleiner Klassen, rundenabhängige Regeln
- **Modi und Wertung:** K.-o. mit Kampf um Platz 3, Pools mit Finalrunde, Punkte-Vorrunde mit Finale, jeder gegen jeden; Flaggen, Kata-Punkte mit Streichwertung, Kumite (Shobu Sanbon, Hantei, Saishiai)
- **Auslosung** zufällig und nachvollziehbar (Seed und Protokoll), Starter desselben Dojos möglichst spät gegeneinander
- **Tatami-Plan** per Drag & Drop mit geschätzten Startzeiten
- **Tablet am Tisch** (eine Tatami je Gerät) für Ergebnisse und Kampfuhr, **Handy für den Ausruf**; Geräte koppeln per QR-Code mit Freigabe durch die Leitung
- **Öffentliche Statusseite** mit laufenden Kämpfen, Bäumen und Ergebnissen – nur Name und Verein, keine Geburtsdaten
- **Coaching-Portal:** Coaches sehen ihre Starter live und stellen Korrekturanträge
- **Ausdrucke:** Turnierbäume, Kampflisten, Ergebnis- und Siegerlisten, Urkunden mit Logos und Unterschriften
- **Hallen-Laptop:** Ohne verlässliches Internet wird das Turnier an einen Laptop übergeben; die Tablets arbeiten im Hallen-WLAN, der Laptop überträgt den Stand im Hintergrund an Online
- **Sicherheit:** Rollen und Rechte je Turnier, zweiter Faktor (Authenticator-App oder Code per E-Mail), Sperre nach Fehlversuchen, CSRF-Schutz, Content-Security-Policy, Änderungsverlauf; ausgerichtet an den OWASP Top 10

## Betriebsarten

| Betriebsart | Ablauf |
|---|---|
| **Online** | Normaler Webspace mit PHP und MySQL/MariaDB: Anmeldung, Verwaltung, Statusseite, Coaching-Portal; Tablets arbeiten direkt online |
| **Online + Hallen-Laptop** | Das Turnier wird an einen Laptop (Windows oder Linux) übergeben und nach dem Turnier zurückgegeben – es schreibt immer nur eine Stelle |
| **Nur lokal** | Ein Rechner mit SQLite, komplett offline, z. B. für Vereinsturniere |

## Voraussetzungen

- **PHP 8.3 oder neuer** mit `pdo_sqlite` (lokal) bzw. `pdo_mysql` (online), `mbstring`, `json`, `zip`; für die Übertragung Hallen-Laptop → Online zusätzlich `openssl` und `curl`
- [Composer](https://getcomposer.org/download/) für die Laufzeit-Bibliotheken (nicht im Repository enthalten)
- Kein Node.js, keine externen Dienste, keine Skripte von Drittanbietern
- Ein aktueller Browser (Edge, Chrome, Firefox, Safari)

## Installation

```bash
git clone https://github.com/rtgoebel-maker/taikai-karate-public.git taikai
cd taikai
composer install --no-dev --optimize-autoloader
```

**Lokal ausprobieren:** `./start-lokal.sh` (Linux) bzw. `start-lokal.bat` (Windows) startet den integrierten PHP-Server; die Software läuft dann unter `http://localhost:8080`, Tablets im selben WLAN erreichen sie unter `http://<IP-des-Rechners>:8080/tisch`. Ohne weitere Einstellungen liegen die Daten in `data/wettkampf.sqlite`.

**Webspace:** Den Ordner `public/` als Document-Root einstellen (sonst leitet die `.htaccess` im Hauptordner um), `config/config.example.php` nach `config/config.php` kopieren und die MySQL-Zugangsdaten eintragen. Die Tabellen legt die Software beim ersten Aufruf selbst an. HTTPS einschalten.

**Linux-Rechner in der Halle als Dauerbetrieb** (startet mit dem Einschalten, Port 80): `sudo ./dienste-einrichten.sh`.

**Aktualisieren:** `git pull` und danach `composer install --no-dev --optimize-autoloader`. Datenbank-Änderungen spielt die Software beim nächsten Aufruf selbst ein; vorher eine Sicherung anlegen (Menü „Sicherung“).

**Ersteinrichtung:** Beim ersten Aufruf erscheint `/setup`. Dort das Konto der Wettkampfleitung anlegen; der Einrichtungscode steht in der Datei `data/einrichtung.token` auf dem Server. Danach führt die Software Schritt für Schritt durch Turnier, Klassen, Teilnehmer, Auslosung und Tatami-Plan; eine Hilfe ist eingebaut.

**Zum Ausprobieren:** `php bin/demodaten.php` legt ein Demo-Turnier mit 150 erfundenen Teilnehmern an.

Weitere Einstellungen (E-Mail-Versand, öffentliche Adresse, Zeitzone, Bot-Schutz, zweiter Faktor) sind in `config/config.example.php` beschrieben; den E-Mail-Versand kann die Administration auch in der Oberfläche unter **System** einrichten.

## Lizenz

Freie Software unter der **GNU Affero General Public License, Version 3 oder später** – siehe [LICENSE](LICENSE).

Urheber: **Robert Göbel** und **Wolfgang Maier**. Die Software darf frei genutzt, verändert und weitergegeben werden – unter denselben Bedingungen und **immer mit Nennung der Urheber** (zusätzliche Bedingung nach Abschnitt 7 b, siehe [NOTICE](NOTICE)).

Wer eine veränderte Fassung über das Netz anbietet, muss deren Quellcode den Nutzern anbieten: Dafür in `config/config.php` die Adresse `quellcode_url` setzen – sie erscheint auf der Seite „Über diese Software“ (`/ueber`). Die verwendeten Bibliotheken stehen unter ihren eigenen Lizenzen; die Liste zeigt dieselbe Seite.

Die Wertungsregeln folgen der Wettkampfordnung des Deutschen JKA-Karate Bundes (DJKB). Die Software ist kein offizielles Produkt des DJKB.

## Kontakt und Unterstützung

Fragen, Fehler und Ideen gerne an **feedback@taikai-karate.de** – oder direkt aus der Software über den Feedback-Knopf.

Die Software ist frei und kostenlos, entwickelt in der Freizeit und getestet auf echten Turnieren. Wenn sie Ihrem Verein Arbeit abnimmt, freuen wir uns über eine kleine Spende für Pflege, Sicherheit und Weiterentwicklung: **[Spenden über PayPal](https://www.paypal.com/donate/?hosted_button_id=D6HGYUXLQUBKJ)**. Die Spende geht an Privatpersonen; eine Spendenbescheinigung ist nicht möglich.
