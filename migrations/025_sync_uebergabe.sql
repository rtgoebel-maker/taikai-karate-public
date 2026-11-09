-- Schritt 9d: Übergabe eines Turniers an einen Hallen-Laptop. Beide Tabellen enthalten den Sync-Schlüssel und
-- gehören deshalb nicht in Turnier-Sicherungen (Turnierpaket).

-- Online: Übergaben eines Turniers. Der Übergabe-Code wird nur als Hash gespeichert (Anzeige einmalig).
CREATE TABLE sync_uebergabe (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    nr INTEGER NOT NULL,
    code_hash VARCHAR(64) NOT NULL UNIQUE,
    schluessel VARCHAR(64) NOT NULL,
    halle_instanz VARCHAR(36) NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    erstellt_von VARCHAR(100) NOT NULL,
    abgeholt_am VARCHAR(19) NULL,
    beendet_am VARCHAR(19) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE
){{OPTS}};
CREATE UNIQUE INDEX idx_sync_uebergabe_nr ON sync_uebergabe(turnier_id, nr);

-- Halle: Verbindung zum Online-Server je übernommenem Turnier.
CREATE TABLE sync_auftrag (
    id {{PK}},
    turnier_id {{FK}} NOT NULL UNIQUE,
    online_url VARCHAR(300) NOT NULL,
    uebergabe_nr INTEGER NOT NULL,
    schluessel VARCHAR(64) NOT NULL,
    eingespielt_am VARCHAR(19) NOT NULL,
    quelle VARCHAR(10) NOT NULL,
    letzter_kontakt VARCHAR(19) NULL,
    gesendete_revision INTEGER NULL,
    bestaetigte_revision INTEGER NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE
){{OPTS}}
