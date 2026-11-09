-- Turnierphasen statt des bisher ungenutzten Status „planung“:
-- vorbereitung → meldephase → laeuft → abgeschlossen.
-- Bestehende Turniere: schon gespielte → läuft, offene Online-Anmeldung → Meldephase.
UPDATE turnier SET status = 'laeuft' WHERE status = 'planung' AND id IN (SELECT turnier_id FROM klasse WHERE status IN ('laeuft', 'beendet', 'bestaetigt'));
UPDATE turnier SET status = 'meldephase' WHERE status = 'planung' AND einstellungen LIKE '%"offen":true%';
UPDATE turnier SET status = 'vorbereitung' WHERE status = 'planung';
