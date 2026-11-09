-- Schritt 9h: UUIDs der Athleten, die mit der Übergabe auf den Laptop kamen (JSON-Liste). Bei der Rückgabe gehen
-- Geburtsdatum und Ausweisnummer nur für danach in der Halle angelegte Athleten an Online (Entscheidung 30.10.2026).
ALTER TABLE sync_auftrag ADD COLUMN athleten_bei_uebergabe {{GROSSTEXT}} NULL;
