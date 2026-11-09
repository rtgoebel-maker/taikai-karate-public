<?php

/*
 * Konfiguration für die Staging-Plattform (netcup Webhosting + Cloudflare).
 * Auf dem Server als config/config.php ablegen und die Platzhalter ersetzen.
 * Anleitung: docs/STAGING-NETCUP-CLOUDFLARE.md
 */
return [
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',          // laut Plesk → Datenbanken
        'port' => 3306,
        'name' => 'DATENBANKNAME',
        'user' => 'DATENBANKBENUTZER',
        'passwort' => 'DATENBANKPASSWORT',
    ],
    'basis_url' => null,                // Subdomain mit Document-Root public/ → automatisch
    'zeitzone' => 'Europe/Berlin',
    'debug' => false,                   // auf Staging aus lassen: Fehlertexte nicht nach außen

    // Nur Anfragen zulassen, die durch Cloudflare Access gelaufen sind.
    'cloudflare_access' => [
        'aktiv' => true,
        // Zero Trust → Settings → Custom Pages bzw. Team domain, ohne Schrägstrich am Ende
        'team_domain' => 'https://TEAMNAME.cloudflareaccess.com',
        // Zero Trust → Access → Applications → <Anwendung> → Overview → Application Audience (AUD) Tag
        'aud' => 'AUD-TAG',
    ],

    // Alle Anfragen laufen über Cloudflare: echte Client-IP für die Sperre nach Fehlversuchen
    'client_ip_header' => 'cf-connecting-ip',
];
