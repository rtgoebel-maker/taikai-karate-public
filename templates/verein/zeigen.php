<?php
/**
 * Vereinsseite (Administration): Teilnehmer und ihre Turniere, Coach-Konten, Online-Anmeldungen.
 * Datensparsam: je Person nur Name, Jahrgang, Graduierung, Turniere.
 * @var array<string, mixed> $verein
 * @var list<array{id: int, name: string, jahrgang: string|null, graduierung: int, turniere: list<array<string, mixed>>}> $personen
 * @var list<array<string, mixed>> $konten
 * @var list<array<string, mixed>> $anmeldungen
 * @var list<array<string, mixed>> $turniere
 */
use Wettkampf\Domain\Graduierung;

$this->layout('layout', ['titel' => $verein['name']]);
$vid = (int) $verein['id'];
$status = ['gemeldet' => '', 'gesperrt' => t('gesperrt||Teilnehmer'), 'abgemeldet' => t('abgemeldet'), 'nicht_erschienen' => t('nicht erschienen')];
?>
<p class="obertitel"><a href="<?= e(url('vereine')) ?>"><?= te('Vereine') ?></a></p>
<h1><?= e($verein['name']) ?><?= $verein['kurzname'] ? ' <span class="leise klein">(' . e($verein['kurzname']) . ')</span>' : '' ?></h1>
<p class="leise"><?= count($turniere) === 1 ? te('{n} Teilnehmer in 1 Turnier', ['n' => count($personen)]) : te('{n} Teilnehmer in {t} Turnieren', ['n' => count($personen), 't' => count($turniere)]) ?> ·
    <?= count($konten) === 1 ? te('1 Coach-Konto') : te('{n} Coach-Konten', ['n' => count($konten)]) ?> · <?= count($anmeldungen) === 1 ? te('1 Online-Anmeldung') : te('{n} Online-Anmeldungen', ['n' => count($anmeldungen)]) ?></p>

<div class="system-raster">
    <section class="karte">
        <h2><?= te('Turniere') ?></h2>
        <?php if (!$turniere): ?><p class="leise"><?= te('In keinem gespeicherten Turnier gemeldet.') ?></p><?php endif; ?>
        <ul class="schlicht">
            <?php foreach ($turniere as $t): ?>
                <li><a href="<?= e(url("turnier/{$t['id']}/teilnehmer", ['verein' => $vid])) ?>"><?= e($t['name']) ?></a>
                    <span class="leise klein"><?= e(datum_de($t['datum'])) ?> · <?= te('{n} Teilnehmer', ['n' => (int) $t['personen']]) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="karte">
        <h2><?= te('Coach-Konten') ?></h2>
        <?php if (!$konten): ?><p class="leise"><?= th('Keine – ein Konto mit diesem Dojo legen Sie unter <a href="{url}">Benutzer</a> an.', ['url' => url('benutzer')]) ?></p><?php endif; ?>
        <ul class="schlicht">
            <?php foreach ($konten as $k): ?>
                <li><?= e($k['name']) ?> <span class="leise klein"><?= e((string) $k['email']) ?></span><?= $k['aktiv'] ? '' : ' <span class="tag">' . te('deaktiviert') . '</span>' ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($anmeldungen): ?>
            <h2><?= te('Online-Anmeldungen') ?></h2>
            <ul class="schlicht">
                <?php foreach ($anmeldungen as $a): ?>
                    <li><a href="<?= e(url("turnier/{$a['turnier_id']}/anmeldung/{$a['id']}")) ?>"><?= e($a['turnier']) ?></a>
                        <span class="leise klein"><?= e(datum_de((string) $a['eingereicht_am'])) ?> · <?= e($a['ansprechpartner']) ?> · <?= e($a['status']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="karte">
    <div class="seitenkopf">
        <h2><?= te('Teilnehmer ({n})', ['n' => count($personen)]) ?></h2>
        <?php if (count($personen) > 8): ?>
            <input type="search" data-tabellenfilter="verein-personen" placeholder="<?= te('Name suchen') ?>" autocomplete="off" style="max-width:260px">
        <?php endif; ?>
    </div>
    <p class="hilfe"><?= te('Nur Name, Jahrgang, Graduierung und Turniere – Geburtsdaten und Ausweisnummern stehen im jeweiligen Turnier. Ein Klick auf das Turnier öffnet den Teilnehmer dort.') ?></p>
    <table class="tabelle" id="verein-personen">
        <thead><tr><th><?= te('Name') ?></th><th><?= te('Jahrgang') ?></th><th><?= te('Graduierung') ?></th><th><?= te('Turniere') ?></th></tr></thead>
        <tbody>
        <?php foreach ($personen as $p): ?>
            <tr data-suche="<?= e(mb_strtolower($p['name'])) ?>">
                <td><?= e($p['name']) ?></td>
                <td><?= e((string) $p['jahrgang']) ?></td>
                <td><?= e(Graduierung::text($p['graduierung'])) ?></td>
                <td><?php foreach ($p['turniere'] as $i => $t): ?><?= $i > 0 ? '<br>' : '' ?><a href="<?= e(url("turnier/{$t['id']}/teilnehmer/{$t['teilnahme_id']}")) ?>"><?= e($t['name']) ?></a>
                    <span class="leise klein"><?= e(substr($t['datum'], 0, 4)) ?><?= ($status[$t['status']] ?? $t['status']) !== '' ? ' · ' . e($status[$t['status']] ?? $t['status']) : '' ?></span><?php endforeach; ?>
                    <?= $p['turniere'] ? '' : '<span class="leise">–</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$personen): ?><tr><td colspan="4" class="leise"><?= te('Keine Teilnehmer.') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
