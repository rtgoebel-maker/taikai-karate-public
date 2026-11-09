<?php
/**
 * QR-Codes zum Ausdrucken: je Tatami ein Blatt mit Eingabe und Ausruf.
 * @var array<string, mixed> $turnier
 * @var list<array{id: int, bezeichnung: string, codes: array<string, array{url: string, svg: string}>}> $qr
 */
use Wettkampf\Durchfuehrung\GeraeteKopplung;

$this->layout('druck/layout', ['titel' => 'QR-Codes für Tische', 'turnier' => $turnier]);
?>
<?php if (!$qr): ?><div class="blatt"><p>Noch keine Tatamis angelegt.</p></div><?php endif; ?>
<?php foreach ($qr as $t): ?>
    <div class="blatt qr-blatt">
        <h1><?= e($t['bezeichnung']) ?></h1>
        <p><?= e($turnier['name']) ?></p>
        <div class="qr-paar">
            <?php foreach ($t['codes'] as $rolle => $c): ?>
                <figure>
                    <?= $c['svg'] /* vom Server erzeugtes SVG, keine Nutzereingabe */ ?>
                    <figcaption><strong><?= e(GeraeteKopplung::ROLLEN[$rolle]) ?></strong><br>
                        <?= $rolle === 'ausruf' ? 'Handy: Starter aufrufen' : 'Tablet: Ergebnisse eintragen' ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <ol class="qr-schritte">
            <li>QR-Code mit der Kamera scannen und den Link öffnen.</li>
            <li>Gerät benennen und „Kopplung anfragen“.</li>
            <li>Das angezeigte Prüfwort der Wettkampfleitung nennen – nach der Freigabe geht es los.</li>
        </ol>
    </div>
<?php endforeach; ?>
