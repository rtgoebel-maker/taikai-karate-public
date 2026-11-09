-- Coaching-Portal: Korrekturanträge eines Dojos zu seinen Teilnehmern (Name, Geburtsdatum,
-- Graduierung, Klasse …). Die Turnierleitung nimmt sie an oder lehnt sie mit Antwort ab.
CREATE TABLE korrekturantrag (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    verein_id {{FK}} NOT NULL,
    teilnahme_id {{FK}} NULL,
    art VARCHAR(20) NOT NULL,
    neu VARCHAR(200) NOT NULL,
    begruendung VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    antwort VARCHAR(500) NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    erstellt_von VARCHAR(100) NOT NULL,
    bearbeitet_am VARCHAR(19) NULL,
    bearbeitet_von VARCHAR(100) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (verein_id) REFERENCES verein(id) ON DELETE CASCADE,
    FOREIGN KEY (teilnahme_id) REFERENCES teilnahme(id) ON DELETE CASCADE
){{OPTS}};
CREATE INDEX idx_korrekturantrag_turnier ON korrekturantrag(turnier_id, status)
