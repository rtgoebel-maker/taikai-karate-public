<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;

/**
 * Schutz öffentlicher Formulare gegen Bots (OWASP A04/A07, API6) – ohne Drittanbieter, für Menschen unsichtbar:
 *
 * - **Formular-Token**: signiert (HMAC mit dem Schlüssel der Installation), an den Zweck gebunden, nur einmal gültig,
 *   frühestens nach einer Mindest-Ausfüllzeit und höchstens 12 Stunden nach dem Laden der Seite.
 * - **Rechenaufgabe (Proof-of-Work)**: Der Browser sucht im Hintergrund eine Zahl n, sodass SHA-256(Aufgabe:n) mit
 *   einer Anzahl Null-Bits beginnt (botschutz.js, Standard 16 Bit ≈ 65 000 Versuche, auf dem Handy unter einer Sekunde).
 *   Massenhaftes Absenden wird damit teuer.
 * - **Spam-Falle**: verstecktes Feld, das Menschen leer lassen.
 *
 * Dazu kommen je Formular Grenzen je IP bzw. E-Mail (Drossel, AnmeldeSchutz) und die E-Mail-Bestätigung neuer Konten.
 */
final class BotSchutz
{
    public const FELD_TOKEN = 'bs_token';
    public const FELD_LOESUNG = 'bs_loesung';
    public const FELD_FALLE = 'bs_kontakt';
    private const MAX_ALTER = 12 * 3600;

    public function __construct(
        private readonly Database $db,
        private readonly string $schluessel,
        private readonly bool $aktiv = true,
        private readonly int $bits = 16,
        private readonly int $mindestSekunden = 3,
    ) {}

    /** @param array<string, mixed> $config Abschnitt 'bot_schutz' (aktiv, schwierigkeit, mindestzeit) */
    public static function ausKonfiguration(Database $db, array $config, string $schluessel): self
    {
        $c = (array) ($config['bot_schutz'] ?? []);
        return new self($db, $schluessel, ($c['aktiv'] ?? true) !== false, max(1, min(24, (int) ($c['schwierigkeit'] ?? 16))), max(0, (int) ($c['mindestzeit'] ?? 3)));
    }

    /** Versteckte Felder für ein Formular (in das Formular einsetzen; botschutz.js löst die Aufgabe). */
    public function felder(string $zweck, ?int $jetzt = null): string
    {
        if (!$this->aktiv) {
            return '';
        }
        $token = $this->token($zweck, $jetzt ?? time());
        $nonce = explode('.', $token)[2];
        $e = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . self::FELD_TOKEN . '" value="' . $e($token) . '">'
            . '<input type="hidden" name="' . self::FELD_LOESUNG . '" value="" data-botschutz="' . $e($nonce) . '" data-bits="' . $this->bits . '">'
            . '<div class="bs-falle" aria-hidden="true"><label>Bitte leer lassen <input name="' . self::FELD_FALLE . '" tabindex="-1" autocomplete="off"></label></div>';
    }

    /** Token: zweck.zeit.nonce.bits.hmac */
    public function token(string $zweck, int $zeit): string
    {
        $zweck = preg_replace('/[^a-z0-9_-]/', '', strtolower($zweck)) ?? '';
        $nutzlast = $zweck . '.' . $zeit . '.' . bin2hex(random_bytes(12)) . '.' . $this->bits;
        return $nutzlast . '.' . hash_hmac('sha256', $nutzlast, $this->schluessel);
    }

    /**
     * Prüft ein abgeschicktes Formular; wirft bei Verdacht. Das Token ist danach verbraucht.
     *
     * @param array<string, mixed> $post
     * @param int|null $mindestSekunden kürzere Mindest-Ausfüllzeit für dieses Formular (Login: Passwort-Manager füllen schnell aus)
     */
    public function pruefen(array $post, string $zweck, ?int $mindestSekunden = null, ?int $jetzt = null): void
    {
        if (!$this->aktiv) {
            return;
        }
        $jetzt ??= time();
        $fehler = new FachFehler('Das Formular konnte nicht angenommen werden – bitte die Seite neu laden und erneut absenden. '
            . '(Schutz vor automatischen Anmeldungen; JavaScript muss eingeschaltet sein.)');
        if (trim((string) ($post[self::FELD_FALLE] ?? '')) !== '') {
            throw $fehler;
        }
        $teile = explode('.', (string) ($post[self::FELD_TOKEN] ?? ''));
        if (count($teile) !== 5) {
            throw $fehler;
        }
        [$tZweck, $tZeit, $nonce, $bits, $hmac] = $teile;
        $nutzlast = "$tZweck.$tZeit.$nonce.$bits";
        if (!hash_equals(hash_hmac('sha256', $nutzlast, $this->schluessel), $hmac) || $tZweck !== strtolower($zweck)
            || !ctype_digit($tZeit) || !ctype_digit($bits) || !preg_match('/^[0-9a-f]{24}$/', $nonce)) {
            throw $fehler;
        }
        $alter = $jetzt - (int) $tZeit;
        if ($alter > self::MAX_ALTER) {
            throw new FachFehler('Die Seite war zu lange geöffnet – bitte neu laden und erneut absenden.');
        }
        if ($alter < min($mindestSekunden ?? $this->mindestSekunden, $this->mindestSekunden)) {
            throw $fehler; // schneller abgeschickt, als ein Mensch das Formular ausfüllen kann
        }
        $loesung = (string) ($post[self::FELD_LOESUNG] ?? '');
        if (!ctype_digit($loesung) || strlen($loesung) > 12 || !self::nullBits(hash('sha256', "$nonce:$loesung", true), (int) $bits)) {
            throw $fehler;
        }
        // nur einmal gültig
        $this->db->query('DELETE FROM formular_token WHERE ablauf < ?', [date('Y-m-d H:i:s', $jetzt)]);
        try {
            $this->db->insert('formular_token', ['nonce' => $nonce, 'ablauf' => date('Y-m-d H:i:s', (int) $tZeit + self::MAX_ALTER)]);
        } catch (\PDOException) {
            throw $fehler;
        }
    }

    /** Beginnt der Hash mit mindestens $bits Null-Bits? */
    public static function nullBits(string $hash, int $bits): bool
    {
        $voll = intdiv($bits, 8);
        for ($i = 0; $i < $voll; $i++) {
            if (ord($hash[$i]) !== 0) {
                return false;
            }
        }
        $rest = $bits % 8;
        return $rest === 0 || (ord($hash[$voll]) >> (8 - $rest)) === 0;
    }

    /** Lösung finden (für Tests und Werkzeuge; im Browser macht das botschutz.js). */
    public static function loesen(string $nonce, int $bits): string
    {
        for ($n = 0; ; $n++) {
            if (self::nullBits(hash('sha256', "$nonce:$n", true), $bits)) {
                return (string) $n;
            }
        }
    }
}
