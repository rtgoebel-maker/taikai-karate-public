<?php
/**
 * Betroffenenrechte (Administration): Person suchen → Auskunft (Art. 15) als Bericht/JSON, Löschung (Art. 17).
 * @var string $name
 * @var string $geburtsdatum
 * @var string $email
 * @var list<array<string, mixed>>|null $treffer null = noch nicht gesucht
 */
$this->layout('layout', ['titel' => 'Auskunft und Löschung']);
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>">System</a></p>
<h1>Auskunft und Löschung (DSGVO)</h1>
<p class="leise">Für Anfragen von Startern, Erziehungsberechtigten oder meldenden Personen: Die Auskunft nach Art. 15 DSGVO enthält alle gespeicherten
    Angaben zur Person über alle Turniere dieser Installation, dazu je Turnier die Datenschutzhinweise. Vor der Herausgabe die Identität der anfragenden
    Person prüfen. Berichtigungen (Art. 16) wie gewohnt beim Teilnehmer im Turnier.</p>

<div class="zwei-spalten">
    <form method="get" action="<?= e(url('datenschutz/auskunft')) ?>" class="karte formular">
        <h2>Starter suchen</h2>
        <div class="zeile">
            <label>Name <span class="hilfe">Vor- und/oder Nachname</span><input name="name" value="<?= e($name) ?>" maxlength="100" required minlength="2"></label>
            <label>Geburtsdatum <span class="hilfe">optional</span><input type="date" name="geburtsdatum" value="<?= e($geburtsdatum) ?>"></label>
        </div>
        <div><button class="knopf primaer" type="submit">Suchen</button></div>
    </form>
    <form method="get" action="<?= e(url('datenschutz/auskunft/kontakt')) ?>" class="karte formular" target="_blank">
        <h2>Meldende Person / Konto</h2>
        <label>E-Mail-Adresse <span class="hilfe">Anmeldungen, Dojo-Zugang, versendete E-Mails</span><input type="email" name="email" value="<?= e($email) ?>" maxlength="190" required></label>
        <div><button class="knopf primaer" type="submit">Auskunft erstellen ↗</button></div>
    </form>
</div>

<?php if ($treffer !== null): ?>
    <section class="karte">
        <h2>Treffer (<?= count($treffer) ?>)</h2>
        <?php if (!$treffer): ?>
            <p class="leise">Keine Starter mit diesem Namen gespeichert.</p>
        <?php else: ?>
            <p class="hilfe">Dieselbe Person kann mehrfach angelegt sein (z. B. aus verschiedenen Turnieren) – alle passenden Zeilen auswählen.</p>
            <form method="get" action="<?= e(url('datenschutz/auskunft/starter')) ?>" target="_blank">
                <div class="tabelle-scroll">
                <table class="tabelle">
                    <thead><tr><th></th><th>Name</th><th>Geburtsdatum</th><th>Verein</th><th>Graduierung</th><th class="rechts">Teilnahmen</th></tr></thead>
                    <tbody>
                    <?php foreach ($treffer as $t): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?= (int) $t['id'] ?>" aria-label="auswählen"></td>
                            <td><?= e($t['vorname'] . ' ' . $t['nachname']) ?></td>
                            <td><?= e(datum_de($t['geburtsdatum'])) ?></td>
                            <td><?= e((string) $t['verein']) ?></td>
                            <td><?= e(grad((int) $t['graduierung'])) ?></td>
                            <td class="rechts"><?= (int) $t['teilnahmen'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="knopfleiste">
                    <button class="knopf primaer" type="submit">Auskunft zum Drucken ↗</button>
                    <button class="knopf" type="submit" name="format" value="json">Als JSON herunterladen</button>
                </p>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
