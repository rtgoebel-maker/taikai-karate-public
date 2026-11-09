-- Athleten ohne Teilnahme an einem Turnier entfernen: Beim Löschen eines Turniers blieben sie
-- bisher stehen (Verein-Übersicht zählte sie weiter). Ab jetzt löscht das Turnier sie selbst mit.
DELETE FROM athlet WHERE id NOT IN (SELECT athlet_id FROM teilnahme);
