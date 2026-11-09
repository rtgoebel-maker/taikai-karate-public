-- Mindestanzahl Starter, damit eine Klasse stattfindet (immer mindestens 2:
-- mit nur einem Starter nimmt eine Klasse nicht am Turnier teil).
ALTER TABLE klasse ADD COLUMN min_starter INTEGER NOT NULL DEFAULT 2
