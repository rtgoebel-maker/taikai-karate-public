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

$this->layout('layout', ['titel' => t('Über diese Software'), 'aktiv' => 'ueber']);
$urheberUnd = implode(' ' . t('und') . ' ', UeberController::URHEBER);
?>
<p class="obertitel"><?= te('Über diese Software') ?></p>
<h1 class="ueber-titel"><img src="<?= e(asset('logo/taikai-logo.svg')) ?>" alt="Taikai – Karate" width="300" height="96"></h1>
<p class="leise"><?= th('<strong>Taikai</strong> (jap. 大会 – „Turnier“) ist eine freie Karate-Wettkampfsoftware: Turnierverwaltung für Karate-Wettkämpfe (Kata, Kumite, Kata-Team, Kumite-Team) nach der DJKB-Wettkampfordnung 2024 – von der Online-Anmeldung der Dojos über Klasseneinteilung, Auslosung und Tatami-Plan bis zur Ergebniseingabe am Tablet, Urkunde, Siegerliste und öffentlicher Statusseite.') ?></p>

<div class="zwei-spalten">
    <section class="karte">
        <h2><?= te('Version') ?></h2>
        <table class="tabelle">
            <tr><th><?= te('Programmversion') ?></th><td><code><?= e($version) ?></code></td></tr>
            <tr><th><?= te('Projektseite') ?></th><td><a href="<?= e(UeberController::WEBSITE) ?>" rel="noopener noreferrer" target="_blank"><?= e(preg_replace('#^https://#', '', UeberController::WEBSITE)) ?></a></td></tr>
            <tr><th><?= te('Sync-Protokoll') ?></th><td><?= (int) $syncProtokoll ?> <span class="klein"><?= te('(Hallen-Laptop und Online müssen übereinstimmen)') ?></span></td></tr>
        </table>
        <p class="klein"><?= th('Versionen heißen nach dem Datum ihres Stands (<code>JJJJ.MM.TT</code>), dahinter der Commit.') ?></p>
        <?php if ($changelog): ?>
            <p><a class="knopf" href="<?= e(url('ueber/changelog')) ?>"><?= te('Changelog – was ist neu?') ?></a></p>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2><?= te('Urheber') ?></h2>
        <p><strong><?= te('Urheber:') ?></strong> <?= e($urheberUnd) ?></p>
        <p class="klein">© 2026 <?= e(implode(', ', UeberController::URHEBER)) ?>. <?= te('Diese Nennung muss bei jeder Weitergabe und jeder veränderten Fassung erhalten bleiben.') ?></p>
    </section>
</div>

<section class="karte">
    <h2><?= te('Lizenz: freie Software') ?></h2>
    <p><?= th('Diese Software steht unter der <strong>{lizenz}</strong>. In Kürze:', ['lizenz' => t(UeberController::LIZENZ)]) ?></p>
    <ul>
        <li><?= th('<strong>Frei nutzen</strong> – für jedes Turnier, jeden Verein und Verband, auch ohne Gebühr.') ?></li>
        <li><?= th('<strong>Frei ändern und weiterentwickeln</strong> – der Quellcode ist offen.') ?></li>
        <li><?= th('<strong>Frei weitergeben</strong> – unverändert oder verändert, wieder unter derselben Lizenz.') ?></li>
        <li><?= th('<strong>Urheber nennen</strong> – der Hinweis auf {urheber} und diese Seite müssen erhalten bleiben.', ['urheber' => $urheberUnd]) ?></li>
        <li><?= th('<strong>Offen bleiben</strong> – wer eine veränderte Fassung anderen zur Verfügung stellt, auch als Webseite im Internet, muss ihnen den geänderten Quellcode ebenfalls anbieten.') ?></li>
        <li><?= th('<strong>Keine Gewährleistung</strong> – die Software wird ohne Garantie bereitgestellt; maßgeblich ist der Lizenztext.') ?></li>
    </ul>
    <p><a href="<?= e(url('ueber/lizenz')) ?>"><?= te('Vollständiger Lizenztext und Urheberhinweis') ?></a></p>
</section>

<?php if ($spenden !== null): ?>
<section class="karte spenden" id="spenden">
    <div>
        <h2>♥ <?= te('Projekt unterstützen') ?></h2>
        <p><?= te('Diese Software ist frei und kostenlos – entwickelt in der Freizeit, getestet auf echten Turnieren. Wenn sie deinem Verein Arbeit abnimmt, freuen wir uns über eine kleine Spende. Sie fließt in Pflege, Sicherheit und Weiterentwicklung.') ?></p>
        <p><a class="knopf primaer" href="<?= e($spenden) ?>" rel="noopener noreferrer" target="_blank"><?= te('Jetzt spenden über PayPal') ?></a></p>
        <p class="klein"><?= te('Auch ohne Spende hilft ihr uns: mit Rückmeldungen über den Feedback-Knopf, Ideen für neue Funktionen oder indem ihr die Software an andere Vereine weiterempfehlt. Die Spende geht an Privatpersonen; eine Spendenbescheinigung ist nicht möglich.') ?></p>
    </div>
    <?php if ($spenden === UeberController::SPENDEN_URL): ?>
        <figure class="spenden-qr"><img src="<?= e(asset('spenden-qr.png')) ?>" alt="<?= te('QR-Code zur PayPal-Spendenseite') ?>" width="152" height="152"><figcaption><?= te('mit dem Handy scannen') ?></figcaption></figure>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="karte">
    <h2><?= te('Quellcode') ?></h2>
    <?php if ($quellcode !== null): ?>
        <p><?= te('Den Quellcode dieser Installation gibt es hier:') ?> <a href="<?= e($quellcode) ?>" rel="noopener noreferrer" target="_blank"><?= e($quellcode) ?></a></p>
    <?php else: ?>
        <p><?= te('Den Quellcode dieser Installation stellt ihr Betreiber auf Anfrage bereit.') ?></p>
        <p class="klein"><?= th('Für Betreiber: Mit <code>\'quellcode_url\' => \'https://…\'</code> in <code>config/config.php</code> erscheint hier ein Link zum Quellcode – bei veränderten Fassungen verlangt die AGPL dieses Angebot.') ?></p>
    <?php endif; ?>
</section>

<section class="karte">
    <h2><?= te('Verwendete Bibliotheken') ?></h2>
    <p class="klein"><?= te('Die Software baut auf freien Bibliotheken auf; jede steht unter ihrer eigenen Lizenz.') ?></p>
    <?php if ($bibliotheken): ?>
        <div class="tabelle-rollen">
            <table class="tabelle">
                <thead><tr><th><?= te('Bibliothek') ?></th><th><?= te('Version') ?></th><th><?= te('Lizenz') ?></th></tr></thead>
                <tbody>
                <?php foreach ($bibliotheken as $b): ?>
                    <tr><td><?= e($b['name']) ?></td><td><?= e($b['version']) ?></td><td><?= e($b['lizenz']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="leise"><?= te('Keine Angaben gefunden.') ?></p>
    <?php endif; ?>
</section>

<section class="karte">
    <h2><?= te('Hinweis') ?></h2>
    <p class="klein"><?= te('Die Wertungsregeln folgen der Wettkampfordnung des Deutschen JKA-Karate Bundes (DJKB). Die Software ist kein offizielles Produkt des DJKB.') ?></p>
</section>
