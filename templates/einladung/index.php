<?php
/**
 * Freiwillige Einladungen zu künftigen Turnieren (Administration): Liste, Export, Versand je Turnier.
 * @var list<array<string, mixed>> $liste
 * @var list<array<string, mixed>> $turniere kommende Turniere
 * @var bool $mail E-Mail-Versand eingerichtet
 */
use Wettkampf\Meldung\Einladungen;

$this->layout('layout', ['titel' => t('Einladungen')]);
$zeit = static fn (string $z): string => datum_de($z) . date(' H:i', (int) strtotime($z));
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>"><?= te('System') ?></a></p>
<h1><?= te('Einladungen zu künftigen Turnieren') ?></h1>
<p class="leise"><?= th('Adressen, deren Inhaber bei einer Online-Anmeldung freiwillig eingewilligt haben (Art. 6 Abs. 1 lit. a DSGVO). Die Checkbox erscheint nur bei Turnieren, die sie unter <strong>Einstellungen → Datenschutz</strong> anbieten. Jede Einladung enthält einen persönlichen Abmeldelink; ein Widerruf löscht den Eintrag. Ohne erneute Einwilligung wird ein Eintrag nach {n} Monaten gelöscht. Nur für Einladungen verwenden – nicht weitergeben.', ['n' => Einladungen::GUELTIG_MONATE]) ?></p>

<section class="karte">
    <h2><?= te('Einladung versenden') ?></h2>
    <?php if (!$mail): ?>
        <div class="meldung meldung-hinweis"><?= te('E-Mail-Versand ist nicht eingerichtet (System → E-Mail-Versand). Alternativ die Liste exportieren – mit Abmeldelink je Adresse.') ?></div>
    <?php elseif (!$turniere): ?>
        <p class="leise"><?= te('Kein kommendes Turnier angelegt.') ?></p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th><?= te('Turnier') ?></th><th><?= te('Datum') ?></th><th><?= te('Versendet') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($turniere as $t): $versendet = (json_decode((string) $t['einstellungen'], true) ?: [])['einladungen_versendet_am'] ?? null; ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td><?= e(datum_de($t['datum'])) ?></td>
                    <td><?= $versendet ? e($zeit((string) $versendet)) : '–' ?></td>
                    <td class="rechts">
                        <?php if (!$versendet && $liste): ?>
                            <form method="post" action="<?= e(url("einladungen/turnier/{$t['id']}")) ?>" data-bestaetigen="<?= te('Einladung zu „{name}“ an {n} Adressen senden?', ['name' => $t['name'], 'n' => count($liste)]) ?>" data-bestaetigen-ja="<?= te('Senden') ?>">
                                <?= csrf_feld() ?><button class="knopf klein primaer" type="submit"><?= te('Einladung senden') ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="hilfe"><?= te('Die Mail enthält Name, Datum, Ort und Meldeschluss des Turniers, den Anmeldelink, die Datenschutzhinweise und den Abmeldelink. Je Turnier einmal.') ?></p>
    <?php endif; ?>
</section>

<section class="karte">
    <div class="titelzeile">
        <h2><?= te('Eingewilligte Adressen ({n})', ['n' => count($liste)]) ?></h2>
        <?php if ($liste): ?><a class="knopf" href="<?= e(url('einladungen/export')) ?>"><?= te('Als CSV exportieren') ?></a><?php endif; ?>
    </div>
    <?php if (!$liste): ?>
        <p class="leise"><?= te('Noch keine Einwilligungen.') ?></p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th><?= te('E-Mail') ?></th><th><?= te('Name') ?></th><th><?= te('Dojo') ?></th><th><?= te('Eingewilligt') ?></th><th><?= te('Herkunft') ?></th><th><?= te('Zuletzt eingeladen') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $z): ?>
                <tr>
                    <td><?= e($z['email']) ?></td><td><?= e($z['name']) ?></td><td><?= e($z['dojo']) ?></td>
                    <td class="klein"><?= e(datum_de((string) $z['eingewilligt_am'])) ?></td>
                    <td class="klein"><?= e($z['herkunft']) ?></td>
                    <td class="klein"><?= $z['zuletzt_eingeladen_am'] ? e(datum_de((string) $z['zuletzt_eingeladen_am'])) : '–' ?></td>
                    <td class="rechts">
                        <form method="post" action="<?= e(url("einladungen/{$z['id']}/loeschen")) ?>" data-bestaetigen="<?= te('{email} löschen (Widerruf)?', ['email' => $z['email']]) ?>" data-bestaetigen-ja="<?= te('Löschen') ?>">
                            <?= csrf_feld() ?><button class="knopf klein gefahr" type="submit"><?= te('Löschen') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
