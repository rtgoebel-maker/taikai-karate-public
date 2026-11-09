-- Ausruf je Begegnung bzw. Vortrag: Der Ausrufer bestätigt je Seite „da“
-- oder „fehlt“ und schließt den Ausruf ab; danach kann der Kampf beginnen.
ALTER TABLE begegnung ADD COLUMN aufruf_aka VARCHAR(10) NULL;
ALTER TABLE begegnung ADD COLUMN aufruf_shiro VARCHAR(10) NULL;
ALTER TABLE begegnung ADD COLUMN aufruf_am VARCHAR(19) NULL;
ALTER TABLE auftritt ADD COLUMN aufruf_starter VARCHAR(10) NULL;
ALTER TABLE auftritt ADD COLUMN aufruf_am VARCHAR(19) NULL
