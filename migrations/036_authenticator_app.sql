-- Zweiter Faktor per Authenticator-App (TOTP). Das Geheimnis liegt nur verschlüsselt vor (Tresor, Schlüssel in data/),
-- totp_schritt merkt sich den zuletzt genutzten Zeitschritt (jeder Code gilt nur einmal).
ALTER TABLE benutzer ADD COLUMN totp_geheimnis TEXT NULL;
ALTER TABLE benutzer ADD COLUMN totp_seit VARCHAR(19) NULL;
ALTER TABLE benutzer ADD COLUMN totp_schritt INTEGER NOT NULL DEFAULT 0;

-- Wiederherstellungscodes (Handy verloren): je Konto 10 Stück zum einmaligen Gebrauch, nur der SHA-256.
CREATE TABLE konto_wiederherstellung (
    id {{PK}},
    benutzer_id {{FK}} NOT NULL,
    code_hash VARCHAR(64) NOT NULL,
    verbraucht_am VARCHAR(19) NULL,
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
){{OPTS}};
CREATE INDEX idx_konto_wiederherstellung ON konto_wiederherstellung (benutzer_id);
