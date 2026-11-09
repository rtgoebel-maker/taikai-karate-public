<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Mail;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Versand über SMTP mit PHPMailer (Zugangsdaten eines Postfachs in config.php, Abschnitt 'mail').
 * Nur reiner Text; Empfänger und Betreff prüft bzw. kodiert PHPMailer (kein Header-Einschleusen, OWASP A03).
 */
final class SmtpTransport implements Transport
{
    /** @param array<string, mixed> $c host, port, verschluesselung (tls|ssl|''), benutzer, passwort, absender, absender_name, antwort_an */
    public function __construct(private readonly array $c) {}

    public function senden(string $empfaenger, string $betreff, string $text, array $anhaenge = []): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($this->c['host'] ?? '');
        $mail->Port = (int) ($this->c['port'] ?? 587);
        $verschluesselung = (string) ($this->c['verschluesselung'] ?? 'tls');
        $mail->SMTPSecure = match ($verschluesselung) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            '' => '',
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
        $mail->SMTPAutoTLS = $verschluesselung !== '';
        if (($this->c['benutzer'] ?? '') !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = (string) $this->c['benutzer'];
            $mail->Password = (string) ($this->c['passwort'] ?? '');
        }
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->setFrom((string) ($this->c['absender'] ?? ''), (string) ($this->c['absender_name'] ?? 'Taikai – Karate'));
        if (($this->c['antwort_an'] ?? '') !== '') {
            $mail->addReplyTo((string) $this->c['antwort_an']);
        }
        $mail->addAddress($empfaenger);
        $mail->Subject = $betreff;
        $mail->isHTML(false);
        $mail->Body = $text;
        foreach ($anhaenge as $a) {
            $mail->addStringAttachment($a['daten'], $a['name'], PHPMailer::ENCODING_BASE64, $a['mime']);
        }
        $mail->send();
    }
}
