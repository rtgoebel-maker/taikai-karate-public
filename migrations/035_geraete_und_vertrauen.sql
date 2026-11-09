-- Zweiter Faktor: „Diesem Gerät 30 Tage vertrauen“ (Cookie mit Zufallswert, hier nur der SHA-256).
CREATE TABLE konto_vertrauen (
    id {{PK}},
    benutzer_id {{FK}} NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    geraet VARCHAR(200) NULL,
    ablauf VARCHAR(19) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
){{OPTS}};

-- Geräte-Kopplung für Tischbesatzung und Ausruf. Der QR-Code je Tatami und Rolle enthält ein HMAC über
-- Tatami, Rolle und dieses Salz (Schlüssel der Installation) – neues Salz = alte QR-Codes ungültig.
CREATE TABLE tatami_kopplung (
    id {{PK}},
    tatami_id {{FK}} NOT NULL,
    rolle VARCHAR(10) NOT NULL,
    salz VARCHAR(32) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    FOREIGN KEY (tatami_id) REFERENCES tatami(id) ON DELETE CASCADE
){{OPTS}};
CREATE UNIQUE INDEX idx_tatami_kopplung_rolle ON tatami_kopplung (tatami_id, rolle);

-- Gekoppelte Geräte (Cookie mit Zufallswert, hier nur der SHA-256): warten auf Freigabe, freigegeben oder gesperrt.
CREATE TABLE tatami_geraet (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    tatami_id {{FK}} NOT NULL,
    rolle VARCHAR(10) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    pruefwort VARCHAR(10) NOT NULL,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'wartet',
    erstellt_am VARCHAR(19) NOT NULL,
    freigegeben_am VARCHAR(19) NULL,
    freigegeben_von VARCHAR(100) NULL,
    zuletzt_am VARCHAR(19) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (tatami_id) REFERENCES tatami(id) ON DELETE CASCADE
){{OPTS}};
CREATE INDEX idx_tatami_geraet_turnier ON tatami_geraet (turnier_id, status);

-- PIN-Anmeldung am Tisch als Notlösung je Turnier abschaltbar (Standard: erlaubt, wie bisher)
ALTER TABLE turnier ADD COLUMN tisch_pin_erlaubt INTEGER NOT NULL DEFAULT 1;
