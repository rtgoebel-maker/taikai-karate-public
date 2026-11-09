-- Anhänge für Mails der Warteschlange (z. B. Screenshot einer Feedback-Meldung), JSON-Liste {name, mime, daten (Base64)}.
ALTER TABLE mail_ausgang ADD COLUMN anhaenge {{GROSSTEXT}} NULL;
