-- Anmeldeformular nicht mehr unter der fortlaufenden Turnier-Nummer (erratbar), sondern unter einem Zufallsschlüssel
-- je Turnier (/melden/{schluessel}); er entsteht bei der ersten Verwendung und lässt sich erneuern.
ALTER TABLE turnier ADD COLUMN anmelde_schluessel VARCHAR(32) NULL;
CREATE UNIQUE INDEX idx_turnier_anmelde_schluessel ON turnier (anmelde_schluessel);
