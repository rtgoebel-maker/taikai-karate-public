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
<?php if (!$gestartet): ?>
    <p class="knopfleiste"><a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/assistent")) ?>">🧭 Einrichtungs-Assistent</a>
        <span class="klein leise">führt durch Klassen, Tatamis und PINs, Online-Anmeldung, Zahlungsangaben und Coaching-Portal</span></p>
<?php endif; ?>

<?php if ($zweiterFaktor !== null): ?>
    <div class="meldung meldung-hinweis">
        <?php if ($zweiterFaktor['ohneMail']): ?>
            <strong>Authenticator-App vor dem Turnier einrichten.</strong>
            Diese Installation (z. B. der Hallen-Laptop) verschickt keine E-Mails – für die Anmeldung ist ein Code aus der Authenticator-App nötig.
            Ohne App muss sie bei der nächsten Anmeldung eingerichtet werden – besser jetzt in Ruhe als am Turniertag.
            <?php if ($zweiterFaktor['ich']): ?>
                <br>Ihr Konto hat noch keine App: <a href="<?= e(url('konto/sicherheit')) ?>">Jetzt unter „Konto &amp; Sicherheit“ einrichten</a>.
            <?php endif; ?>
            <?php if ($zweiterFaktor['andere']): ?>
                <br>Ohne App in der Turnierleitung: <?= e(implode(', ', $zweiterFaktor['andere'])) ?> – bitte Bescheid geben (jeder richtet sie selbst ein).
            <?php endif; ?>
        <?php else: ?>
            <strong>Für den Hallen-Laptop:</strong> Dort gibt es in der Regel keinen E-Mail-Versand, und Konten werden nicht übertragen – jedes Konto
            auf dem Laptop braucht eine Authenticator-App (zweiter Faktor ist Pflicht). Am besten beim Einrichten bzw. bei der Übergabe an die Halle
            auf dem Laptop unter „Konto &amp; Sicherheit“ einrichten, nicht erst am Turniertag.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($nichtErschienen): ?>
    <?= $this->teil('turnier/nicht_erschienen', ['turnier' => $turnier, 'meldungen' => $nichtErschienen]) ?>
<?php endif; ?>
<?php if (!empty($kataBeendet)): ?>
<section class="karte kata-beendet" id="kata-beendet">
    <h2>☕ Alle Kata-Wettkämpfe sind gelaufen</h2>
    <p>Seit <?= $kataBeendet['seit'] ? e(substr($kataBeendet['seit'], 11, 5)) . ' Uhr' : 'eben' ?> ist keine Kata-Gruppe mehr offen; Kumite: noch <?= (int) $kataBeendet['kumite_offen'] ?> <?= $kataBeendet['kumite_offen'] === 1 ? 'Klasse' : 'Klassen' ?>.
        Meist die Gelegenheit für eine <strong>allgemeine Pause</strong> – z. B. alle Tatamis im <a href="<?= e(url("turnier/{$turnier['id']}/plan")) ?>">Tatami-Plan</a> sperren und eine Nachricht an die Tische senden.</p>
    <form method="post" action="<?= e(url("turnier/{$turnier['id']}/kata-beendet")) ?>" class="inline">
        <?= csrf_feld() ?>
        <button class="knopf" type="submit">Verstanden – Meldung ausblenden</button>
    </form>
</section>
<?php endif; ?>
<?= $this->teil('turnier/phase', ['turnier' => $turnier, 'phase' => $phase, 'offenePunkte' => $offenePunkte, 'meldephaseGewesen' => $meldephaseGewesen]) ?>
<?php if ($phase === TurnierPhase::LAEUFT): ?>
<?php /* Während das Turnier läuft: bei neuen Meldungen der Ausrufer automatisch aktualisieren (app.js) */ ?>
<div hidden data-aktualisieren="<?= e(url("api/v1/turnier/{$turnier['id']}")) ?>" data-intervall="8000"></div>
<?php endif; ?>

<div class="raster">
    <div class="karte"><div class="klein">Teilnehmer</div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['teilnehmer'] ?></div><div class="klein"><?= $zahlen['vereine'] ?> Vereine · <?= $zahlen['teams'] ?> Teams</div></div>
    <div class="karte"><div class="klein">Klassen</div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['klassen'] ?></div><div class="klein"><?= $zahlen['starts'] ?> Starts</div></div>
    <div class="karte"><div class="klein">Kämpfe &amp; Vorträge</div><div class="zahl" style="font-size:2rem;font-weight:800"><?= $zahlen['beendet'] ?> / <?= $zahlen['begegnungen'] ?></div><div class="klein"><?= $fortschritt ?> % beendet</div></div>
</div>

<?php if ($gestartet): // nach dem Start: Vorbereitungshinweise nur noch aufklappbar ?>
<details class="vorbereitung-rueckblick">
    <summary>Ablauf und Hinweise aus der Vorbereitung<?= $hinweise ? ' (' . count($hinweise) . ' Hinweise)' : '' ?></summary>
<?php endif; ?>
<div class="zwei-spalten">
    <div class="karte">
        <h2>Ablauf <?= info('ablauf') ?></h2>
        <ol>
            <li><a href="<?= e(url("turnier/{$turnier['id']}/klassen")) ?>">Klassen</a> aus Vorlage laden oder anlegen</li>
            <li><a href="<?= e(url("turnier/{$turnier['id']}/teilnehmer")) ?>">Teilnehmer</a> erfassen oder <a href="<?= e(url("turnier/{$turnier['id']}/import")) ?>">importieren</a>, <a href="<?= e(url("turnier/{$turnier['id']}/teams")) ?>">Teams</a> bilden</li>
            <li>Kleine Klassen prüfen und ggf. zusammenlegen</li>
            <li>Auslosen (je Klasse oder alle)</li>
            <li><a href="<?= e(url("turnier/{$turnier['id']}/plan")) ?>">Tatami-Plan</a> erstellen, Listen <a href="<?= e(url("turnier/{$turnier['id']}/druck")) ?>">drucken</a></li>
            <li>Ergebnisse erfassen (Tablet oder PC), Platzierungen bestätigen</li>
        </ol>
    </div>
    <div class="karte">
        <h2>Hinweise <?= info('hinweise') ?></h2>
        <?php if (!$hinweise): ?>
            <p class="leise">Keine offenen Hinweise.</p>
        <?php else: ?>
            <ul class="hinweisliste">
                <?php foreach (array_slice($hinweise, 0, 25) as $h): ?>
                    <li><span class="tag <?= $h['typ'] === 'fehler' ? 'fehler' : 'warn' ?>"><?= $h['typ'] === 'fehler' ? 'Fehler' : 'Hinweis' ?></span>
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
            <?php if (count($hinweise) > 25): ?><p class="klein">… und <?= count($hinweise) - 25 ?> weitere</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($gestartet): ?></details><?php endif; ?>
