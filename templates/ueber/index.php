<?php
/**
 * Über diese Software (öffentlich): Zweck, Version, Urheber, Lizenz, Quellcode, Bibliotheken.
 * @var string $version
 * @var int $syncProtokoll
 * @var string|null $quellcode
 * @var string|null $spenden Spendenlink (PayPal) oder null = ausgeblendet
 * @var list<array{name: string, version: string, lizenz: string}> $bibliotheken
 * @var bool $changelog
 */
use Wettkampf\Betrieb\Http\UeberController;

$this->layout('layout', ['titel' => 'Über diese Software', 'aktiv' => 'ueber']);
?>
<p class="obertitel">Über diese Software</p>
<h1 class="ueber-titel"><img src="<?= e(asset('logo/taikai-logo.svg')) ?>" alt="Taikai – Karate" width="300" height="96"></h1>
<p class="leise"><strong>Taikai</strong> (jap. 大会 – „Turnier“) ist eine freie Karate-Wettkampfsoftware: Turnierverwaltung für Karate-Wettkämpfe (Kata, Kumite, Kata-Team, Kumite-Team) nach der DJKB-Wettkampfordnung 2024 – von der Online-Anmeldung der Dojos über Klasseneinteilung, Auslosung und Tatami-Plan bis zur Ergebniseingabe am Tablet, Urkunde, Siegerliste und öffentlicher Statusseite.</p>

<div class="zwei-spalten">
    <section class="karte">
        <h2>Version</h2>
        <table class="tabelle">
            <tr><th>Programmversion</th><td><code><?= e($version) ?></code></td></tr>
            <tr><th>Projektseite</th><td><a href="<?= e(UeberController::WEBSITE) ?>" rel="noopener noreferrer" target="_blank"><?= e(preg_replace('#^https://#', '', UeberController::WEBSITE)) ?></a></td></tr>
            <tr><th>Sync-Protokoll</th><td><?= (int) $syncProtokoll ?> <span class="klein">(Hallen-Laptop und Online müssen übereinstimmen)</span></td></tr>
        </table>
        <p class="klein">Versionen heißen nach dem Datum ihres Stands (<code>JJJJ.MM.TT</code>), dahinter der Commit.</p>
        <?php if ($changelog): ?>
            <p><a class="knopf" href="<?= e(url('ueber/changelog')) ?>">Changelog – was ist neu?</a></p>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2>Urheber</h2>
        <p><strong>Urheber:</strong> <?= e(implode(' und ', UeberController::URHEBER)) ?></p>
        <p class="klein">© 2026 <?= e(implode(', ', UeberController::URHEBER)) ?>. Diese Nennung muss bei jeder Weitergabe und jeder veränderten Fassung erhalten bleiben.</p>
    </section>
</div>

<section class="karte">
    <h2>Lizenz: freie Software</h2>
    <p>Diese Software steht unter der <strong><?= e(UeberController::LIZENZ) ?></strong>. In Kürze:</p>
    <ul>
        <li><strong>Frei nutzen</strong> – für jedes Turnier, jeden Verein und Verband, auch ohne Gebühr.</li>
        <li><strong>Frei ändern und weiterentwickeln</strong> – der Quellcode ist offen.</li>
        <li><strong>Frei weitergeben</strong> – unverändert oder verändert, wieder unter derselben Lizenz.</li>
        <li><strong>Urheber nennen</strong> – der Hinweis auf <?= e(implode(' und ', UeberController::URHEBER)) ?> und diese Seite müssen erhalten bleiben.</li>
        <li><strong>Offen bleiben</strong> – wer eine veränderte Fassung anderen zur Verfügung stellt, auch als Webseite im Internet, muss ihnen den geänderten Quellcode ebenfalls anbieten.</li>
        <li><strong>Keine Gewährleistung</strong> – die Software wird ohne Garantie bereitgestellt; maßgeblich ist der Lizenztext.</li>
    </ul>
    <p><a href="<?= e(url('ueber/lizenz')) ?>">Vollständiger Lizenztext und Urheberhinweis</a></p>
</section>

<?php if ($spenden !== null): ?>
<section class="karte spenden" id="spenden">
    <div>
        <h2>♥ Projekt unterstützen</h2>
        <p>Diese Software ist frei und kostenlos – entwickelt in der Freizeit, getestet auf echten Turnieren. Wenn sie deinem Verein Arbeit abnimmt, freuen wir uns über eine kleine Spende. Sie fließt in Pflege, Sicherheit und Weiterentwicklung.</p>
        <p><a class="knopf primaer" href="<?= e($spenden) ?>" rel="noopener noreferrer" target="_blank">Jetzt spenden über PayPal</a></p>
        <p class="klein">Auch ohne Spende hilft ihr uns: mit Rückmeldungen über den Feedback-Knopf, Ideen für neue Funktionen oder indem ihr die Software an andere Vereine weiterempfehlt. Die Spende geht an Privatpersonen; eine Spendenbescheinigung ist nicht möglich.</p>
    </div>
    <?php if ($spenden === UeberController::SPENDEN_URL): ?>
        <figure class="spenden-qr"><img src="<?= e(asset('spenden-qr.png')) ?>" alt="QR-Code zur PayPal-Spendenseite" width="152" height="152"><figcaption>mit dem Handy scannen</figcaption></figure>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="karte">
    <h2>Quellcode</h2>
    <?php if ($quellcode !== null): ?>
        <p>Den Quellcode dieser Installation gibt es hier: <a href="<?= e($quellcode) ?>" rel="noopener noreferrer" target="_blank"><?= e($quellcode) ?></a></p>
    <?php else: ?>
        <p>Den Quellcode dieser Installation stellt ihr Betreiber auf Anfrage bereit.</p>
        <p class="klein">Für Betreiber: Mit <code>'quellcode_url' => 'https://…'</code> in <code>config/config.php</code> erscheint hier ein Link zum Quellcode – bei veränderten Fassungen verlangt die AGPL dieses Angebot.</p>
    <?php endif; ?>
</section>

<section class="karte">
    <h2>Verwendete Bibliotheken</h2>
    <p class="klein">Die Software baut auf freien Bibliotheken auf; jede steht unter ihrer eigenen Lizenz.</p>
    <?php if ($bibliotheken): ?>
        <div class="tabelle-rollen">
            <table class="tabelle">
                <thead><tr><th>Bibliothek</th><th>Version</th><th>Lizenz</th></tr></thead>
                <tbody>
                <?php foreach ($bibliotheken as $b): ?>
                    <tr><td><?= e($b['name']) ?></td><td><?= e($b['version']) ?></td><td><?= e($b['lizenz']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="leise">Keine Angaben gefunden.</p>
    <?php endif; ?>
</section>

<section class="karte">
    <h2>Hinweis</h2>
    <p class="klein">Die Wertungsregeln folgen der Wettkampfordnung des Deutschen JKA-Karate Bundes (DJKB). Die Software ist kein offizielles Produkt des DJKB.</p>
</section>
