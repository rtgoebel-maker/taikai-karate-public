<?php
/**
 * Beleg als PDF (Buchhaltungsexport): ein Rechnungsblatt (rechnung/blatt) mit eingebetteten, für dompdf einfachen Styles
 * (CSS 2.1, keine externen Dateien – dompdf lädt nichts nach).
 * @var array<string, mixed> $blatt Daten für rechnung/blatt
 * @var string $vermerk Zeile am Seitenende (z. B. „Archivkopie, erstellt am …“)
 */
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Beleg <?= e((string) $blatt['nummer']) ?></title>
<style>
@page { size: A4 portrait; margin: 14mm 14mm 18mm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #000; margin: 0; }
h1 { font-size: 15pt; margin: 0 0 3mm; }
h2 { font-size: 11pt; margin: 5mm 0 2mm; }
table { width: 100%; border-collapse: collapse; margin: 2mm 0; }
th, td { border: .3pt solid #777; padding: 1mm 1.5mm; text-align: left; vertical-align: top; }
thead th { background: #eee; }
.rechts { text-align: right; }
.klein { font-size: 7.5pt; color: #444; }
.r-kopf { text-align: right; min-height: 20mm; }
.r-absender { font-size: 8.5pt; line-height: 1.35; }
.r-adressfeld { float: left; width: 85mm; min-height: 30mm; margin-top: 4mm; line-height: 1.4; }
.r-ruecksende { font-size: 6.5pt; border-bottom: .3pt solid #000; margin-bottom: 2mm; padding-bottom: .5mm; }
table.r-daten { float: right; width: 70mm; margin-top: 8mm; }
table.r-daten th, table.r-daten td { border: 0; padding: .5mm 0; font-size: 8.5pt; background: none; }
table.r-daten td { text-align: right; }
.r-titel { clear: both; padding-top: 8mm; }
.r-bank, .r-bezahlt { border-left: 2px solid #000; padding-left: 3mm; }
table.r-starter { font-size: 8pt; }
.vermerk { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #555; text-align: center; }
</style>
</head>
<body>
<div class="vermerk"><?= e($vermerk) ?></div>
<?= $this->teil('rechnung/blatt', $blatt) ?>
</body>
</html>
