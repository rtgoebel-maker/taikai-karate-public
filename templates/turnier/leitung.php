<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $leitung
 * @var array{id: int, name: string, admin: bool, turniere: list<int>} $ich
 * @var bool $istAdmin
 */
use Wettkampf\Identitaet\Konten;

$this->layout('layout', ['titel' => 'Turnierleitung', 'turnier' => $turnier]);
$tid = (int) $turnier['id'];
$min = Konten::MIN_PASSWORT;
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>">Einstellungen</a></p>
<h1>Turnierleitung</h1>
<p class="leise">Die Turnierleitung hat alle Rechte in diesem Turnier – Meldungen, Klassen, Auslosung, Plan, Ergebnisse, Druck und Einstellungen –
    und kann weitere Turnierleitungen ernennen. Neue Turniere anlegen, löschen sowie Konten und Vereine verwalten kann nur die Administration.</p>

<table class="tabelle">
    <thead><tr><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Ernannt</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($leitung as $l): $istIch = (int) $l['id'] === (int) $ich['id']; ?>
        <tr<?= $l['aktiv'] ? '' : ' class="inaktiv"' ?>>
            <td><?= e(trim($l['vorname'] . ' ' . $l['nachname']) ?: $l['name']) ?><?= $istIch ? ' <span class="tag">ich</span>' : '' ?><?= $l['aktiv'] ? '' : ' <span class="tag">deaktiviert</span>' ?></td>
            <td><?= e($l['email']) ?></td>
            <td><?= e($l['telefon'] ?? '') ?></td>
            <td class="klein"><?= e(datum_de($l['erstellt_am'])) ?><?= $l['erstellt_von'] ? ' · ' . e($l['erstellt_von']) : '' ?></td>
            <td>
                <?php if (!$istIch || $istAdmin): ?>
                    <form method="post" action="<?= e(url("turnier/$tid/leitung/{$l['id']}/entfernen")) ?>" data-bestaetigen="Turnierleitung entziehen?">
                        <?= csrf_feld() ?>
                        <button class="knopf klein" type="submit">Entziehen</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$leitung): ?><tr><td colspan="5" class="leise">Noch keine Turnierleitung – bisher kann nur die Administration dieses Turnier bearbeiten.</td></tr><?php endif; ?>
    </tbody>
</table>

<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/leitung")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Bestehendes Konto ernennen</h2>
        <label>E-Mail des Kontos <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
        <div><button class="knopf primaer" type="submit">Ernennen</button></div>
    </form>
    <form method="post" action="<?= e(url("turnier/$tid/leitung")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <input type="hidden" name="neu" value="1">
        <h2>Neues Konto anlegen und ernennen</h2>
        <div class="zeile">
            <label>Vorname <input name="vorname" required maxlength="100"></label>
            <label>Nachname <input name="nachname" required maxlength="100"></label>
        </div>
        <label>E-Mail (Benutzername) <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
        <label>Telefon <span class="hilfe">optional</span> <input type="tel" name="telefon" maxlength="50"></label>
        <label>Passwort (mind. <?= $min ?> Zeichen) <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
        <p class="hilfe">Zugangsdaten bitte vorerst persönlich weitergeben – Einladungen per E-Mail folgen später.</p>
        <div><button class="knopf" type="submit">Anlegen und ernennen</button></div>
    </form>
</div>
