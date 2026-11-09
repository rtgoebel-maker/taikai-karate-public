-- Konten und Rollen (Schritt 5, KONZEPT-ONLINE-OFFLINE.md Kap. 3):
-- E-Mail als Benutzername, Vor-/Nachname getrennt, Telefon optional, Dojo-Zuordnung (Coach),
-- globale Administration und Turnierleitung je Turnier.
-- Die alte Spalte login (max. 50 Zeichen) bleibt als interner Schlüssel; angemeldet wird mit email.
-- Bestehende Konten behalten ihren Benutzernamen als email, bis eine echte E-Mail eingetragen wird.
ALTER TABLE benutzer ADD COLUMN email VARCHAR(190) NULL;
ALTER TABLE benutzer ADD COLUMN vorname VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE benutzer ADD COLUMN nachname VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE benutzer ADD COLUMN telefon VARCHAR(50) NULL;
-- Fremdschlüssel nur unter SQLite wirksam; beim Löschen/Zusammenführen von Vereinen setzt der Code die Zuordnung um
ALTER TABLE benutzer ADD COLUMN verein_id {{FK}} NULL REFERENCES verein(id) ON DELETE SET NULL;
ALTER TABLE benutzer ADD COLUMN ist_admin INTEGER NOT NULL DEFAULT 0;
-- Bisher gab es nur die Rolle „admin“ für alle Konten
UPDATE benutzer SET ist_admin = 1, email = LOWER(login), nachname = name;
CREATE UNIQUE INDEX idx_benutzer_email ON benutzer(email);

CREATE TABLE turnier_rolle (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    benutzer_id {{FK}} NOT NULL,
    rolle VARCHAR(20) NOT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    erstellt_von VARCHAR(100) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
){{OPTS}};
CREATE UNIQUE INDEX idx_turnier_rolle ON turnier_rolle(turnier_id, benutzer_id, rolle);
CREATE INDEX idx_turnier_rolle_benutzer ON turnier_rolle(benutzer_id);
