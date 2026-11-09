-- System → Gesperrte Anmeldungen: Ziel (z. B. „login:mail@example.de“) und IP zusätzlich im Klartext, damit die
-- Administration sieht, wer gesperrt ist, und Sperren aufheben kann. Einträge ohne Aktivität verschwinden nach
-- einem Tag (AnmeldeSchutz::AUFBEWAHRUNG_SEKUNDEN) – IP-Adressen nur so lange wie nötig (DSGVO Art. 5 Abs. 1 e).
ALTER TABLE anmelde_schutz ADD COLUMN ziel VARCHAR(190) NULL;
ALTER TABLE anmelde_schutz ADD COLUMN ip VARCHAR(45) NULL;
