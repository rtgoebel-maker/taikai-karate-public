-- Bearbeitungsstand einer Online-Anmeldung: Zahlungsaufforderung (versioniert – jede Änderung des Betrags
-- ergibt eine neue Version) und Zahlungseingang (von der Wettkampfleitung erfasst).
ALTER TABLE anmeldung ADD COLUMN aufforderung_version INTEGER NOT NULL DEFAULT 0;
ALTER TABLE anmeldung ADD COLUMN aufforderung_cent INTEGER NOT NULL DEFAULT 0;
ALTER TABLE anmeldung ADD COLUMN aufforderung_am VARCHAR(19) NULL;
ALTER TABLE anmeldung ADD COLUMN bezahlt_cent INTEGER NOT NULL DEFAULT 0;
ALTER TABLE anmeldung ADD COLUMN bezahlt_am VARCHAR(10) NULL;
ALTER TABLE anmeldung ADD COLUMN zahlung_notiz VARCHAR(200) NULL;
ALTER TABLE anmeldung ADD COLUMN zahlung_von VARCHAR(100) NULL;
-- bestehende Anmeldungen: erste Zahlungsaufforderung über den aktuellen Betrag
UPDATE anmeldung SET aufforderung_version = 1, aufforderung_cent = preis_cent, aufforderung_am = COALESCE(geaendert_am, eingereicht_am)
 WHERE preis_cent > 0;
