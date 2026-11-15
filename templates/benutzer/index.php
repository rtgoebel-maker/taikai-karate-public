<?php
/**
 * @var list<array<string, mixed>> $konten
 * @var list<array{id: int|string, name: string}> $vereine
 * @var array{id: int, name: string, admin: bool, turniere: list<int>} $ich
 */
use Wettkampf\Identitaet\Konten;

$this->layout('layout', ['titel' => t('Benutzer')]);
$min = Konten::MIN_PASSWORT;
$dojoAuswahl = static function (?int $gewaehlt) use ($vereine): string {
    $html = '<select name="verein_id"><option value="">' . te('– kein Dojo –') . '</option>';
    foreach ($vereine as $v) {
        $html .= '<option value="' . (int) $v['id'] . '"' . sel((int) $v['id'], (int) $gewaehlt) . '>' . e($v['name']) . '</option>';
    }
    return $html . '</select>';
};
?>
<div class="seitenkopf">
    <h1><?= te('Konten') ?> <span class="leise klein">(<?= count($konten) ?>)</span></h1>
    <a class="knopf primaer" href="#neues-konto" data-aufklappen="neues-konto">+ <?= te('Neues Konto') ?></a>
</div>
<p class="leise"><?= th('Angemeldet wird mit der <strong>E-Mail-Adresse</strong>. <strong>Administration</strong>: alle Turniere, Konten, Vereine und System. <strong>Turnierleitung</strong> wird je Turnier vergeben (Turnier → Einstellungen → Turnierleitung). Ein Konto mit <strong>Dojo</strong> ist ein Coach-Konto („Meine Meldungen“, Coaching-Portal). Die Tischbesatzung braucht kein Konto – ihre Geräte werden per QR-Code gekoppelt (Turnier → Einstellungen → Geräte koppeln).') ?></p>

<details class="karte neues-konto" id="neues-konto">
    <summary><h2><?= te('Neues Konto anlegen') ?></h2></summary>
<form method="post" action="<?= e(url('benutzer')) ?>" class="formular" style="max-width:640px">
    <?= csrf_feld() ?>
    <div class="zeile">
        <label><?= te('Vorname') ?> <input name="vorname" required maxlength="100"></label>
        <label><?= te('Nachname') ?> <input name="nachname" required maxlength="100"></label>
    </div>
    <label><?= te('E-Mail (Benutzername)') ?> <input type="email" name="email" required maxlength="190" autocomplete="off"></label>
    <label><?= te('Telefon') ?> <span class="hilfe"><?= te('optional') ?></span> <input type="tel" name="telefon" maxlength="50"></label>
    <label><?= te('Dojo') ?> <span class="hilfe"><?= te('für Coach-Konten') ?></span> <?= $dojoAuswahl(null) ?></label>
    <label><?= te('Passwort (mind. {min} Zeichen)', ['min' => $min]) ?> <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="admin" value="1"> <?= te('Administration (alle Turniere, Konten, Vereine, System)') ?></label>
    <label class="check"><input type="checkbox" name="demo" value="1"> <?= te('Demo-Zugang (für Schulungen: alle Demo-Turniere und eigene Turniere mit fast allen Funktionen, keine Konten, keine E-Mail-Zugangsdaten, keine Datenbank-Sicherung; ohne zweiten Faktor, Konto nicht änderbar)') ?></label>
    <p class="hilfe"><?= te('Das Passwort bitte persönlich bzw. auf sicherem Weg weitergeben. Bei der ersten Anmeldung richtet das Konto den zweiten Faktor ein (Authenticator-App bzw. Code per E-Mail); Passwort und Angaben lassen sich danach unter „Konto & Sicherheit“ selbst ändern.') ?></p>
    <div><button class="knopf primaer" type="submit"><?= te('Anlegen') ?></button></div>
</form>
</details>

<?php if (count($konten) > 8): ?>
    <label class="suche-konten"><?= te('Suchen') ?> <input type="search" data-tabellenfilter="konten-liste" placeholder="<?= te('Name, E-Mail oder Dojo') ?>" autocomplete="off"></label>
<?php endif; ?>

<table class="tabelle" id="konten-liste">
    <thead><tr><th><?= te('Name') ?></th><th><?= te('E-Mail') ?></th><th><?= te('Telefon') ?></th><th><?= te('Dojo') ?></th><th><?= te('Rechte') ?></th><th><?= te('Status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($konten as $k): $id = (int) $k['id']; $istIch = $id === (int) $ich['id']; ?>
        <tr<?= $k['aktiv'] ? '' : ' class="inaktiv"' ?> data-suche="<?= e(mb_strtolower(trim($k['vorname'] . ' ' . $k['nachname'] . ' ' . $k['name'] . ' ' . $k['email'] . ' ' . ($k['verein'] ?? '')))) ?>">
            <td><?= e(trim($k['vorname'] . ' ' . $k['nachname']) ?: $k['name']) ?><?= $istIch ? ' <span class="tag">' . te('ich') . '</span>' : '' ?></td>
            <td><?= e($k['email']) ?><?= filter_var($k['email'], FILTER_VALIDATE_EMAIL) ? '' : ' <span class="tag warn" title="' . te('Altes Konto: bitte eine E-Mail-Adresse eintragen') . '">' . te('keine E-Mail') . '</span>' ?></td>
            <td><?= e($k['telefon'] ?? '') ?></td>
            <td><?= e($k['verein'] ?? '') ?></td>
            <td>
                <?= $k['ist_admin'] ? '<span class="tag ok">' . te('Administration') . '</span>' : '' ?>
                <?= !$k['ist_admin'] && ($k['rolle'] ?? '') === 'demo' ? '<span class="tag">' . te('Demo') . '</span>' : '' ?>
                <?php foreach ($k['turniere'] as $t): ?><span class="tag" title="<?= te('Turnierleitung') ?>"><?= te('Leitung: {turnier}', ['turnier' => $t]) ?></span> <?php endforeach; ?>
            </td>
            <td><?= $k['aktiv'] ? '<span class="tag ok">' . te('aktiv') . '</span>' : '<span class="tag">' . te('deaktiviert') . '</span>' ?></td>
            <td>
                <details>
                    <summary class="knopf klein"><?= te('Bearbeiten') ?></summary>
                    <form method="post" action="<?= e(url("benutzer/$id")) ?>" class="formular">
                        <?= csrf_feld() ?>
                        <div class="zeile">
                            <label><?= te('Vorname') ?> <input name="vorname" value="<?= e($k['vorname']) ?>" required maxlength="100"></label>
                            <label><?= te('Nachname') ?> <input name="nachname" value="<?= e($k['nachname']) ?>" required maxlength="100"></label>
                        </div>
                        <label><?= te('E-Mail') ?> <input type="email" name="email" value="<?= e($k['email']) ?>" required maxlength="190"></label>
                        <label><?= te('Telefon') ?> <input type="tel" name="telefon" value="<?= e($k['telefon'] ?? '') ?>" maxlength="50"></label>
                        <label><?= te('Dojo') ?> <?= $dojoAuswahl($k['verein_id'] === null ? null : (int) $k['verein_id']) ?></label>
                        <div><button class="knopf klein primaer" type="submit"><?= te('Speichern') ?></button></div>
                    </form>
                    <form method="post" action="<?= e(url("benutzer/$id/passwort")) ?>" class="formular">
                        <?= csrf_feld() ?>
                        <h3><?= $istIch ? te('Eigenes Passwort ändern') : te('Neues Passwort für dieses Konto') ?></h3>
                        <label><?= te('Neues Passwort (mind. {min} Zeichen)', ['min' => $min]) ?> <input type="password" name="passwort" minlength="<?= $min ?>" required autocomplete="new-password"></label>
                        <label><?= te('Neues Passwort wiederholen') ?> <input type="password" name="passwort2" minlength="<?= $min ?>" required autocomplete="new-password"></label>
                        <?php if ($istIch): ?>
                            <label><?= te('Bisheriges Passwort') ?> <input type="password" name="bisheriges_passwort" required autocomplete="current-password"></label>
                        <?php else: ?>
                            <p class="hilfe"><?= te('Das alte Passwort wird nicht benötigt. Das neue Passwort bitte persönlich weitergeben.') ?></p>
                        <?php endif; ?>
                        <div><button class="knopf klein" type="submit"><?= te('Passwort setzen') ?></button></div>
                    </form>
                    <?php if ((int) ($k['app'] ?? 0) === 1): ?>
                        <form method="post" action="<?= e(url("benutzer/$id/app-zuruecksetzen")) ?>" class="knopfleiste" data-bestaetigen="<?= te('Authenticator-App dieses Kontos zurücksetzen? Nur tun, wenn die Person sicher erkannt ist (z. B. persönlich oder per Rückruf).') ?>">
                            <?= csrf_feld() ?>
                            <span class="klein leise"><?= te('Authenticator-App eingerichtet') ?></span>
                            <button class="knopf klein" type="submit"><?= te('App zurücksetzen') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int) ($k['vertraut'] ?? 0) > 0): ?>
                        <form method="post" action="<?= e(url("benutzer/$id/vertrauen-zuruecksetzen")) ?>" class="knopfleiste">
                            <?= csrf_feld() ?>
                            <span class="klein leise"><?= (int) $k['vertraut'] === 1 ? te('1 vertrautes Gerät') : te('{n} vertraute Geräte', ['n' => (int) $k['vertraut']]) ?> <?= te('(Login ohne Anmeldecode)') ?></span>
                            <button class="knopf klein" type="submit"><?= te('Vertraute Geräte zurücksetzen') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$istIch): ?>
                        <div class="knopfleiste">
                            <form method="post" action="<?= e(url("benutzer/$id/admin")) ?>">
                                <?= csrf_feld() ?>
                                <input type="hidden" name="admin" value="<?= $k['ist_admin'] ? '0' : '1' ?>">
                                <button class="knopf klein" type="submit"><?= $k['ist_admin'] ? te('Administration entziehen') : te('Zur Administration machen') ?></button>
                            </form>
                            <?php if (!$k['ist_admin']): $istDemo = ($k['rolle'] ?? '') === 'demo'; ?>
                                <form method="post" action="<?= e(url("benutzer/$id/demo")) ?>">
                                    <?= csrf_feld() ?>
                                    <input type="hidden" name="demo" value="<?= $istDemo ? '0' : '1' ?>">
                                    <button class="knopf klein" type="submit"><?= $istDemo ? te('Demo-Zugang entziehen') : te('Zum Demo-Zugang machen') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url("benutzer/$id/aktiv")) ?>">
                                <?= csrf_feld() ?>
                                <button class="knopf klein" type="submit"><?= $k['aktiv'] ? te('Deaktivieren') : te('Aktivieren') ?></button>
                            </form>
                        </div>
                        <?php if (!$k['aktiv']): ?>
                            <form method="post" action="<?= e(url("benutzer/$id/loeschen")) ?>" class="formular gefahrzone" data-bestaetigen="<?= te('Konto endgültig löschen? Das lässt sich nicht rückgängig machen.') ?>">
                                <?= csrf_feld() ?>
                                <strong><?= te('Endgültig löschen') ?></strong>
                                <p class="hilfe"><?= te('Entfernt das Konto mit Turnierleitungen und Zuordnungen; die E-Mail-Adresse ist danach wieder frei. Der Änderungsverlauf bleibt erhalten.') ?></p>
                                <label><?= te('Zur Bestätigung die E-Mail-Adresse eingeben') ?> <input name="bestaetigung" autocomplete="off" placeholder="<?= e((string) ($k['email'] ?? '')) ?>"></label>
                                <div><button class="knopf klein gefahr" type="submit"><?= te('Konto endgültig löschen') ?></button></div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </details>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
