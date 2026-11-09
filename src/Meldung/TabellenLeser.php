<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use ZipArchive;

/**
 * Liest die erste Tabelle einer CSV- oder XLSX-Datei als Liste von Zeilen
 * (jede Zeile: Liste von Zell-Texten). Ohne externe Bibliotheken.
 */
final class TabellenLeser
{
    /** @return list<list<string>> */
    public static function lesen(string $pfad, string $dateiname): array
    {
        $endung = strtolower(pathinfo($dateiname, PATHINFO_EXTENSION));
        return match ($endung) {
            'csv', 'txt' => self::csv((string) file_get_contents($pfad)),
            'xlsx' => self::xlsx($pfad),
            'xls' => throw new FachFehler('Das alte Excel-Format .xls wird nicht unterstützt. Bitte als .xlsx oder .csv speichern.'),
            default => throw new FachFehler('Bitte eine CSV- oder XLSX-Datei hochladen.'),
        };
    }

    /** @return list<list<string>> */
    public static function csv(string $inhalt): array
    {
        // Kodierung: UTF-8 (mit/ohne BOM) oder Windows-1252 (Excel-Export)
        if (str_starts_with($inhalt, "\xEF\xBB\xBF")) {
            $inhalt = substr($inhalt, 3);
        } elseif (!mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }
        $inhalt = str_replace(["\r\n", "\r"], "\n", $inhalt);

        $ersteZeile = strtok($inhalt, "\n") ?: '';
        $trenner = ';';
        $anzahl = 0;
        foreach ([';', ',', "\t"] as $kandidat) {
            $n = substr_count($ersteZeile, $kandidat);
            if ($n > $anzahl) {
                $anzahl = $n;
                $trenner = $kandidat;
            }
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $inhalt);
        rewind($stream);
        $zeilen = [];
        while (($zeile = fgetcsv($stream, 0, $trenner, '"', '')) !== false) {
            if ($zeile === [null]) {
                continue;
            }
            $zeilen[] = array_map(static fn($z) => trim((string) $z), $zeile);
        }
        fclose($stream);
        return $zeilen;
    }

    /** @return list<list<string>> */
    public static function xlsx(string $pfad): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new FachFehler('Für XLSX-Dateien wird die PHP-Erweiterung „zip“ benötigt. Alternativ als CSV speichern.');
        }
        $zip = new ZipArchive();
        if ($zip->open($pfad) !== true) {
            throw new FachFehler('Die XLSX-Datei konnte nicht geöffnet werden.');
        }
        try {
            $strings = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: null);
            $blatt = $zip->getFromName(self::erstesBlatt($zip));
            if ($blatt === false) {
                throw new FachFehler('In der XLSX-Datei wurde keine Tabelle gefunden.');
            }
            return self::blattLesen($blatt, $strings);
        } finally {
            $zip->close();
        }
    }

    private static function erstesBlatt(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }
        $wb = self::xml($workbook);
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheet = $wb->xpath('//m:sheets/m:sheet')[0] ?? null;
        if ($sheet === null) {
            return 'xl/worksheets/sheet1.xml';
        }
        $rId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $r = self::xml($rels);
        foreach ($r->children() as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $ziel = ltrim((string) $rel['Target'], '/');
                return str_starts_with($ziel, 'xl/') ? $ziel : 'xl/' . $ziel;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @return list<string> */
    private static function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $liste = [];
        foreach (self::xml($xml)->children() as $si) {
            // Text kann in <t> oder in mehreren Formatierungsläufen <r><t> stehen
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            }
            foreach ($si->r ?? [] as $lauf) {
                $text .= (string) $lauf->t;
            }
            $liste[] = $text;
        }
        return $liste;
    }

    /**
     * @param list<string> $strings
     * @return list<list<string>>
     */
    private static function blattLesen(string $xml, array $strings): array
    {
        $blatt = self::xml($xml);
        $zeilen = [];
        foreach ($blatt->sheetData->row ?? [] as $row) {
            $zeile = [];
            foreach ($row->c as $zelle) {
                $spalte = self::spaltenIndex((string) $zelle['r']);
                $typ = (string) $zelle['t'];
                $wert = match ($typ) {
                    's' => $strings[(int) $zelle->v] ?? '',
                    'inlineStr' => (string) ($zelle->is->t ?? ''),
                    'b' => ((string) $zelle->v) === '1' ? 'x' : '',
                    default => (string) $zelle->v,
                };
                $zeile[$spalte] = trim($wert);
            }
            if ($zeile === []) {
                continue;
            }
            $max = max(array_keys($zeile));
            $voll = [];
            for ($i = 0; $i <= $max; $i++) {
                $voll[] = $zeile[$i] ?? '';
            }
            $zeilen[] = $voll;
        }
        return $zeilen;
    }

    private static function spaltenIndex(string $referenz): int
    {
        preg_match('/^[A-Z]+/', $referenz, $m);
        $index = 0;
        foreach (str_split($m[0] ?? 'A') as $buchstabe) {
            $index = $index * 26 + (ord($buchstabe) - 64);
        }
        return $index - 1;
    }

    private static function xml(string $inhalt): \SimpleXMLElement
    {
        $xml = simplexml_load_string($inhalt, \SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            throw new FachFehler('Die Datei enthält ungültiges XML.');
        }
        return $xml;
    }
}
