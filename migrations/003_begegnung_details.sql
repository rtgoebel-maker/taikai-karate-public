-- Rundenabhängige Angaben werden beim Auslosen je Begegnung festgehalten
-- (z. B. Kata-Finale frei, „letzte 8: Jiyu-Kumite“), damit Anzeige, Druck
-- und Eingabe dieselben Regeln verwenden.
ALTER TABLE phase ADD COLUMN baum_groesse INTEGER NULL;
ALTER TABLE phase ADD COLUMN starter TEXT NULL;
ALTER TABLE begegnung ADD COLUMN wertungssystem VARCHAR(10) NULL;
ALTER TABLE begegnung ADD COLUMN kumite_form VARCHAR(20) NULL;
ALTER TABLE begegnung ADD COLUMN kata_liste TEXT NULL;
ALTER TABLE auftritt ADD COLUMN kata_liste TEXT NULL;
-- Stichentscheid der Wettkampfleitung bei Gleichstand in „jeder gegen jeden“
ALTER TABLE start ADD COLUMN stichentscheid INTEGER NULL
