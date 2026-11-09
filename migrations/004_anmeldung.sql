-- Online-Anmeldung der Dojos: Teilnehmer, Disziplinen und Teams als JSON.
-- Erst mit der Freigabe durch die Wettkampfleitung werden daraus Verein,
-- Teilnehmer und Teams angelegt und den Klassen zugeordnet.
CREATE TABLE anmeldung (
    id {{PK}},
    uuid VARCHAR(36) NOT NULL UNIQUE,
    turnier_id {{FK}} NOT NULL,
    code VARCHAR(40) NOT NULL UNIQUE,
    dojo VARCHAR(200) NOT NULL,
    ansprechpartner VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    telefon VARCHAR(50) NULL,
    bemerkung TEXT NULL,
    daten TEXT NOT NULL,
    preis_cent INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'eingereicht',
    grund VARCHAR(500) NULL,
    verein_id {{FK}} NULL,
    uebernahme TEXT NULL,
    eingereicht_am VARCHAR(19) NOT NULL,
    geaendert_am VARCHAR(19) NULL,
    freigegeben_am VARCHAR(19) NULL,
    freigegeben_von VARCHAR(100) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (verein_id) REFERENCES verein(id) ON DELETE SET NULL
){{OPTS}};
