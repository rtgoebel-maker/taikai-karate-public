<?php
/**
 * Freiwillige Einladungen zu künftigen Turnieren (Administration): Liste, Export, Versand je Turnier.
 * @var list<array<string, mixed>> $liste
 * @var list<array<string, mixed>> $turniere kommende Turniere
 * @var bool $mail E-Mail-Versand eingerichtet
 */
use Wettkampf\Meldung\Einladungen;

$this->layout('layout', ['titel' => 'Einladungen']);
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>">System</a></p>
<h1>Einladungen zu künftigen Turnieren</h1>
<p class="leise">Adressen, deren Inhaber bei einer Online-Anmeldung freiwillig eingewilligt haben (Art. 6 Abs. 1 lit. a DSGVO). Die Checkbox erscheint nur bei
    Turnieren, die sie unter <strong>Einstellungen → Datenschutz</strong> anbieten. Jede Einladung enthält einen persönlichen Abmeldelink; ein Widerruf löscht den Eintrag.
    Ohne erneute Einwilligung wird ein Eintrag nach <?= Einladungen::GUELTIG_MONATE ?> Monaten gelöscht. Nur für Einladungen verwenden – nicht weitergeben.</p>

<section class="karte">
    <h2>Einladung versenden</h2>
    <?php if (!$mail): ?>
        <div class="meldung meldung-hinweis">E-Mail-Versand ist nicht eingerichtet (System → E-Mail-Versand). Alternativ die Liste exportieren – mit Abmeldelink je Adresse.</div>
    <?php elseif (!$turniere): ?>
        <p class="leise">Kein kommendes Turnier angelegt.</p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>Turnier</th><th>Datum</th><th>Versendet</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($turniere as $t): $versendet = (json_decode((string) $t['einstellungen'], true) ?: [])['einladungen_versendet_am'] ?? null; ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td><?= e(datum_de($t['datum'])) ?></td>
                    <td><?= $versendet ? e(date('d.m.Y H:i', (int) strtotime((string) $versendet))) : '–' ?></td>
                    <td class="rechts">
                        <?php if (!$versendet && $liste): ?>
                            <form method="post" action="<?= e(url("einladungen/turnier/{$t['id']}")) ?>" data-bestaetigen="Einladung zu „<?= e($t['name']) ?>“ an <?= count($liste) ?> Adressen senden?" data-bestaetigen-ja="Senden">
                                <?= csrf_feld() ?><button class="knopf klein primaer" type="submit">Einladung senden</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="hilfe">Die Mail enthält Name, Datum, Ort und Meldeschluss des Turniers, den Anmeldelink, die Datenschutzhinweise und den Abmeldelink. Je Turnier einmal.</p>
    <?php endif; ?>
</section>

<section class="karte">
    <div class="titelzeile">
        <h2>Eingewilligte Adressen (<?= count($liste) ?>)</h2>
        <?php if ($liste): ?><a class="knopf" href="<?= e(url('einladungen/export')) ?>">Als CSV exportieren</a><?php endif; ?>
    </div>
    <?php if (!$liste): ?>
        <p class="leise">Noch keine Einwilligungen.</p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>E-Mail</th><th>Name</th><th>Dojo</th><th>Eingewilligt</th><th>Herkunft</th><th>Zuletzt eingeladen</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $z): ?>
                <tr>
                    <td><?= e($z['email']) ?></td><td><?= e($z['name']) ?></td><td><?= e($z['dojo']) ?></td>
                    <td class="klein"><?= e(date('d.m.Y', (int) strtotime((string) $z['eingewilligt_am']))) ?></td>
                    <td class="klein"><?= e($z['herkunft']) ?></td>
                    <td class="klein"><?= $z['zuletzt_eingeladen_am'] ? e(date('d.m.Y', (int) strtotime((string) $z['zuletzt_eingeladen_am']))) : '–' ?></td>
                    <td class="rechts">
                        <form method="post" action="<?= e(url("einladungen/{$z['id']}/loeschen")) ?>" data-bestaetigen="<?= e($z['email']) ?> löschen (Widerruf)?" data-bestaetigen-ja="Löschen">
                            <?= csrf_feld() ?><button class="knopf klein gefahr" type="submit">Löschen</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
