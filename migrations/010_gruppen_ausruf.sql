-- Ausruf einer ganzen Gruppe (Klasse): ab diesem Zeitpunkt stehen alle ihre
-- Starter auf der Tatami-Übersicht unter „Bitte zur Tatami“, bis sie „da“ sind.
ALTER TABLE klasse ADD COLUMN ausruf_am VARCHAR(19) NULL
