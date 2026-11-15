<?php
/**
 * System → Gesperrte Anmeldungen (Administration).
 * @var list<array{schluessel: string, art: string, wer: string, ip: string|null, alle_ips: bool, versuche: int, sperren: int, gesperrt_bis: string|null, geaendert_am: string, aktiv: bool}> $eintraege
 * @var list<array{zeit: string, aktion: string, von: string, art: string, wer: string, ip: string|null, sekunden: int|null}> $verlauf
 */
use Wettkampf\Identitaet\AnmeldeSchutz;

$this->layout('layout', ['titel' => t('Gesperrte Anmeldungen')]);
$zeit = static fn(string $iso): string => datum_de($iso) . ' ' . substr($iso, 11, 5);
$aktive = count(array_filter($eintraege, static fn($e) => $e['aktiv']));
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>"><?= te('System') ?></a></p>
<h1><?= te('Gesperrte Anmeldungen') ?></h1>
<p class="leise"><?= te('Nach {n} falschen Eingaben (Passwort, Tatami-PIN, Leitungs-PIN, Einrichtungscode) ist die Anmeldung für diese Kombination aus Ziel und IP-Adresse gesperrt – erst 1 Minute, bei jeder weiteren Sperre doppelt so lange, höchstens 30 Minuten.', ['n' => AnmeldeSchutz::MAX_VERSUCHE]) ?>
    <?= te('Kommen sehr viele Fehlversuche von verschiedenen IPs, pausiert das Ziel für alle kurz (5 Minuten).') ?>
    <?= te('In der Halle teilen sich oft alle Tablets eine IP – dann trifft eine Sperre alle Geräte dort.') ?></p>

<section class="karte">
    <h2><?= te('Aktuell ({n} gesperrt)', ['n' => $aktive]) ?></h2>
    <?php if (!$eintraege): ?>
        <p class="leise"><?= te('Keine Sperren und keine offenen Fehlversuche.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Status') ?></th><th><?= te('Wofür') ?></th><th><?= te('Wer / was') ?></th><th><?= te('IP-Adresse') ?></th><th><?= te('Fehlversuche') ?></th><th><?= te('Letzte Änderung') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($eintraege as $e): ?>
                <tr>
                    <td><?php if ($e['aktiv']): ?><span class="tag fehler"><?= te('gesperrt bis {zeit}', ['zeit' => substr((string) $e['gesperrt_bis'], 11, 5)]) ?></span>
                        <?php else: ?><span class="tag warn"><?= te('Fehlversuche') ?></span><?php endif; ?></td>
                    <td><?= e($e['art']) ?></td>
                    <td><?= e($e['wer']) ?></td>
                    <td><?= $e['alle_ips'] ? '<span class="leise">' . te('alle IPs – Pause nach {n} Fehlversuchen in einer Stunde', ['n' => AnmeldeSchutz::MAX_JE_ZIEL]) . '</span>' : '<code>' . e((string) $e['ip']) . '</code>' ?></td>
                    <td><?php if ($e['aktiv']): ?><?= $e['alle_ips'] ? '' : te('{n}. Sperre', ['n' => $e['sperren']]) ?>
                        <?php else: ?><?= te('{n} von {max}', ['n' => $e['versuche'], 'max' => AnmeldeSchutz::MAX_VERSUCHE]) ?><?= $e['sperren'] > 0 ? ' <span class="leise klein">' . te('(schon {n}× gesperrt)', ['n' => $e['sperren']]) . '</span>' : '' ?><?php endif; ?></td>
                    <td class="nowrap"><?= e($zeit($e['geaendert_am'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('system/sperren/aufheben')) ?>" class="inline"
                              data-bestaetigen="<?= $e['aktiv'] ? te('Sperre aufheben? Von dort sind sofort wieder Anmeldeversuche möglich.') : te('Fehlversuche zurücksetzen?') ?>">
                            <?= csrf_feld() ?>
                            <input type="hidden" name="schluessel" value="<?= e($e['schluessel']) ?>">
                            <button class="knopf klein" type="submit"><?= $e['aktiv'] ? te('Aufheben') : te('Zurücksetzen') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="hilfe"><?= te('IP-Adressen werden hier nur gespeichert, solange sie gebraucht werden: Einträge ohne neue Fehlversuche verschwinden nach {n} Stunden.', ['n' => intdiv(AnmeldeSchutz::AUFBEWAHRUNG_SEKUNDEN, 3600)]) ?>
        <?= te('Unbekannte IPs, die viele Konten durchprobieren, sind meist automatisierte Angriffe – die Sperre greift dann von selbst; Aufheben ist nur nötig, wenn sich jemand Bekanntes ausgesperrt hat.') ?></p>
</section>

<section class="karte">
    <h2><?= te('Verlauf (30 Tage)') ?></h2>
    <?php if (!$verlauf): ?>
        <p class="leise"><?= te('In den letzten 30 Tagen keine Sperren.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Zeit') ?></th><th><?= te('Ereignis') ?></th><th><?= te('Wofür') ?></th><th><?= te('Wer / was') ?></th><th><?= te('IP-Adresse') ?></th></tr></thead>
            <tbody>
            <?php foreach ($verlauf as $v): ?>
                <tr>
                    <td class="nowrap"><?= e($zeit($v['zeit'])) ?></td>
                    <td><?= match ($v['aktion']) {
                        'gesperrt' => '<span class="tag fehler">' . te('gesperrt') . '</span>' . ($v['sekunden'] ? ' <span class="leise klein">' . te('{n} Min.', ['n' => max(1, intdiv($v['sekunden'], 60))]) . '</span>' : ''),
                        'pausiert' => '<span class="tag warn">' . te('Pause für alle IPs') . '</span>',
                        default => '<span class="tag ok">' . te('aufgehoben') . '</span>' . ($v['von'] !== '' ? ' <span class="leise klein">' . te('von {name}', ['name' => $v['von']]) . '</span>' : ''),
                    } ?></td>
                    <td><?= e($v['art']) ?></td>
                    <td><?= e($v['wer']) ?></td>
                    <td><?= $v['ip'] !== null ? '<code>' . e($v['ip']) . '</code>' : '<span class="leise">–</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="hilfe"><?= te('Aus dem Änderungsverlauf; Einträge ohne Turnierbezug werden dort nach 90 Tagen gelöscht.') ?></p>
</section>
