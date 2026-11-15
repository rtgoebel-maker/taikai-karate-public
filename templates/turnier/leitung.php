<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $leitung
 * @var array{id: int, name: string, admin: bool, turniere: list<int>} $ich
 * @var bool $istAdmin
 */
use Wettkampf\Identitaet\Konten;

$this->layout('layout', ['titel' => t('Turnierleitung'), 'turnier' => $turnier]);
$tid = (int) $turnier['id'];
$min = Konten::MIN_PASSWORT;
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>"><?= te('Einstellungen') ?></a></p>
<h1><?= te('Turnierleitung') ?></h1>
<p class="leise"><?= te('Die Turnierleitung hat alle Rechte in diesem Turnier – Meldungen, Klassen, Auslosung, Plan, Ergebnisse, Druck und Einstellungen – und kann weitere Turnierleitungen ernennen. Neue Turniere anlegen, löschen sowie Konten und Vereine verwalten kann nur die Administration.') ?></p>

<table class="tabelle">
    <thead><tr><th><?= te('Name') ?></th><th><?= te('E-Mail') ?></th><th><?= te('Telefon') ?></th><th><?= te('Ernannt') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($leitung as $l): $istIch = (int) $l['id'] === (int) $ich['id']; ?>
        <tr<?= $l['aktiv'] ? '' : ' class="inaktiv"' ?>>
            <td><?= e(trim($l['vorname'] . ' ' . $l['nachname']) ?: $l['name']) ?><?= $istIch ? ' <span class="tag">' . te('ich') . '</span>' : '' ?><?= $l['aktiv'] ? '' : ' <span class="tag">' . te('deaktiviert') . '</span>' ?></td>
            <td><?= e($l['email']) ?></td>
            <td><?= e($l['telefon'] ?? '') ?></td>
            <td class="klein"><?= e(datum_de($l['erstellt_am'])) ?><?= $l['erstellt_von'] ? ' · ' . e($l['erstellt_von']) : '' ?></td>
            <td>
                <?php if (!$istIch || $istAdmin): ?>
                    <form method="post" action="<?= e(url("turnier/$tid/leitung/{$l['id']}/entfernen")) ?>" data-bestaetigen="<?= te('Turnierleitung entziehen?') ?>">
                        <?= csrf_feld() ?>
                        <button class="knopf klein" type="submit"><?= te('Entziehen') ?></button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$leitung): ?><tr><td colspan="5" class="leise"><?= te('Noch keine Turnierleitung – bisher kann nur die Administration dieses Turnier bearbeiten.') ?></td></tr><?php endif; ?>
    </tbody>
</table>

<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/leitung")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Bestehendes Konto ernennen') ?></h2>
        <label><?= te('E-Mail des Kontos') ?> <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
        <div><button class="knopf primaer" type="submit"><?= te('Ernennen') ?></button></div>
    </form>
    <form method="post" action="<?= e(url("turnier/$tid/leitung")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <input type="hidden" name="neu" value="1">
        <h2><?= te('Neues Konto anlegen und ernennen') ?></h2>
        <div class="zeile">
            <label><?= te('Vorname') ?> <input name="vorname" required maxlength="100"></label>
            <label><?= te('Nachname') ?> <input name="nachname" required maxlength="100"></label>
        </div>
        <label><?= te('E-Mail (Benutzername)') ?> <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
        <label><?= te('Telefon') ?> <span class="hilfe"><?= te('optional') ?></span> <input type="tel" name="telefon" maxlength="50"></label>
        <label><?= te('Passwort (mind. {n} Zeichen)', ['n' => $min]) ?> <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
        <p class="hilfe"><?= te('Zugangsdaten bitte vorerst persönlich weitergeben – Einladungen per E-Mail folgen später.') ?></p>
        <div><button class="knopf" type="submit"><?= te('Anlegen und ernennen') ?></button></div>
    </form>
</div>
