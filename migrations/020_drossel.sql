-- Drosselung öffentlicher bzw. häufiger Aktionen (OWASP A06/API4): Online-Anmeldung und
-- Feedback je Absender höchstens N-mal pro Zeitfenster (Kern\Drossel).
CREATE TABLE drossel (
    schluessel VARCHAR(190) NOT NULL PRIMARY KEY,
    fenster_start VARCHAR(19) NOT NULL,
    anzahl INTEGER NOT NULL DEFAULT 0
){{OPTS}};
