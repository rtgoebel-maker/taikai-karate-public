<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, int> $zahlen
 * @var list<array{typ: string, text: string, klasse_id?: int, teilnahme_id?: int}> $hinweise
 * @var string $phase
 * @var array<string, list<string>> $offenePunkte offene Punkte vor „läuft“ bzw. „abgeschlossen“
 * @var list<array<string, mixed>> $nichtErschienen Rückmeldungen des Ausrufs
 * @var bool $meldephaseGewesen
 * @var array{ich: bool, andere: list<string>, ohneMail: bool}|null $zweiterFaktor Konten ohne Authenticator-App
 */
use Wettkampf\Domain\TurnierPhase;

$this->layout('layout', ['titel' => $turnier['name'], 'turnier' => $turnier, 'aktiv' => 'uebersicht']);
$gestartet = in_array($phase, [TurnierPhase::LAEUFT, TurnierPhase::ABGESCHLOSSEN], true);
$fortschritt = $zahlen['begegnungen'] > 0 ? round(100 * $zahlen['beendet'] / $zahlen['begegnungen']) : 0;
?>
<p class="obertitel"><?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' · ' . e($turnier['ort']) : '' ?></p>
<h1><?= e($turnier['name']) ?></h1>
<?php if (\Wettkampf\Betrieb\DemoTurnier::kennung($turnier) !== null): $demoNaechster = $demoNaechster ?? null; ?>
    <div class="meldung meldung-hinweis"><?= $demoNaechster !== null
        ? te('Demo-Turnier zum Ausprobieren – wird am {datum} um {uhrzeit} automatisch zurückgesetzt.', ['datum' => datum_de(substr($demoNaechster, 0, 10)), 'uhrzeit' => substr($demoNaechster, 11, 5)])
        : te('Demo-Turnier zum Ausprobieren.') ?></div>
<?php endif; ?>
<?php if (!$gestartet): ?>
    <p class="knopfleiste"><a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/assistent")) ?>">🧭 <?= te('Einrichtungs-Assistent') ?></a>
        <span class="klein leise"><?= te('führt durch Klassen, Tatamis und PINs, Online-Anmeldung, Zahlungsangaben und Coaching-Portal') ?></span></p>
<?php endif; ?>

<?php if ($zweiterFaktor !== null): ?>
    <div class="meldung meldung-hinweis">
        <?php if ($zweiterFaktor['ohneMail']): ?>
            <strong><?= te('Authenticator-App vor dem Turnier einrichten.') ?></strong>
            <?= te('Diese Installation (z. B. der Hallen-Laptop) verschickt keine E-Mails – für die Anmeldung ist ein Code aus der Authenticator-App nötig.') ?>
            <?= te('Ohne App muss sie bei der nächsten Anmeldung eingerichtet werden – besser jetzt in Ruhe als am Turniertag.') ?>
            <?php if ($zweiterFaktor['ich']): ?>
                <br><?= th('Ihr Konto hat noch keine App: <a href="{url}">Jetzt unter „Konto &amp; Sicherheit“ einrichten</a>.', ['url' => url('konto/sicherheit')]) ?>
            <?php endif; ?>
            <?php if ($zweiterFaktor['andere']): ?>
                <br><?= te('Ohne App in der Turnierleitung: {namen} – bitte Bescheid geben (jeder richtet sie selbst ein).', ['namen' => implode(', ', $zweiterFaktor['andere'])]) ?>
            <?php endif; ?>
        <?php else: ?>
            <strong><?= te('Für den Hallen-Laptop:') ?></strong> <?= te('Dort gibt es in der Regel keinen E-Mail-Versand, und Konten werden nicht übertragen – jedes Konto auf dem Laptop braucht eine Authenticator-App (zweiter Faktor ist Pflicht). Am besten beim Einrichten bzw. bei der Übergabe an die Halle auf dem Laptop unter „Konto & Sicherheit“ einrichten, nicht erst am Turniertag.') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($nichtErschienen): ?>
    <?= $this->teil('turnier/nicht_erschienen', ['turnier' => $turnier, 'meldungen' => $nichtErschienen]) ?>
<?php endif; ?>
<?php if (!empty($kataBeendet)): ?>
<section class="karte kata-beendet" id="kata-beendet">
    <h2>☕ <?= te('Alle Kata-Wettkämpfe sind gelaufen') ?></h2>
    <p><?= $kataBeendet['seit'] ? te('Seit {zeit} Uhr ist keine Kata-Gruppe mehr offen;', ['zeit' => substr($kataBeendet['seit'], 11, 5)]) : te('Seit eben ist keine Kata-Gruppe mehr offen;') ?> <?= $kataBeendet['kumite_offen'] === 1 ? te('Kumite: noch {n} Klasse.', ['n' => (int) $kataBeendet['kumite_offen']]) : te('Kumite: noch {n} Klassen.', ['n' => (int) $kataBeendet['kumite_offen']]) ?>
        <?= th('Meist die Gelegenheit für eine <strong>allgemeine Pause</strong> – z. B. alle Tatamis im <a href="{url}">Tatami-Plan</a> sperren und eine Nachricht an die Tische senden.', ['url' => url("turnier/{$turnier['id']}/plan")]) ?></p>
    <form method="post" action="<?= e(url("turnier/{$turnier['id']}/kata-beendet")) ?>" class="inline">
        <?= csrf_feld() ?>
        <button class="knopf" type="submit"><?= te('Verstanden – Meldung ausblenden') ?></button>
    </form>
</section>
<?php endif; ?>
<?= $this->teil('turnier/phase', ['turnier' => $turnier, 'phase' => $phase, 'offenePunkte' => $offenePunkte, 'meldephaseGewesen' => $meldephaseGewesen]) ?>
<?php if ($phase === TurnierPhase::LAEUFT): ?>
<?php /* Während das Turnier läuft: bei neuen Meldungen der Ausrufer automatisch aktualisieren (app.js) */ ?>
<div hidden data-aktualisieren="<?= e(url("api/v1/turnier/{$turnier['id']}")) ?>" data-intervall="8000"></div>
<?php endif; ?>

<div class="raster">
    <div class="karte"><div class="klein"><?= te('Teilnehmer') ?></div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['teilnehmer'] ?></div><div class="klein"><?= te('{vereine} Vereine · {teams} Teams', ['vereine' => $zahlen['vereine'], 'teams' => $zahlen['teams']]) ?></div></div>
    <div class="karte"><div class="klein"><?= te('Klassen') ?></div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['klassen'] ?></div><div class="klein"><?= te('{n} Starts', ['n' => $zahlen['starts']]) ?></div></div>
    <div class="karte"><div class="klein"><?= te('Kämpfe & Vorträge') ?></div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['beendet'] ?> / <?= $zahlen['begegnungen'] ?></div><div class="klein"><?= te('{n} % beendet', ['n' => $fortschritt]) ?></div></div>
</div>

<?php if ($gestartet): // nach dem Start: Vorbereitungshinweise nur noch aufklappbar ?>
<details class="vorbereitung-rueckblick">
    <summary><?= te('Ablauf und Hinweise aus der Vorbereitung') ?><?= $hinweise ? ' (' . te('{n} Hinweise', ['n' => count($hinweise)]) . ')' : '' ?></summary>
<?php endif; ?>
<div class="zwei-spalten">
    <div class="karte">
        <h2><?= te('Ablauf') ?> <?= info('ablauf') ?></h2>
        <ol>
            <li><?= th('<a href="{url}">Klassen</a> aus Vorlage laden oder anlegen', ['url' => url("turnier/{$turnier['id']}/klassen")]) ?></li>
            <li><?= th('<a href="{teilnehmer}">Teilnehmer</a> erfassen oder <a href="{import}">importieren</a>, <a href="{teams}">Teams</a> bilden', ['teilnehmer' => url("turnier/{$turnier['id']}/teilnehmer"), 'import' => url("turnier/{$turnier['id']}/import"), 'teams' => url("turnier/{$turnier['id']}/teams")]) ?></li>
            <li><?= te('Kleine Klassen prüfen und ggf. zusammenlegen') ?></li>
            <li><?= te('Auslosen (je Klasse oder alle)') ?></li>
            <li><?= th('<a href="{plan}">Tatami-Plan</a> erstellen, Listen <a href="{druck}">drucken</a>', ['plan' => url("turnier/{$turnier['id']}/plan"), 'druck' => url("turnier/{$turnier['id']}/druck")]) ?></li>
            <li><?= te('Ergebnisse erfassen (Tablet oder PC), Platzierungen bestätigen') ?></li>
        </ol>
    </div>
    <div class="karte">
        <h2><?= te('Hinweise') ?> <?= info('hinweise') ?></h2>
        <?php if (!$hinweise): ?>
            <p class="leise"><?= te('Keine offenen Hinweise.') ?></p>
        <?php else: ?>
            <ul class="hinweisliste">
                <?php foreach (array_slice($hinweise, 0, 25) as $h): ?>
                    <li><span class="tag <?= $h['typ'] === 'fehler' ? 'fehler' : 'warn' ?>"><?= $h['typ'] === 'fehler' ? te('Fehler') : te('Hinweis') ?></span>
                        <?php if (isset($h['klasse_id'])): ?>
                            <a href="<?= e(url("turnier/{$turnier['id']}/klasse/{$h['klasse_id']}")) ?>"><?= e($h['text']) ?></a>
                        <?php elseif (isset($h['teilnahme_id'])): ?>
                            <a href="<?= e(url("turnier/{$turnier['id']}/teilnehmer/{$h['teilnahme_id']}")) ?>"><?= e($h['text']) ?></a>
                        <?php else: ?>
                            <?= e($h['text']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($hinweise) > 25): ?><p class="klein"><?= te('… und {n} weitere', ['n' => count($hinweise) - 25]) ?></p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($gestartet): ?></details><?php endif; ?>
