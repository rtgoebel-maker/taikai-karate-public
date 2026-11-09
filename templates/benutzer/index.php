<?php
/**
 * @var list<array<string, mixed>> $konten
 * @var list<array{id: int|string, name: string}> $vereine
 * @var array{id: int, name: string, admin: bool, turniere: list<int>} $ich
 */
use Wettkampf\Identitaet\Konten;

$this->layout('layout', ['titel' => 'Benutzer']);
$min = Konten::MIN_PASSWORT;
$dojoAuswahl = static function (?int $gewaehlt) use ($vereine): string {
    $html = '<select name="verein_id"><option value="">– kein Dojo –</option>';
    foreach ($vereine as $v) {
        $html .= '<option value="' . (int) $v['id'] . '"' . sel((int) $v['id'], (int) $gewaehlt) . '>' . e($v['name']) . '</option>';
    }
    return $html . '</select>';
};
?>
<div class="seitenkopf">
    <h1>Konten <span class="leise klein">(<?= count($konten) ?>)</span></h1>
    <a class="knopf primaer" href="#neues-konto" data-aufklappen="neues-konto">+ Neues Konto</a>
</div>
<p class="leise">Angemeldet wird mit der <strong>E-Mail-Adresse</strong>. <strong>Administration</strong>: alle Turniere, Konten, Vereine und System.
    <strong>Turnierleitung</strong> wird je Turnier vergeben (Turnier → Einstellungen → Turnierleitung). Ein Konto mit <strong>Dojo</strong> ist ein Coach-Konto
    („Meine Meldungen“, Coaching-Portal). Die Tischbesatzung braucht kein Konto – ihre Geräte werden per QR-Code gekoppelt (Turnier → Einstellungen → Geräte koppeln).</p>

<details class="karte neues-konto" id="neues-konto">
    <summary><h2>Neues Konto anlegen</h2></summary>
<form method="post" action="<?= e(url('benutzer')) ?>" class="formular" style="max-width:640px">
    <?= csrf_feld() ?>
    <div class="zeile">
        <label>Vorname <input name="vorname" required maxlength="100"></label>
        <label>Nachname <input name="nachname" required maxlength="100"></label>
    </div>
    <label>E-Mail (Benutzername) <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
    <label>Telefon <span class="hilfe">optional</span> <input type="tel" name="telefon" maxlength="50"></label>
    <label>Dojo <span class="hilfe">für Coach-Konten</span> <?= $dojoAuswahl(null) ?></label>
    <label>Passwort (mind. <?= $min ?> Zeichen) <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="admin" value="1"> Administration (alle Turniere, Konten, Vereine, System)</label>
    <p class="hilfe">Das Passwort bitte persönlich bzw. auf sicherem Weg weitergeben. Bei der ersten Anmeldung richtet das Konto den zweiten Faktor ein
        (Authenticator-App bzw. Code per E-Mail); Passwort und Angaben lassen sich danach unter „Konto &amp; Sicherheit“ selbst ändern.</p>
    <div><button class="knopf primaer" type="submit">Anlegen</button></div>
</form>
</details>

<?php if (count($konten) > 8): ?>
    <label class="suche-konten">Suchen <input type="search" data-tabellenfilter="konten-liste" placeholder="Name, E-Mail oder Dojo" autocomplete="off"></label>
<?php endif; ?>

<table class="tabelle" id="konten-liste">
    <thead><tr><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Dojo</th><th>Rechte</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($konten as $k): $id = (int) $k['id']; $istIch = $id === (int) $ich['id']; ?>
        <tr<?= $k['aktiv'] ? '' : ' class="inaktiv"' ?> data-suche="<?= e(mb_strtolower(trim($k['vorname'] . ' ' . $k['nachname'] . ' ' . $k['name'] . ' ' . $k['email'] . ' ' . ($k['verein'] ?? '')))) ?>">
            <td><?= e(trim($k['vorname'] . ' ' . $k['nachname']) ?: $k['name']) ?><?= $istIch ? ' <span class="tag">ich</span>' : '' ?></td>
            <td><?= e($k['email']) ?><?= filter_var($k['email'], FILTER_VALIDATE_EMAIL) ? '' : ' <span class="tag warn" title="Altes Konto: bitte eine E-Mail-Adresse eintragen">keine E-Mail</span>' ?></td>
            <td><?= e($k['telefon'] ?? '') ?></td>
            <td><?= e($k['verein'] ?? '') ?></td>
            <td>
                <?= $k['ist_admin'] ? '<span class="tag ok">Administration</span>' : '' ?>
                <?php foreach ($k['turniere'] as $t): ?><span class="tag" title="Turnierleitung">Leitung: <?= e($t) ?></span> <?php endforeach; ?>
            </td>
            <td><?= $k['aktiv'] ? '<span class="tag ok">aktiv</span>' : '<span class="tag">deaktiviert</span>' ?></td>
            <td>
                <details>
                    <summary class="knopf klein">Bearbeiten</summary>
                    <form method="post" action="<?= e(url("benutzer/$id")) ?>" class="formular">
                        <?= csrf_feld() ?>
                        <div class="zeile">
                            <label>Vorname <input name="vorname" value="<?= e($k['vorname']) ?>" required maxlength="100"></label>
                            <label>Nachname <input name="nachname" value="<?= e($k['nachname']) ?>" required maxlength="100"></label>
                        </div>
                        <label>E-Mail <input type="email" name="email" value="<?= e($k['email']) ?>" required maxlength="190"></label>
                        <label>Telefon <input type="tel" name="telefon" value="<?= e($k['telefon'] ?? '') ?>" maxlength="50"></label>
                        <label>Dojo <?= $dojoAuswahl($k['verein_id'] === null ? null : (int) $k['verein_id']) ?></label>
                        <div><button class="knopf klein primaer" type="submit">Speichern</button></div>
                    </form>
                    <form method="post" action="<?= e(url("benutzer/$id/passwort")) ?>" class="formular">
                        <?= csrf_feld() ?>
                        <h3><?= $istIch ? 'Eigenes Passwort ändern' : 'Neues Passwort für dieses Konto' ?></h3>
                        <label>Neues Passwort (mind. <?= $min ?> Zeichen) <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
                        <label>Neues Passwort wiederholen <input type="password" name="passwort2" minlength="<?= $min ?>" required autocomplete="new-password"></label>
                        <?php if ($istIch): ?>
                            <label>Bisheriges Passwort <input type="password" name="bisheriges_passwort" required autocomplete="current-password"></label>
                        <?php else: ?>
                            <p class="hilfe">Das alte Passwort wird nicht benötigt. Das neue Passwort bitte persönlich weitergeben.</p>
                        <?php endif; ?>
                        <div><button class="knopf klein" type="submit">Passwort setzen</button></div>
                    </form>
                    <?php if ((int) ($k['app'] ?? 0) === 1): ?>
                        <form method="post" action="<?= e(url("benutzer/$id/app-zuruecksetzen")) ?>" class="knopfleiste" data-bestaetigen="Authenticator-App dieses Kontos zurücksetzen? Nur tun, wenn die Person sicher erkannt ist (z. B. persönlich oder per Rückruf).">
                            <?= csrf_feld() ?>
                            <span class="klein leise">Authenticator-App eingerichtet</span>
                            <button class="knopf klein" type="submit">App zurücksetzen</button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int) ($k['vertraut'] ?? 0) > 0): ?>
                        <form method="post" action="<?= e(url("benutzer/$id/vertrauen-zuruecksetzen")) ?>" class="knopfleiste">
                            <?= csrf_feld() ?>
                            <span class="klein leise"><?= (int) $k['vertraut'] === 1 ? '1 vertrautes Gerät' : (int) $k['vertraut'] . ' vertraute Geräte' ?> (Login ohne Anmeldecode)</span>
                            <button class="knopf klein" type="submit">Vertraute Geräte zurücksetzen</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$istIch): ?>
                        <div class="knopfleiste">
                            <form method="post" action="<?= e(url("benutzer/$id/admin")) ?>">
                                <?= csrf_feld() ?>
                                <input type="hidden" name="admin" value="<?= $k['ist_admin'] ? '0' : '1' ?>">
                                <button class="knopf klein" type="submit"><?= $k['ist_admin'] ? 'Administration entziehen' : 'Zur Administration machen' ?></button>
                            </form>
                            <form method="post" action="<?= e(url("benutzer/$id/aktiv")) ?>">
                                <?= csrf_feld() ?>
                                <button class="knopf klein" type="submit"><?= $k['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                            </form>
                        </div>
                        <?php if (!$k['aktiv']): ?>
                            <form method="post" action="<?= e(url("benutzer/$id/loeschen")) ?>" class="formular gefahrzone" data-bestaetigen="Konto endgültig löschen? Das lässt sich nicht rückgängig machen.">
                                <?= csrf_feld() ?>
                                <strong>Endgültig löschen</strong>
                                <p class="hilfe">Entfernt das Konto mit Turnierleitungen und Zuordnungen; die E-Mail-Adresse ist danach wieder frei. Der Änderungsverlauf bleibt erhalten.</p>
                                <label>Zur Bestätigung die E-Mail-Adresse eingeben <input name="bestaetigung" autocomplete="off" placeholder="<?= e((string) ($k['email'] ?? '')) ?>"></label>
                                <div><button class="knopf klein gefahr" type="submit">Konto endgültig löschen</button></div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </details>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

