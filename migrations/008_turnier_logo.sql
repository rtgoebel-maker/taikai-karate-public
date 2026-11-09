-- Logos für Urkunden (Verband, Verein und Dojo des Ausrichters) als Base64 in der
-- Datenbank – damit sind sie in Turnier-Export und Umzug automatisch enthalten.
CREATE TABLE turnier_logo (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    art VARCHAR(20) NOT NULL,
    mime VARCHAR(50) NOT NULL,
    daten {{GROSSTEXT}} NOT NULL,
    breite INTEGER NOT NULL,
    hoehe INTEGER NOT NULL,
    geaendert_am VARCHAR(19) NOT NULL,
    UNIQUE (turnier_id, art),
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE
){{OPTS}};
