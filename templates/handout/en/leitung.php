<?php
/**
 * Handout for the tournament management, English version of templates/handout/leitung.php – same structure, keep in sync.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Tournament management</div><h1>Running the tournament day</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Page 1 of 4') ?>
    <h2><span class="ho-nr">1</span>Before the start</h2>
    <ul>
        <li><strong>Settings → Tatamis &amp; access</strong>: PIN per tatami, <strong>management PIN</strong> (for corrections on the tablet), AKA on the left/right, match clock on the tablet.</li>
        <li><strong>Settings → Pair devices</strong>: print the QR codes per tatami. Tablets and phones scan them; you approve each device after the check word (the page shows new requests by itself) and can lock it at any time.</li>
        <li><strong>Print</strong>: print the match lists per tatami and the <strong>emergency set</strong> and keep them ready.</li>
        <li><strong>Overview → Tournament phase → “Start tournament”</strong>: draws open categories and allocates the first groups to the tatamis.</li>
    </ul>

    <h2><span class="ho-nr">2</span>Overview on tournament day</h2>
    <div class="ho-schritt breit">
        <?= $bild('leitung-uebersicht.jpg', 'Overview: “No-show” reports and tournament phase') ?>
    </div>
    <ul>
        <li><strong>No-show</strong>: report from the call-up. Decide: <span class="ho-knopf">Present after all</span>, <span class="ho-knopf">Kiken here only</span> (loses this match) or <span class="ho-knopf">From all events</span>.</li>
        <li>The header shows warnings (e.g. “1 no-show”, “☕ Kata finished” – a chance for a break).</li>
    </ul>
</div>

<div class="blatt">
    <?= $kopf('Page 2 of 4') ?>
    <h2><span class="ho-nr">3</span>Tatami schedule</h2>
    <div class="ho-schritt breit">
        <?= $bild('leitung-plan.jpg', 'Queue and tatamis: drag cards with the mouse') ?>
    </div>
    <ul>
        <li><strong>Dynamic allocation</strong>: when a group is finished, the tatami immediately gets the next one from the <strong>queue</strong> (kata first, then kumite, younger categories first). Change the order by dragging; “Re-sort queue” restores the category order.</li>
        <li><strong>🔒 Lock</strong> per tatami (e.g. break, changing mats): the current group finishes, after that nothing new comes.</li>
    </ul>
    <div class="ho-schritt">
        <?= $bild('leitung-nachricht.jpg', 'Message to one or all tatamis') ?>
        <div>
            <p><strong>Message to the tables</strong>: send a text to one or all tatamis. It stays on the tablet and the call-up phone until someone taps “Read” there.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 3 of 4') ?>
    <h2><span class="ho-nr">4</span>Correcting a result</h2>
    <div class="ho-schritt eng">
        <?= $bild('leitung-ergebnis.jpg', 'Correcting a match on the PC') ?>
        <div>
            <ol>
                <li>Open the category → at the match or performance <span class="ho-knopf">Corr.</span> – or Results → match.</li>
                <li>Change the values, save. Every correction is logged (history).</li>
            </ol>
            <p>On the <strong>tablet</strong> the table officials correct results themselves with the management PIN (“Last entered → Correct”).</p>
        </div>
    </div>
    <div class="ho-fehler">
        <h3>Wrong input – typical cases</h3>
        <table class="ho-tabelle">
            <tr><th style="width:36%">Message / situation</th><th>Solution</th></tr>
            <tr><td>“The following match has already been decided”</td><td>First correct the later match or delete its result, then the earlier one.</td></tr>
            <tr><td>Category is already <strong>confirmed</strong></td><td>Revoke the confirmation in the category, correct, confirm again.</td></tr>
            <tr><td>Match is to be re-run</td><td>On the tablet “Correct” → “Delete result – re-run match” (management PIN).</td></tr>
            <tr><td>Wrong competitor in a category / wrong personal data</td><td>Edit the participant; before the draw reassign, afterwards check the category (redraw if necessary, as long as there are no results).</td></tr>
            <tr><td>Tie in kata / round robin</td><td>The category shows “Tie-break” – enter the order after the tie-break performance or deciding match.</td></tr>
        </table>
    </div>
    <h2><span class="ho-nr">5</span>Confirming placings</h2>
    <div class="ho-schritt eng">
        <?= $bild('leitung-klasse.jpg', 'Category finished: check and confirm placings') ?>
        <div>
            <p>Once the table has closed the group, the placings appear as <strong>provisional</strong> in the category. Check them and tap <span class="ho-knopf">Confirm placings</span> – only then do they appear in the winners list, results and on the certificates.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 4 of 4') ?>
    <h2><span class="ho-nr">6</span>Printing and emergencies</h2>
    <div class="ho-schritt">
        <?= $bild('leitung-druck.jpg', 'Print centre: match lists, emergency set, notices, certificates') ?>
        <div>
            <ul>
                <li><strong>Emergency set per category</strong>: bracket or pool list and match list to continue by hand (◯ = Ippon, △ = Wazaari).</li>
                <li><strong>Notice A4/A3</strong>: replaces the status page on the wall.</li>
                <li><strong>Certificates</strong> per confirmed category.</li>
            </ul>
            <div class="ho-hinweis"><strong>If the technology fails:</strong> emergency set to the tables, write results by hand, enter them on the PC later.</div>
        </div>
    </div>
    <h2><span class="ho-nr">7</span>Status page</h2>
    <div class="ho-schritt">
        <?= $bild('leitung-status.jpg', 'Status page for spectators and coaches') ?>
        <div>
            <p>The public status page (menu “Status page”) shows per tatami who is on now, who should get ready and who is being called up. It updates itself – good for a screen at the venue (“Full screen” per tatami).</p>
        </div>
    </div>
    <div class="ho-hinweis">
        <h3>After the tournament: data protection</h3>
        <p><strong>Settings → Data protection</strong>: download the accounting export (ZIP with payment list and PDF receipts) and have the treasurer archive it. After that the software takes the status page offline after the set number of days and pseudonymises the competitors after the retention period – results remain as statistics.</p>
    </div>
    <div class="ho-hinweis">
        <h3>Further reading</h3>
        <p>Menu <strong>Help</strong> in the software (all topics), where you can also print these handouts for table officials and call-up. The interface and handouts are also available in German (switch in the footer).</p>
    </div>
</div>
