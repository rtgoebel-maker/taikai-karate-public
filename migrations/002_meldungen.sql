-- Gemeldete Einzeldisziplinen an der Teilnahme (z. B. 'kata,kumite').
-- Die Starts in Klassen werden daraus durch die Zuordnung erzeugt.
ALTER TABLE teilnahme ADD COLUMN disziplinen VARCHAR(100) NULL
