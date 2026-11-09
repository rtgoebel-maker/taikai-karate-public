-- Die Tischbesatzung startet den Ausruf einer Gruppe; erst dann sieht der
-- Ausrufer deren Starter (die laufende Gruppe ist immer sichtbar).
ALTER TABLE klasse ADD COLUMN ausruf_freigabe_am VARCHAR(19) NULL
