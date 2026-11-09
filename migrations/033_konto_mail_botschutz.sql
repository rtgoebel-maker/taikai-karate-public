-- Selbst angelegte Dojo-Konten (Online-Anmeldung), E-Mail-Versand und Schutz öffentlicher Formulare.

-- Konto wartet auf die Bestätigung der E-Mail-Adresse; selbst angelegte Konten laufen nach dem Turnier ab
ALTER TABLE benutzer ADD COLUMN bestaetigung_offen INTEGER NOT NULL DEFAULT 0;
ALTER TABLE benutzer ADD COLUMN ablauf_am VARCHAR(10) NULL;

-- Einmal-Links: E-Mail bestätigen, Passwort neu setzen (nur der SHA-256 des Tokens wird gespeichert)
CREATE TABLE konto_token (
    id {{PK}},
    benutzer_id {{FK}} NOT NULL,
    zweck VARCHAR(20) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    ablauf VARCHAR(19) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
){{OPTS}};

-- Anmeldungen eines Kontos (über die UUID der Anmeldung: übersteht Übergabe und Rückgabe an die Halle)
CREATE TABLE konto_anmeldung (
    benutzer_id {{FK}} NOT NULL,
    anmeldung_uuid VARCHAR(36) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    PRIMARY KEY (benutzer_id, anmeldung_uuid),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
){{OPTS}};

-- Warteschlange für E-Mails (Kern\Mail\Postausgang)
CREATE TABLE mail_ausgang (
    id {{PK}},
    empfaenger VARCHAR(190) NOT NULL,
    betreff VARCHAR(200) NOT NULL,
    text {{GROSSTEXT}} NOT NULL,
    anlass VARCHAR(40) NOT NULL,
    turnier_id {{FK}} NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    gesendet_am VARCHAR(19) NULL,
    versuche INTEGER NOT NULL DEFAULT 0,
    naechster_versuch VARCHAR(19) NULL,
    fehler VARCHAR(500) NULL
){{OPTS}};
CREATE INDEX idx_mail_ausgang_offen ON mail_ausgang (gesendet_am, naechster_versuch);

-- verbrauchte Formular-Token (Kern\BotSchutz: jedes Formular nur einmal absendbar)
CREATE TABLE formular_token (
    nonce VARCHAR(40) NOT NULL PRIMARY KEY,
    ablauf VARCHAR(19) NOT NULL
){{OPTS}};
