-- Ein laufender Kampf kann unterbrochen werden (z. B. Verletzung); er bleibt der
-- aktuelle Kampf der Tatami, bis er fortgesetzt oder entschieden wird.
ALTER TABLE begegnung ADD COLUMN unterbrochen_am VARCHAR(19) NULL
