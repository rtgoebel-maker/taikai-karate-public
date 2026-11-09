-- Schritt 9c: welche Installation ein Turnier führt (schreiben darf). Leer = keine Übergabe, die Installation
-- mit dem Turnier führt selbst. Nach der Übergabe an die Halle steht hier online die Instanz-ID der Halle
-- (online nur lesend), in der Halle ihre eigene; nach der Rückgabe in der Halle die von Online (Halle nur lesend).
ALTER TABLE turnier ADD COLUMN fuehrung_instanz VARCHAR(36) NULL;
ALTER TABLE turnier ADD COLUMN fuehrung_name VARCHAR(100) NULL;
ALTER TABLE turnier ADD COLUMN fuehrung_seit VARCHAR(19) NULL;
ALTER TABLE turnier ADD COLUMN uebergabe_nr INTEGER NOT NULL DEFAULT 0;
