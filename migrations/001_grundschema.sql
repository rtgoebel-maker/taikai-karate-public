-- Grundschema der Wettkampfsoftware (portabel: SQLite / MySQL)
-- Datumswerte als 'YYYY-MM-DD', Zeitstempel als 'YYYY-MM-DD HH:MM:SS' (lokale Zeit)

CREATE TABLE benutzer (
    id {{PK}},
    login VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    passwort_hash VARCHAR(255) NOT NULL,
    rolle VARCHAR(20) NOT NULL DEFAULT 'admin',
    aktiv INTEGER NOT NULL DEFAULT 1,
    erstellt_am VARCHAR(19) NOT NULL
){{OPTS}};

CREATE TABLE turnier (
    id {{PK}},
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    datum VARCHAR(10) NOT NULL,
    ort VARCHAR(200) NULL,
    halle VARCHAR(200) NULL,
    anzahl_tatamis INTEGER NOT NULL DEFAULT 1,
    min_graduierung INTEGER NULL,
    meldeschluss VARCHAR(10) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planung',
    revision INTEGER NOT NULL DEFAULT 0,
    leitung_pin_hash VARCHAR(255) NULL,
    oeffentlich INTEGER NOT NULL DEFAULT 1,
    einstellungen TEXT NULL,
    erstellt_am VARCHAR(19) NOT NULL
){{OPTS}};

CREATE TABLE tatami (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    nummer INTEGER NOT NULL,
    bezeichnung VARCHAR(100) NOT NULL,
    pin_hash VARCHAR(255) NULL,
    stream_url VARCHAR(500) NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE verein (
    id {{PK}},
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    kurzname VARCHAR(50) NULL,
    ort VARCHAR(100) NULL
){{OPTS}};

CREATE TABLE athlet (
    id {{PK}},
    uuid VARCHAR(36) NOT NULL UNIQUE,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geburtsdatum VARCHAR(10) NULL,
    geschlecht VARCHAR(1) NOT NULL,
    graduierung INTEGER NOT NULL,
    verein_id {{FK}} NULL,
    ausweis_nr VARCHAR(50) NULL,
    FOREIGN KEY (verein_id) REFERENCES verein(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE teilnahme (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    athlet_id {{FK}} NOT NULL,
    startnummer INTEGER NULL,
    alter_manuell INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'gemeldet',
    ausweis_geprueft INTEGER NOT NULL DEFAULT 0,
    nachmeldung INTEGER NOT NULL DEFAULT 0,
    vermerk VARCHAR(500) NULL,
    UNIQUE (turnier_id, athlet_id),
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (athlet_id) REFERENCES athlet(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE team (
    id {{PK}},
    turnier_id {{FK}} NOT NULL,
    verein_id {{FK}} NULL,
    name VARCHAR(200) NOT NULL,
    disziplin VARCHAR(20) NOT NULL,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (verein_id) REFERENCES verein(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE team_mitglied (
    id {{PK}},
    team_id {{FK}} NOT NULL,
    teilnahme_id {{FK}} NOT NULL,
    verein_id {{FK}} NULL,
    rolle VARCHAR(10) NOT NULL DEFAULT 'stamm',
    FOREIGN KEY (team_id) REFERENCES team(id) ON DELETE CASCADE,
    FOREIGN KEY (teilnahme_id) REFERENCES teilnahme(id) ON DELETE CASCADE,
    FOREIGN KEY (verein_id) REFERENCES verein(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE klasse (
    id {{PK}},
    uuid VARCHAR(36) NOT NULL UNIQUE,
    turnier_id {{FK}} NOT NULL,
    code VARCHAR(20) NOT NULL,
    bezeichnung VARCHAR(200) NOT NULL,
    disziplin VARCHAR(20) NOT NULL,
    kumite_form VARCHAR(20) NULL,
    geschlecht VARCHAR(5) NOT NULL,
    alter_min INTEGER NULL,
    alter_max INTEGER NULL,
    grad_min INTEGER NULL,
    grad_max INTEGER NULL,
    erlaubte_kata TEXT NULL,
    exklusiv_gruppe VARCHAR(20) NULL,
    modus VARCHAR(20) NOT NULL DEFAULT 'auto',
    anzahl_pools INTEGER NOT NULL DEFAULT 1,
    aufsteiger_pro_pool INTEGER NOT NULL DEFAULT 2,
    finale_art VARCHAR(10) NOT NULL DEFAULT 'ko',
    finalisten INTEGER NOT NULL DEFAULT 4,
    wertung VARCHAR(10) NOT NULL DEFAULT 'flaggen',
    wertung_finale VARCHAR(10) NULL,
    kampfrichter INTEGER NOT NULL DEFAULT 5,
    dojo_trennung INTEGER NOT NULL DEFAULT 1,
    gleiche_flaeche INTEGER NOT NULL DEFAULT 0,
    optionen TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    zusammengelegt_in {{FK}} NULL,
    sortierung INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (turnier_id) REFERENCES turnier(id) ON DELETE CASCADE,
    FOREIGN KEY (zusammengelegt_in) REFERENCES klasse(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE klasse_regel (
    id {{PK}},
    klasse_id {{FK}} NOT NULL,
    ab_runde VARCHAR(20) NOT NULL,
    kumite_form VARCHAR(20) NULL,
    erlaubte_kata TEXT NULL,
    wertung VARCHAR(10) NULL,
    FOREIGN KEY (klasse_id) REFERENCES klasse(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE start (
    id {{PK}},
    klasse_id {{FK}} NOT NULL,
    teilnahme_id {{FK}} NULL,
    team_id {{FK}} NULL,
    herkunft_klasse_id {{FK}} NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'gemeldet',
    manuell INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (klasse_id) REFERENCES klasse(id) ON DELETE CASCADE,
    FOREIGN KEY (teilnahme_id) REFERENCES teilnahme(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES team(id) ON DELETE CASCADE,
    FOREIGN KEY (herkunft_klasse_id) REFERENCES klasse(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE zusammenlegung (
    id {{PK}},
    ziel_klasse_id {{FK}} NOT NULL,
    quell_klasse_id {{FK}} NOT NULL,
    grund TEXT NULL,
    zustimmung TEXT NULL,
    erstellt_am VARCHAR(19) NOT NULL,
    erstellt_von VARCHAR(100) NULL,
    aufgehoben_am VARCHAR(19) NULL,
    FOREIGN KEY (ziel_klasse_id) REFERENCES klasse(id) ON DELETE CASCADE,
    FOREIGN KEY (quell_klasse_id) REFERENCES klasse(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE auslosung (
    id {{PK}},
    klasse_id {{FK}} NOT NULL,
    seed VARCHAR(64) NOT NULL,
    algorithmus VARCHAR(20) NOT NULL,
    optionen TEXT NULL,
    protokoll TEXT NULL,
    aktiv INTEGER NOT NULL DEFAULT 1,
    erstellt_am VARCHAR(19) NOT NULL,
    erstellt_von VARCHAR(100) NULL,
    FOREIGN KEY (klasse_id) REFERENCES klasse(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE phase (
    id {{PK}},
    klasse_id {{FK}} NOT NULL,
    typ VARCHAR(20) NOT NULL,
    nummer INTEGER NOT NULL DEFAULT 1,
    bezeichnung VARCHAR(50) NOT NULL,
    wertung VARCHAR(10) NOT NULL,
    aufsteiger INTEGER NULL,
    tatami_id {{FK}} NULL,
    reihenfolge INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'wartet',
    FOREIGN KEY (klasse_id) REFERENCES klasse(id) ON DELETE CASCADE,
    FOREIGN KEY (tatami_id) REFERENCES tatami(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE begegnung (
    id {{PK}},
    phase_id {{FK}} NOT NULL,
    runde INTEGER NOT NULL,
    position INTEGER NOT NULL,
    art VARCHAR(10) NOT NULL DEFAULT 'ko',
    aka_start_id {{FK}} NULL,
    shiro_start_id {{FK}} NULL,
    aka_quelle VARCHAR(30) NULL,
    shiro_quelle VARCHAR(30) NULL,
    sieger_start_id {{FK}} NULL,
    entscheidung VARCHAR(20) NULL,
    kata_aka VARCHAR(60) NULL,
    kata_shiro VARCHAR(60) NULL,
    wertung TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    version INTEGER NOT NULL DEFAULT 0,
    beendet_am VARCHAR(19) NULL,
    erfasst_von VARCHAR(100) NULL,
    FOREIGN KEY (phase_id) REFERENCES phase(id) ON DELETE CASCADE,
    FOREIGN KEY (aka_start_id) REFERENCES start(id) ON DELETE SET NULL,
    FOREIGN KEY (shiro_start_id) REFERENCES start(id) ON DELETE SET NULL,
    FOREIGN KEY (sieger_start_id) REFERENCES start(id) ON DELETE SET NULL
){{OPTS}};

CREATE TABLE auftritt (
    id {{PK}},
    phase_id {{FK}} NOT NULL,
    start_id {{FK}} NOT NULL,
    reihenfolge INTEGER NOT NULL,
    kata VARCHAR(60) NULL,
    wertung TEXT NULL,
    gesamt DECIMAL(8,2) NULL,
    nebenwertung DECIMAL(8,2) NULL,
    stichentscheid INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    version INTEGER NOT NULL DEFAULT 0,
    beendet_am VARCHAR(19) NULL,
    erfasst_von VARCHAR(100) NULL,
    FOREIGN KEY (phase_id) REFERENCES phase(id) ON DELETE CASCADE,
    FOREIGN KEY (start_id) REFERENCES start(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE platzierung (
    id {{PK}},
    klasse_id {{FK}} NOT NULL,
    platz INTEGER NOT NULL,
    start_id {{FK}} NOT NULL,
    bestaetigt_am VARCHAR(19) NULL,
    bestaetigt_von VARCHAR(100) NULL,
    FOREIGN KEY (klasse_id) REFERENCES klasse(id) ON DELETE CASCADE,
    FOREIGN KEY (start_id) REFERENCES start(id) ON DELETE CASCADE
){{OPTS}};

CREATE TABLE audit_log (
    id {{PK}},
    zeit VARCHAR(19) NOT NULL,
    benutzer VARCHAR(100) NULL,
    turnier_id {{FK}} NULL,
    entitaet VARCHAR(30) NOT NULL,
    entitaet_id INTEGER NULL,
    aktion VARCHAR(30) NOT NULL,
    daten TEXT NULL
){{OPTS}};

CREATE INDEX idx_tatami_turnier ON tatami(turnier_id);
CREATE INDEX idx_teilnahme_turnier ON teilnahme(turnier_id);
CREATE INDEX idx_klasse_turnier ON klasse(turnier_id);
CREATE INDEX idx_start_klasse ON start(klasse_id);
CREATE INDEX idx_start_teilnahme ON start(teilnahme_id);
CREATE INDEX idx_phase_klasse ON phase(klasse_id);
CREATE INDEX idx_phase_tatami ON phase(tatami_id);
CREATE INDEX idx_begegnung_phase ON begegnung(phase_id);
CREATE INDEX idx_auftritt_phase ON auftritt(phase_id);
CREATE INDEX idx_platzierung_klasse ON platzierung(klasse_id);
CREATE INDEX idx_audit_turnier ON audit_log(turnier_id)
