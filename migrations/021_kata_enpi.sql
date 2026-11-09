-- Kata-Name wie in der DJKB-Wettkampfordnung (C 2.5): „Enpi“ statt „Empi“ in den erlaubten Kata.
-- Bereits vorgetragene Kata (Ergebnisse) bleiben unverändert.
UPDATE klasse SET erlaubte_kata = REPLACE(erlaubte_kata, 'Empi', 'Enpi') WHERE erlaubte_kata LIKE '%Empi%';
UPDATE klasse_regel SET erlaubte_kata = REPLACE(erlaubte_kata, 'Empi', 'Enpi') WHERE erlaubte_kata LIKE '%Empi%';
UPDATE begegnung SET kata_liste = REPLACE(kata_liste, 'Empi', 'Enpi') WHERE kata_liste LIKE '%Empi%';
UPDATE auftritt SET kata_liste = REPLACE(kata_liste, 'Empi', 'Enpi') WHERE kata_liste LIKE '%Empi%'
