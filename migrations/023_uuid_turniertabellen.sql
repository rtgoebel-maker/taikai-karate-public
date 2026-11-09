-- Schritt 9a: UUID in allen Turniertabellen, damit Halle und Online (und Sicherungen) denselben Datensatz eindeutig
-- erkennen. Bestand wird vom Migrator nachgetragen (Database::MIT_UUID), neue Zeilen erhalten die UUID in Database::insert.
-- NULL ist erlaubt, damit die Spalte auch in SQLite nachträglich angelegt werden kann; der eindeutige Index lässt mehrere NULL zu.
ALTER TABLE tatami ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_tatami_uuid ON tatami(uuid);
ALTER TABLE teilnahme ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_teilnahme_uuid ON teilnahme(uuid);
ALTER TABLE team ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_team_uuid ON team(uuid);
ALTER TABLE team_mitglied ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_team_mitglied_uuid ON team_mitglied(uuid);
ALTER TABLE klasse_regel ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_klasse_regel_uuid ON klasse_regel(uuid);
ALTER TABLE start ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_start_uuid ON start(uuid);
ALTER TABLE zusammenlegung ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_zusammenlegung_uuid ON zusammenlegung(uuid);
ALTER TABLE auslosung ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_auslosung_uuid ON auslosung(uuid);
ALTER TABLE phase ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_phase_uuid ON phase(uuid);
ALTER TABLE begegnung ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_begegnung_uuid ON begegnung(uuid);
ALTER TABLE auftritt ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_auftritt_uuid ON auftritt(uuid);
ALTER TABLE platzierung ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_platzierung_uuid ON platzierung(uuid);
ALTER TABLE tatami_nachricht ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_tatami_nachricht_uuid ON tatami_nachricht(uuid);
ALTER TABLE turnier_logo ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_turnier_logo_uuid ON turnier_logo(uuid);
ALTER TABLE korrekturantrag ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_korrekturantrag_uuid ON korrekturantrag(uuid);
ALTER TABLE audit_log ADD COLUMN uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX idx_audit_log_uuid ON audit_log(uuid);
