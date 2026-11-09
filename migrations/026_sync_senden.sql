-- Schritt 9e: Halle sendet den Gesamtstand. letzter_versuch drosselt das Senden (auch bei mehreren Auslösern),
-- letzter_fehler zeigt der Turnierleitung, warum gerade nichts ankommt.
ALTER TABLE sync_auftrag ADD COLUMN letzter_versuch VARCHAR(19) NULL;
ALTER TABLE sync_auftrag ADD COLUMN letzter_fehler VARCHAR(300) NULL;
