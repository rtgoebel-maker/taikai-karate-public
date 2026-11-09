<?php
/**
 * @var string|null $verein Dojo des Kontos (null = selbst angelegter Zugang, noch nicht zugeordnet)
 * @var string|null $ablauf Zugang gilt bis (selbst angelegt)
 * @var string|null $gebundenAn Turnier(e), für die der Zugang gilt (null = nicht beschränkt)
 * @var list<array<string, mixed>> $offen Turniere mit offener Online-Anmeldung
 * @var list<array<string, mixed>> $meldungen Meldungen des eigenen Dojos
 * @var list<array<string, mixed>> $portal Turniere mit Coaching-Portal, an denen das Dojo teilnimmt
 */
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Meldung\AnmeldungService;

$this->layout('layout', ['titel' => 'Meine Meldungen', 'aktiv' => 'coach']);
?>
<p class="obertitel"><?= $verein !== null ? 'Coach · ' . e($verein) : 'Dojo-Zugang' ?></p>
<h1>Meine Meldungen</h1>
<?php if ($ablauf !== null): ?>
    <div class="meldung meldung-hinweis">Ihr Zugang gilt<?= $gebundenAn !== null ? ' für <strong>' . e($gebundenAn) . '</strong>' : '' ?> bis <strong><?= e(datum_de($ablauf)) ?></strong> und wird danach automatisch gelöscht.
        <?= $gebundenAn !== null ? 'Für ein weiteres Turnier bitte dort ohne Anmeldung melden (abmelden) – dieser Zugang gilt nur für das Turnier, für das er angelegt wurde.' : 'Für jedes weitere Turnier ist eine neue Anmeldung nötig – angemeldet abgeschickt verlängert sie den Zugang.' ?>
        <?php if ($verein === null): ?><br>Starter, Ergebnisse und Coaching-Portal Ihres Dojos erscheinen, sobald die Wettkampfleitung den Zugang bei der Freigabe Ihrem Dojo zuordnet.<?php endif; ?></div>
<?php endif; ?>

<?php if ($portal): ?>
<section class="karte">
    <h2>Coaching-Portal</h2>
    <p class="hilfe">Turniere, an denen <?= e($verein) ?> teilnimmt und deren Turnierleitung das Portal freigegeben hat.</p>
    <table class="tabelle">
        <thead><tr><th>Datum</th><th>Turnier</th><th class="rechts">Starter</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($portal as $t): ?>
            <tr>
                <td><?= e(datum_de($t['datum'])) ?></td>
                <td><strong><?= e($t['name']) ?></strong><br><span class="klein"><?= e(TurnierPhase::text(TurnierPhase::von($t))) ?></span></td>
                <td class="rechts zahl"><?= (int) $t['starter'] ?><?= (int) $t['teams'] ? ' + ' . (int) $t['teams'] . ' Teams' : '' ?></td>
                <td class="knopfleiste">
                    <?php foreach (\Wettkampf\Meldung\CoachService::BEREICHE as $b => $text): ?>
                        <?php if ($t['portal']['bereiche'][$b]): ?><a class="knopf klein<?= $b === 'starter' ? ' primaer' : '' ?>" href="<?= e(url("coach/turnier/{$t['id']}/$b")) ?>"><?= e($text) ?></a><?php endif; ?>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="karte">
    <h2>Online-Anmeldung offen</h2>
    <?php if (!$offen): ?>
        <p class="leise">Zurzeit ist bei keinem Turnier die Online-Anmeldung offen.</p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th>Datum</th><th>Turnier</th><th>Meldeschluss</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($offen as $t): ?>
                <tr>
                    <td><?= e(datum_de($t['datum'])) ?></td>
                    <td><strong><?= e($t['name']) ?></strong><?= $t['ort'] ? ' · ' . e($t['ort']) : '' ?></td>
                    <td><?= e(datum_de($t['meldeschluss'] ?? '')) ?></td>
                    <td><a class="knopf klein primaer" href="<?= e($t['anmeldelink']) ?>">Neue Meldung<?= $verein !== null ? ' für ' . e($verein) : '' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="karte">
    <h2><?= $verein !== null ? 'Meldungen von ' . e($verein) : 'Ihre Meldungen' ?></h2>
    <?php if (!$meldungen): ?>
        <p class="leise">Noch keine Meldungen. Meldungen, die ohne Konto abgegeben wurden, erscheinen hier, sobald die Turnierleitung sie freigegeben und dem Dojo zugeordnet hat.</p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th>Turnier</th><th>Stand</th><th class="rechts">Teilnehmer</th><th class="rechts">Teams</th><th class="rechts">Startgeld</th><th>Eingereicht</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($meldungen as $m): ?>
                <tr>
                    <td><strong><?= e($m['turnier']) ?></strong><br><span class="klein"><?= e(datum_de($m['datum'])) ?> · <?= e(TurnierPhase::text(TurnierPhase::von(['status' => $m['phase']]))) ?><?= $verein === null ? ' · Dojo: ' . e($m['dojo']) : '' ?></span></td>
                    <td><span class="tag <?= e($m['stand']['art']) ?>"><?= e($m['stand']['kurz']) ?></span><?= $m['status'] === 'eingereicht' ? '<br><span class="klein">noch änderbar</span>' : '' ?>
                        <?= $m['stand']['offen'] > 0 && $m['status'] !== 'abgelehnt' ? '<br><span class="klein">offen: ' . e(AnmeldungService::euro($m['stand']['offen'])) . '</span>' : '' ?>
                        <?= $m['grund'] ? '<br><span class="klein">' . e($m['grund']) . '</span>' : '' ?>
                        <details class="klein"><summary>Bearbeitungsstand</summary><?= $this->teil('teile/anmeldestand', ['stand' => $m['stand']]) ?></details></td>
                    <td class="rechts zahl"><?= (int) $m['teilnehmer'] ?></td>
                    <td class="rechts zahl"><?= (int) $m['teams'] ?></td>
                    <td class="rechts zahl"><?= e(AnmeldungService::euro((int) $m['preis_cent'])) ?></td>
                    <td class="klein"><?= e(datum_de($m['geaendert_am'] ?? $m['eingereicht_am'])) ?></td>
                    <td class="knopfleiste">
                        <a class="knopf klein" href="<?= e(url("anmeldung/{$m['turnier_id']}/{$m['code']}")) ?>"><?= $m['status'] === 'eingereicht' ? 'Ansehen / ändern' : 'Ansehen' ?></a>
                        <a class="knopf klein" href="<?= e(url("anmeldung/{$m['turnier_id']}/{$m['code']}/uebersicht")) ?>">Übersicht &amp; Zahlung</a>
                        <?php if ((int) $m['oeffentlich'] && TurnierPhase::gestartet(['status' => $m['phase']])): ?>
                            <a class="knopf klein" href="<?= e(url("live/{$m['turnier_id']}")) ?>">Statusseite</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
