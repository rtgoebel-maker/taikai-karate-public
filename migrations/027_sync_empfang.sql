-- Schritt 9f: Online empfängt den Hallenstand.
-- Jede Nonce wird nur einmal angenommen (Schutz gegen wiederholte Sendungen); ältere als das Zeitfenster
-- werden beim Empfang gelöscht.
CREATE TABLE sync_nonce (
    nonce VARCHAR(32) NOT NULL PRIMARY KEY,
    zeit VARCHAR(19) NOT NULL
){{OPTS}};
-- zuletzt übernommene Revision der Halle (ältere oder gleiche Sendungen ändern nichts)
ALTER TABLE sync_uebergabe ADD COLUMN uebernommene_revision INTEGER NULL;
ALTER TABLE sync_uebergabe ADD COLUMN letzter_empfang VARCHAR(19) NULL;
-- Zeitpunkt des zuletzt übernommenen Hallenstands (Hinweis „nur lesend … Stand HH:MM“)
ALTER TABLE turnier ADD COLUMN fuehrung_stand VARCHAR(19) NULL;
