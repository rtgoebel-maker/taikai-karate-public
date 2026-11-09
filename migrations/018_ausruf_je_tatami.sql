-- Ausruf-Start und Gruppen-Aufruf je Phase statt je Klasse: Pools einer Klasse laufen auf
-- verschiedenen Tatamis, jede Tischbesatzung startet den Ausruf ihres Pools selbst.
ALTER TABLE phase ADD COLUMN ausruf_am VARCHAR(19) NULL;
ALTER TABLE phase ADD COLUMN ausruf_freigabe_am VARCHAR(19) NULL;
UPDATE phase SET ausruf_am = (SELECT k.ausruf_am FROM klasse k WHERE k.id = phase.klasse_id),
                 ausruf_freigabe_am = (SELECT k.ausruf_freigabe_am FROM klasse k WHERE k.id = phase.klasse_id)
 WHERE status <> 'beendet' AND tatami_id IS NOT NULL
