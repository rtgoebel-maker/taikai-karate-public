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

$this->layout('layout', ['titel' => t('Meine Meldungen'), 'aktiv' => 'coach']);
?>
<p class="obertitel"><?= $verein !== null ? 'Coach · ' . e($verein) : te('Dojo-Zugang') ?></p>
<h1><?= te('Meine Meldungen') ?></h1>
<?php if ($ablauf !== null): ?>
    <div class="meldung meldung-hinweis"><?= $gebundenAn !== null
        ? th('Ihr Zugang gilt für <strong>{turnier}</strong> bis <strong>{datum}</strong> und wird danach automatisch gelöscht.', ['turnier' => $gebundenAn, 'datum' => datum_de($ablauf)])
        : th('Ihr Zugang gilt bis <strong>{datum}</strong> und wird danach automatisch gelöscht.', ['datum' => datum_de($ablauf)]) ?>
        <?= $gebundenAn !== null ? te('Für ein weiteres Turnier bitte dort ohne Anmeldung melden (abmelden) – dieser Zugang gilt nur für das Turnier, für das er angelegt wurde.') : te('Für jedes weitere Turnier ist eine neue Anmeldung nötig – angemeldet abgeschickt verlängert sie den Zugang.') ?>
        <?php if ($verein === null): ?><br><?= te('Starter, Ergebnisse und Coaching-Portal Ihres Dojos erscheinen, sobald die Wettkampfleitung den Zugang bei der Freigabe Ihrem Dojo zuordnet.') ?><?php endif; ?></div>
<?php endif; ?>

<?php if ($portal): ?>
<section class="karte">
    <h2><?= te('Coaching-Portal') ?></h2>
    <p class="hilfe"><?= te('Turniere, an denen {verein} teilnimmt und deren Turnierleitung das Portal freigegeben hat.', ['verein' => $verein]) ?></p>
    <table class="tabelle">
        <thead><tr><th><?= te('Datum') ?></th><th><?= te('Turnier') ?></th><th class="rechts"><?= te('Starter') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($portal as $t): ?>
            <tr>
                <td><?= e(datum_de($t['datum'])) ?></td>
                <td><strong><?= e($t['name']) ?></strong><br><span class="klein"><?= e(TurnierPhase::text(TurnierPhase::von($t))) ?></span></td>
                <td class="rechts zahl"><?= (int) $t['starter'] ?><?= (int) $t['teams'] ? te(' + {n} Teams', ['n' => (int) $t['teams']]) : '' ?></td>
                <td class="knopfleiste">
                    <?php foreach (\Wettkampf\Meldung\CoachService::BEREICHE as $b => $text): ?>
                        <?php if ($t['portal']['bereiche'][$b]): ?><a class="knopf klein<?= $b === 'starter' ? ' primaer' : '' ?>" href="<?= e(url("coach/turnier/{$t['id']}/$b")) ?>"><?= e(\Wettkampf\Kern\Sprache::t($text)) ?></a><?php endif; ?>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="karte">
    <h2><?= te('Online-Anmeldung offen') ?></h2>
    <?php if (!$offen): ?>
        <p class="leise"><?= te('Zurzeit ist bei keinem Turnier die Online-Anmeldung offen.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Datum') ?></th><th><?= te('Turnier') ?></th><th><?= te('Meldeschluss') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($offen as $t): ?>
                <tr>
                    <td><?= e(datum_de($t['datum'])) ?></td>
                    <td><strong><?= e($t['name']) ?></strong><?= $t['ort'] ? ' · ' . e($t['ort']) : '' ?></td>
                    <td><?= e(datum_de($t['meldeschluss'] ?? '')) ?></td>
                    <td><a class="knopf klein primaer" href="<?= e($t['anmeldelink']) ?>"><?= $verein !== null ? te('Neue Meldung für {verein}', ['verein' => $verein]) : te('Neue Meldung') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="karte">
    <h2><?= $verein !== null ? te('Meldungen von {verein}', ['verein' => $verein]) : te('Ihre Meldungen') ?></h2>
    <?php if (!$meldungen): ?>
        <p class="leise"><?= te('Noch keine Meldungen. Meldungen, die ohne Konto abgegeben wurden, erscheinen hier, sobald die Turnierleitung sie freigegeben und dem Dojo zugeordnet hat.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Turnier') ?></th><th><?= te('Stand') ?></th><th class="rechts"><?= te('Teilnehmer') ?></th><th class="rechts"><?= te('Teams') ?></th><th class="rechts"><?= te('Startgeld') ?></th><th><?= te('Eingereicht') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($meldungen as $m): ?>
                <tr>
                    <td><strong><?= e($m['turnier']) ?></strong><br><span class="klein"><?= e(datum_de($m['datum'])) ?> · <?= e(TurnierPhase::text(TurnierPhase::von(['status' => $m['phase']]))) ?><?= $verein === null ? te(' · Dojo: {dojo}', ['dojo' => $m['dojo']]) : '' ?></span></td>
                    <td><span class="tag <?= e($m['stand']['art']) ?>"><?= e($m['stand']['kurz']) ?></span><?= $m['status'] === 'eingereicht' ? '<br><span class="klein">' . te('noch änderbar') . '</span>' : '' ?>
                        <?= $m['stand']['offen'] > 0 && $m['status'] !== 'abgelehnt' ? '<br><span class="klein">' . te('offen: {betrag}', ['betrag' => AnmeldungService::euro($m['stand']['offen'])]) . '</span>' : '' ?>
                        <?= $m['grund'] ? '<br><span class="klein">' . e($m['grund']) . '</span>' : '' ?>
                        <details class="klein"><summary><?= te('Bearbeitungsstand') ?></summary><?= $this->teil('teile/anmeldestand', ['stand' => $m['stand']]) ?></details></td>
                    <td class="rechts zahl"><?= (int) $m['teilnehmer'] ?></td>
                    <td class="rechts zahl"><?= (int) $m['teams'] ?></td>
                    <td class="rechts zahl"><?= e(AnmeldungService::euro((int) $m['preis_cent'])) ?></td>
                    <td class="klein"><?= e(datum_de($m['geaendert_am'] ?? $m['eingereicht_am'])) ?></td>
                    <td class="knopfleiste">
                        <a class="knopf klein" href="<?= e(url("anmeldung/{$m['turnier_id']}/{$m['code']}")) ?>"><?= $m['status'] === 'eingereicht' ? te('Ansehen / ändern') : te('Ansehen') ?></a>
                        <a class="knopf klein" href="<?= e(url("anmeldung/{$m['turnier_id']}/{$m['code']}/uebersicht")) ?>"><?= te('Übersicht & Zahlung') ?></a>
                        <?php if ((int) $m['oeffentlich'] && TurnierPhase::gestartet(['status' => $m['phase']])): ?>
                            <a class="knopf klein" href="<?= e(url("live/{$m['turnier_id']}")) ?>"><?= te('Statusseite') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
