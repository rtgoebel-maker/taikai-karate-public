-- Systemweite Einstellungen (für alle Turniere) und Feedback der Nutzer
-- (Fehler, Verbesserungsvorschläge, Fragen) samt Screenshot als Base64.
CREATE TABLE einstellung (
    schluessel VARCHAR(100) NOT NULL PRIMARY KEY,
    wert TEXT NOT NULL
){{OPTS}};

CREATE TABLE feedback (
    id {{PK}},
    art VARCHAR(20) NOT NULL,
    beschreibung TEXT NOT NULL,
    seite VARCHAR(500) NOT NULL,
    turnier_id INTEGER NULL,
    von VARCHAR(200) NOT NULL,
    geraet VARCHAR(300) NULL,
    screenshot {{GROSSTEXT}} NULL,
    screenshot_mime VARCHAR(30) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'neu',
    notiz TEXT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    geaendert_am VARCHAR(19) NULL
){{OPTS}};
