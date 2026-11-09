-- Die Tischbesatzung bestätigt am Ende, dass eine Gruppe (Klasse) auf ihrer
-- Tatami abgeschlossen ist; erst dann lädt das Tablet die nächste Gruppe.
ALTER TABLE klasse ADD COLUMN tisch_abschluss_am VARCHAR(19) NULL;
ALTER TABLE klasse ADD COLUMN tisch_abschluss_von VARCHAR(100) NULL;
-- bereits fertige Gruppen gelten als abgeschlossen
UPDATE klasse SET tisch_abschluss_am = '2000-01-01 00:00:00' WHERE status IN ('beendet', 'bestaetigt')
