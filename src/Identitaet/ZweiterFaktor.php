<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Totp;

/**
 * Zweiter Faktor bei der Anmeldung (OWASP A07): Nach richtiger E-Mail und richtigem Passwort ist man erst „halb“ angemeldet –
 * dieser Zustand liegt in der (serverseitigen) Sitzung. Arten:
 *
 *  - 'mail': 6-stelliger Code an die hinterlegte E-Mail-Adresse (in der Sitzung nur sein Hash)
 *  - 'app': Code aus der Authenticator-App (oder ein Wiederherstellungscode), siehe AuthenticatorApp
 *  - 'einrichten': Pflicht, aber weder App noch E-Mail möglich – die App wird jetzt eingerichtet
 *
 * Der Zustand gilt 10 Minuten (Einrichten: 20) und verträgt höchstens 5 Fehlversuche – danach muss das Passwort erneut
 * eingegeben werden (das wiederum begrenzt der AnmeldeSchutz).
 */
final class ZweiterFaktor
{
    public const GUELTIG_SEKUNDEN = 600;
    public const EINRICHTEN_SEKUNDEN = 1200;
    public const MAX_VERSUCHE = 5;
    private const SCHLUESSEL = 'zweiter_faktor';

    /** Neuen E-Mail-Code für ein Konto anlegen (ersetzt einen offenen). @return string Code im Klartext – nur für die Mail */
    public static function starten(int $benutzerId, string $zurueck, ?int $jetzt = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $salz = bin2hex(random_bytes(16));
        $_SESSION[self::SCHLUESSEL] = [
            'art' => 'mail',
            'benutzer' => $benutzerId,
            'hash' => hash('sha256', $salz . $code),
            'salz' => $salz,
            'ablauf' => ($jetzt ?? time()) + self::GUELTIG_SEKUNDEN,
            'versuche' => 0,
            'zurueck' => $zurueck,
        ];
        return $code;
    }

    /** Auf Code aus der App warten ('app') bzw. die App jetzt einrichten lassen ('einrichten'). */
    public static function warten(int $benutzerId, string $zurueck, string $art, ?int $jetzt = null): void
    {
        $_SESSION[self::SCHLUESSEL] = [
            'art' => $art,
            'benutzer' => $benutzerId,
            'ablauf' => ($jetzt ?? time()) + ($art === 'einrichten' ? self::EINRICHTEN_SEKUNDEN : self::GUELTIG_SEKUNDEN),
            'versuche' => 0,
            'zurueck' => $zurueck,
        ];
    }

    /** @return array{benutzer: int, zurueck: string, art: string}|null offene Anmeldung, die auf den zweiten Faktor wartet */
    public static function offen(?int $jetzt = null): ?array
    {
        $z = $_SESSION[self::SCHLUESSEL] ?? null;
        if (!is_array($z) || ($z['ablauf'] ?? 0) < ($jetzt ?? time())) {
            return null;
        }
        return ['benutzer' => (int) $z['benutzer'], 'zurueck' => (string) $z['zurueck'], 'art' => (string) ($z['art'] ?? 'mail')];
    }

    /** Geheimnis für die Einrichtung während der Anmeldung (bleibt bis zur Bestätigung in der Sitzung). */
    public static function einrichtGeheimnis(): string
    {
        if (!isset($_SESSION[self::SCHLUESSEL]['geheimnis'])) {
            $_SESSION[self::SCHLUESSEL]['geheimnis'] = Totp::geheimnis();
        }
        return (string) $_SESSION[self::SCHLUESSEL]['geheimnis'];
    }

    /**
     * E-Mail-Code prüfen; die offene Anmeldung ist danach verbraucht.
     *
     * @return array{benutzer: int, zurueck: string}
     */
    public static function pruefen(string $eingabe, ?int $jetzt = null): array
    {
        $z = self::gueltig($jetzt);
        $code = preg_replace('/\D/', '', $eingabe) ?? '';
        if (($z['art'] ?? 'mail') === 'mail' && strlen($code) === 6 && hash_equals((string) $z['hash'], hash('sha256', $z['salz'] . $code))) {
            self::verwerfen();
            return ['benutzer' => (int) $z['benutzer'], 'zurueck' => (string) $z['zurueck']];
        }
        self::fehlversuch();
    }

    /**
     * Offenen Zustand holen oder mit Hinweis verwerfen.
     *
     * @return array<string, mixed>
     */
    public static function gueltig(?int $jetzt = null): array
    {
        $z = $_SESSION[self::SCHLUESSEL] ?? null;
        if (!is_array($z) || ($z['ablauf'] ?? 0) < ($jetzt ?? time())) {
            self::verwerfen();
            throw new FachFehler('Die Anmeldung ist abgelaufen – bitte erneut mit E-Mail und Passwort anmelden.');
        }
        return $z;
    }

    /** Falscher Code: zählen, nach MAX_VERSUCHE die offene Anmeldung verwerfen. */
    public static function fehlversuch(): never
    {
        $versuche = (int) ($_SESSION[self::SCHLUESSEL]['versuche'] ?? 0) + 1;
        if ($versuche >= self::MAX_VERSUCHE || !isset($_SESSION[self::SCHLUESSEL])) {
            self::verwerfen();
            throw new FachFehler('Zu viele falsche Codes – bitte erneut mit E-Mail und Passwort anmelden.');
        }
        $_SESSION[self::SCHLUESSEL]['versuche'] = $versuche;
        throw new FachFehler('Der Code stimmt nicht. Noch ' . (self::MAX_VERSUCHE - $versuche) . ' Versuche.');
    }

    public static function verwerfen(): void
    {
        unset($_SESSION[self::SCHLUESSEL]);
    }

    public static function mailText(string $code, string $name): string
    {
        return "Guten Tag $name,\n\n"
            . 'Ihr Anmeldecode für Taikai – Karate: ' . substr($code, 0, 3) . ' ' . substr($code, 3) . "\n\n"
            . 'Er gilt ' . intdiv(self::GUELTIG_SEKUNDEN, 60) . " Minuten und nur für diese Anmeldung.\n\n"
            . "Sie haben sich gerade nicht angemeldet? Dann kennt jemand Ihr Passwort – bitte ändern Sie es umgehend\n"
            . "(Passwort vergessen bzw. Administration). Ohne diesen Code kommt niemand in Ihr Konto.\n"
            . "\n-- \nDiese Nachricht wurde automatisch von Taikai – Karate verschickt.";
    }
}
