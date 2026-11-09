-- Schritte 9g/9h am Hallen-Laptop: Abfrage neuer Korrekturanträge (gedrosselt), Rückgabe an Online und
-- „Führung entzogen“ (Online hat die Übergabe zurückgenommen oder neu vergeben).
ALTER TABLE sync_auftrag ADD COLUMN letzte_abfrage VARCHAR(19) NULL;
ALTER TABLE sync_auftrag ADD COLUMN zurueckgegeben_am VARCHAR(19) NULL;
ALTER TABLE sync_auftrag ADD COLUMN entzogen_am VARCHAR(19) NULL;
