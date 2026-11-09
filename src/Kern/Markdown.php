<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Kleiner Markdown-Renderer für eigene Projektdateien (z. B. CHANGELOG.md): Überschriften mit
 * Sprungmarke, Absätze, Listen, Tabellen, Trennlinie, **fett**, *kursiv*, `Code` und Links.
 *
 * Sicherheit (OWASP A03): Jeder Text wird zuerst HTML-maskiert, erst danach werden die
 * Auszeichnungen ersetzt – rohes HTML aus der Datei erscheint nur als Text. Links nur auf
 * http(s), Sprungmarken (#…) oder relative Pfade ohne Schema.
 */
final class Markdown
{
    public static function html(string $text): string
    {
        $zeilen = preg_split('/\R/', $text) ?: [];
        $html = '';
        $absatz = [];
        $liste = [];
        $tabelle = [];
        foreach ($zeilen as $zeile) {
            $rein = trim($zeile);
            if ($rein === '') {
                self::abschliessen($html, $absatz, $liste, $tabelle);
            } elseif (preg_match('/^(#{1,4})\s+(.+)$/', $rein, $m)) {
                self::abschliessen($html, $absatz, $liste, $tabelle);
                $ebene = strlen($m[1]);
                $html .= sprintf('<h%d id="%s">%s</h%d>' . "\n", $ebene, self::anker($m[2]), self::inline($m[2]), $ebene);
            } elseif (preg_match('/^(-{3,}|\*{3,})$/', $rein)) {
                self::abschliessen($html, $absatz, $liste, $tabelle);
                $html .= "<hr>\n";
            } elseif (str_starts_with($rein, '|')) {
                if ($absatz !== [] || $liste !== []) {
                    self::abschliessen($html, $absatz, $liste, $tabelle);
                }
                $tabelle[] = $rein;
            } elseif (preg_match('/^[-*]\s+(.+)$/', $rein, $m)) {
                if ($absatz !== [] || $tabelle !== []) {
                    self::abschliessen($html, $absatz, $liste, $tabelle);
                }
                $liste[] = $m[1];
            } elseif ($liste !== [] && preg_match('/^\s{2,}\S/', $zeile)) {
                $liste[count($liste) - 1] .= ' ' . $rein; // Fortsetzungszeile eines Listenpunkts
            } else {
                if ($liste !== [] || $tabelle !== []) {
                    self::abschliessen($html, $absatz, $liste, $tabelle);
                }
                $absatz[] = $rein;
            }
        }
        self::abschliessen($html, $absatz, $liste, $tabelle);
        return $html;
    }

    /**
     * Gibt den offenen Absatz, die offene Liste bzw. Tabelle aus und leert sie.
     *
     * @param list<string> $absatz
     * @param list<string> $liste
     * @param list<string> $tabelle
     */
    private static function abschliessen(string &$html, array &$absatz, array &$liste, array &$tabelle): void
    {
        if ($absatz !== []) {
            $html .= '<p>' . self::inline(implode(' ', $absatz)) . "</p>
";
            $absatz = [];
        }
        if ($liste !== []) {
            $html .= '<ul>' . implode('', array_map(static fn($l) => '<li>' . self::inline($l) . '</li>', $liste)) . "</ul>
";
            $liste = [];
        }
        if ($tabelle !== []) {
            $html .= self::tabelle($tabelle);
            $tabelle = [];
        }
    }

    /** Sprungmarke wie bei GitHub: klein, ohne Satzzeichen, Leerzeichen als Bindestrich („2026.11.07“ → „20261107“). */
    public static function anker(string $text): string
    {
        $text = mb_strtolower(preg_replace('/[`*\[\]]|\(.*?\)/u', '', $text) ?? '');
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text) ?? '';
        return preg_replace('/\s+/u', '-', trim($text)) ?? '';
    }

    /** @param list<string> $zeilen */
    private static function tabelle(array $zeilen): string
    {
        $zellen = static fn(string $z) => array_map('trim', explode('|', trim(trim($z), '|')));
        $kopf = $zellen($zeilen[0]);
        $start = isset($zeilen[1]) && preg_match('/^\|?[\s:|-]+\|?$/', $zeilen[1]) ? 2 : 1;
        $html = '<div class="tabelle-rollen"><table class="tabelle"><thead><tr>'
            . implode('', array_map(static fn($c) => '<th>' . self::inline($c) . '</th>', $kopf)) . '</tr></thead><tbody>';
        foreach (array_slice($zeilen, $start) as $z) {
            $html .= '<tr>' . implode('', array_map(static fn($c) => '<td>' . self::inline($c) . '</td>', $zellen($z))) . '</tr>';
        }
        return $html . "</tbody></table></div>\n";
    }

    private static function inline(string $text): string
    {
        $t = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Code zuerst, damit darin nichts weiter ersetzt wird
        $codes = [];
        $t = preg_replace_callback('/`([^`]+)`/', static function ($m) use (&$codes) {
            $codes[] = '<code>' . $m[1] . '</code>';
            return "\x00" . (count($codes) - 1) . "\x00";
        }, $t) ?? $t;
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($m) {
            $ziel = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $erlaubt = preg_match('#^(https?://|\#)#i', $ziel) === 1 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $ziel) === 0;
            if (!$erlaubt) {
                return $m[1];
            }
            $extern = preg_match('#^https?://#i', $ziel) === 1;
            return '<a href="' . htmlspecialchars($ziel, ENT_QUOTES, 'UTF-8') . '"' . ($extern ? ' rel="noopener noreferrer" target="_blank"' : '') . '>' . $m[1] . '</a>';
        }, $t) ?? $t;
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t) ?? $t;
        $t = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $t) ?? $t;
        return preg_replace_callback("/\x00(\\d+)\x00/", static fn($m) => $codes[(int) $m[1]], $t) ?? $t;
    }
}
