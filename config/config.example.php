<?php

/*
 * Konfiguration. Als config/config.php kopieren und anpassen.
 * Ohne config.php gelten die Standardwerte (SQLite unter data/).
 */
return [
    'db' => [
        // 'sqlite' (lokal) oder 'mysql' (online)
        'driver' => 'sqlite',
        'sqlite_pfad' => __DIR__ . '/../data/wettkampf.sqlite',

        // nur für MySQL/MariaDB
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'wettkampf',
        'user' => 'wettkampf',
        'passwort' => '',
    ],
    // Bei Unterordner-Installation ohne funktionierende Erkennung manuell setzen, z. B. '/wettkampf'
    'basis_url' => null,
    'zeitzone' => 'Europe/Berlin',
    'debug' => false,
    // Statusseite und öffentliche API: höchstens so viele Abfragen je IP und Minute (0 = keine Grenze).
    // Die Statusseite fragt alle 4 Sekunden nach (15 je Minute und Gerät). Online ohne Cloudflare-Zwischenspeicher
    // teilen sich in der Halle oft alle Handys eine IP – bei sehr vielen Besuchern erhöhen.
    'oeffentlich_max_je_minute' => 1200,
    // Adresse des Quellcodes dieser Installation (AGPL-3.0, Abschnitt 13) – erscheint auf „Über diese Software“.
    // Pflicht für Betreiber veränderter Fassungen, z. B. 'https://github.com/…'
    'quellcode_url' => null,
    // Automatische Sicherung der SQLite-Datenbank (Hallen-Laptop): höchstens alle abstand_sekunden und nur bei Änderungen,
    // die letzten „anzahl“ Stände bleiben in „ordner“ (Standard data/sicherungen). Für MySQL ohne Wirkung.
    'auto_sicherung' => ['aktiv' => true, 'abstand_sekunden' => 120, 'anzahl' => 30],
    // Öffentliche Adresse dieser Installation (für Links in E-Mails), z. B. 'https://turnier.example.de' – Pflicht für den Mailversand
    'oeffentliche_url' => null,
    // E-Mail-Versand (Bestätigung von Dojo-Konten, Passwort vergessen, Bearbeitungsstand der Online-Anmeldung).
    // transport: null = aus, 'smtp' = über ein Postfach, 'datei' = nur in data/mail/ ablegen (zum Ausprobieren).
    // Auf dem Hallen-Laptop aus lassen – Online verschickt.
    'mail' => [
        'transport' => null,
        'host' => 'smtp.example.de',
        'port' => 587,
        'verschluesselung' => 'tls', // 'tls' (STARTTLS, Port 587), 'ssl' (Port 465) oder '' (unverschlüsselt, nur intern)
        'benutzer' => 'turnier@example.de',
        'passwort' => '',
        'absender' => 'turnier@example.de',
        'absender_name' => 'Taikai – Karate',
        'antwort_an' => null, // z. B. Adresse der Wettkampfleitung
    ],
    // Anmeldung mit zweitem Faktor (Authenticator-App bzw. Code per E-Mail; Pflicht einstellbar unter System → Anmeldung).
    // NUR als Notschalter, wenn sich niemand mehr anmelden kann (Versand gestört, Handy weg): 'mfa' => false
    // 'mfa' => true,
    // Empfänger der Meldungen über den Feedback-Knopf (Standard: Team von Taikai – Karate). Der Knopf setzt den
    // E-Mail-Versand voraus. Eigene Weiterentwicklungen können hier ihre eigene Adresse eintragen.
    // 'feedback_adresse' => 'feedback@taikai-karate.de',
    // Schutz öffentlicher Formulare (Online-Anmeldung, Login, Passwort vergessen): Rechenaufgabe im Browser
    // (schwierigkeit in Bit, 16 ≈ unter einer Sekunde), Mindest-Ausfüllzeit in Sekunden
    'bot_schutz' => ['aktiv' => true, 'schwierigkeit' => 16, 'mindestzeit' => 3],
    // Spendenlink auf „Über diese Software“: null = Spendenseite des Projekts, '' = ausblenden, oder eigene https-Adresse
    'spenden_url' => null,
];
