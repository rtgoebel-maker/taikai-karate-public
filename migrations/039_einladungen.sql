-- Freiwillige Einwilligung (Art. 6 Abs. 1 lit. a DSGVO) aus der Online-Anmeldung: per E-Mail zu künftigen Turnieren
-- einladen. Installationsweit (nicht Teil eines Turniers, daher weder im Turnierpaket noch in der Pseudonymisierung).
-- Abmeldelink = HMAC mit dem Schlüssel der Installation über ID und Adresse; Widerruf löscht die Zeile.
CREATE TABLE einladung (
    id {{PK}},
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    dojo VARCHAR(200) NOT NULL,
    herkunft VARCHAR(250) NOT NULL,
    eingewilligt_am VARCHAR(19) NOT NULL,
    fassung VARCHAR(20) NOT NULL,
    zuletzt_eingeladen_am VARCHAR(19) NULL
){{OPTS}};
