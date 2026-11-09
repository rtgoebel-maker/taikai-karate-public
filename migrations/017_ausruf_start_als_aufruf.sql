-- Ausruf-Start am Tisch gilt jetzt zugleich als Gruppen-Aufruf: bereits gestartete, noch nicht
-- beendete Gruppen nachziehen, damit der Ausrufer nicht noch einmal „Gruppe aufrufen“ tippen muss.
UPDATE klasse SET ausruf_am = ausruf_freigabe_am
 WHERE ausruf_am IS NULL AND ausruf_freigabe_am IS NOT NULL AND status NOT IN ('beendet', 'bestaetigt');
