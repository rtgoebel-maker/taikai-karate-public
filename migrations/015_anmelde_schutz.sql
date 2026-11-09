-- Schutz vor Durchprobieren von Passwort und PINs (OWASP A07): Fehlversuche je
-- Ziel (Benutzer, Tatami, Leitungs-PIN) und IP; nach mehreren Fehlern zeitweise gesperrt.
CREATE TABLE anmelde_schutz (
    schluessel VARCHAR(190) NOT NULL PRIMARY KEY,
    versuche INTEGER NOT NULL DEFAULT 0,
    sperren INTEGER NOT NULL DEFAULT 0,
    gesperrt_bis VARCHAR(19) NULL,
    geaendert_am VARCHAR(19) NOT NULL
){{OPTS}}
