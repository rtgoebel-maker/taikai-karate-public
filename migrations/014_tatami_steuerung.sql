-- Tatami-Steuerung am Turniertag: Sperre (keine neuen Gruppen) und Nachrichten
-- der Wettkampfleitung an die Tischbesatzung (bleiben bis zur Bestätigung stehen).
ALTER TABLE tatami ADD COLUMN gesperrt_am VARCHAR(19) NULL;
ALTER TABLE tatami ADD COLUMN sperr_grund VARCHAR(200) NULL;

CREATE TABLE tatami_nachricht (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    tatami_id {{FK}} NOT NULL,
    text VARCHAR(500) NOT NULL,
    von VARCHAR(100) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    bestaetigt_am VARCHAR(19) NULL,
    bestaetigt_von VARCHAR(100) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (tatami_id) REFERENCES tatami(id) ON DELETE CASCADE
){{OPTS}};
CREATE INDEX idx_nachricht_tatami ON tatami_nachricht(tatami_id, bestaetigt_am)
