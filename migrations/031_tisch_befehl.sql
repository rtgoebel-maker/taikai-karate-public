-- Bereits ausgeführte Befehle vom Tablet (Ergebnis, Start, Ausruf …) mit der gesendeten Antwort.
-- Kommt dieselbe Befehls-ID noch einmal (Antwort ging im WLAN verloren, automatische Wiederholung),
-- wird die gespeicherte Antwort geliefert statt den Befehl erneut auszuführen (Kern\Http\Middleware\EinmaligeBefehle).
CREATE TABLE tisch_befehl (
    befehl_id VARCHAR(64) NOT NULL PRIMARY KEY,
    status INTEGER NOT NULL,
    antwort TEXT NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL
){{OPTS}};
CREATE INDEX idx_tisch_befehl_erstellt ON tisch_befehl (erstellt_am);
