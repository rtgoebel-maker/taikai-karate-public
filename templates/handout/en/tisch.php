<?php
/**
 * Handout for table officials (tablet), English version of templates/handout/tisch.php – same structure, keep in sync.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Table officials</div><h1>Entering results on the tablet</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Page 1 of 4') ?>
    <p>One tablet per tatami. You get your tatami’s <strong>QR code</strong> from the tournament management. Everything you enter appears immediately on the status page and in the management’s lists.</p>

    <h2><span class="ho-nr">1</span>Log in</h2>
    <div class="ho-schritt hoch">
        <?= $bild('tablet-anmeldung.jpg', 'Choose the tatami, view “Entry”, enter the PIN') ?>
        <div>
            <ol>
                <li>Scan your tatami’s <strong>“Entry” QR code</strong> with the tablet and enter a device name.</li>
                <li>Tell the tournament management the <strong>check word</strong> shown – they approve the tablet and it logs itself in.</li>
                <li>Fallback, if allowed: open the address with <strong>/tisch</strong>, choose the tatami, view “Entry”, <strong>PIN</strong>.</li>
            </ol>
            <p>The tablet stays logged in and reloads itself after an update. <strong>More space:</strong> <span class="ho-knopf">⛶ Full screen</span> at the top, or add the page to the home screen as an app. Language: <strong>Deutsch</strong> at the bottom or top.</p>
        </div>
    </div>

    <h2><span class="ho-nr">2</span>New group: start the call-up</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-uebergang.jpg', 'New group – start the call-up') ?>
        <div>
            <p>When a new group comes up (including the first one of the day), the tablet shows <strong>“Next group …”</strong>.</p>
            <ol>
                <li>Tap <span class="ho-knopf">📣 Start call-up for group …</span> – only then does the caller see the names.</li>
                <li>Once the caller has ticked everyone off, <strong>“Call-up completed”</strong> appears – then <span class="ho-knopf">Next</span> or <span class="ho-knopf">▶ Start match</span>.</li>
            </ol>
            <p>You can see the call-up status in the bar on the right. Tapped <span class="ho-knopf">Next</span> by mistake? It then shows <span class="ho-knopf">📣 Start call-up</span> – just do it now.</p>
        </div>
    </div>
    <h2><span class="ho-nr">3</span>Kata with points</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-kata.jpg', 'Kata performance: judges’ scores') ?>
        <div>
            <ol>
                <li>Enter or choose the <strong>kata</strong> performed.</li>
                <li>One <strong>score</strong> per judge (0.0 to 9.9). Quick without the decimal point: <strong>77</strong> = 7.7 – then the cursor jumps to the next field.</li>
                <li>The total (highest and lowest dropped) appears next to it. <span class="ho-knopf">Send result</span>.</li>
            </ol>
            <p>An invalid value turns <strong>red</strong> and sending is blocked. If someone does not compete: “Walkover / disqualification” → <span class="ho-knopf">Kiken</span>.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 2 of 4') ?>
    <h2><span class="ho-nr">4</span>Kumite</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-kumite.jpg', 'Kumite: match clock, Ippon/Wazaari, penalties') ?>
        <div>
            <ul>
                <li><strong>Match clock</strong> (if switched on): <span class="ho-knopf">▶ Hajime</span> starts, <span class="ho-knopf">⏸ Yame</span> stops. Yellow 30 seconds before the end (Ato shibaraku), red at the end.</li>
                <li><strong>Ippon / Wazaari</strong> with <span class="ho-knopf">+</span> and <span class="ho-knopf">−</span> (2 Wazaari = Ippon).</li>
                <li><strong>Penalties</strong>: the button shows the referee’s next call – <em>Keikoku → Hansoku Chui → Hansoku</em>, each for rule violation, Jogai, Mubobi. Penalties give the opponent no points. Hansoku = opponent wins.</li>
            </ul>
        </div>
    </div>
    <div class="ho-schritt">
        <?= $bild('tablet-ippon.jpg', 'An Ippon ends the match') ?>
        <div>
            <p>As soon as a match is decided (Ippon, Hansoku), the tablet asks: <span class="ho-knopf">End match – send result</span> or <span class="ho-knopf">Continue entering</span> (if you mistyped).</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 3 of 4') ?>
    <h2><span class="ho-nr">5</span>Time up, Saishiai, Hantei</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-hantei.jpg', 'Tie after time is up: Hantei') ?>
        <div>
            <ol>
                <li>If time runs out without an Ippon: <span class="ho-knopf">⏱ Time up</span> (automatic with the match clock). The higher score wins.</li>
                <li>In case of a <strong>tie</strong>: either <span class="ho-knopf">Saishiai (extension)</span> – a new match of 2 minutes – or straight to <strong>Hantei</strong>.</li>
                <li>Hantei: enter the flags for AKA and SHIRO; flags not shown count as a draw. Then <span class="ho-knopf">Send result</span>.</li>
            </ol>
        </div>
    </div>

    <h2><span class="ho-nr">6</span>Group finished – close it</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-gruppe-fertig.jpg', 'Group finished: check placings and close') ?>
        <div>
            <ol>
                <li>After the last match the tablet shows the <strong>placings</strong>.</li>
                <li>Check them. If something is wrong: correct it first (page 4).</li>
                <li><span class="ho-knopf">Close group …</span> – then the next group comes up (step 2).</li>
            </ol>
        </div>
    </div>
    <div class="ho-hinweis">
        <h3>Other situations</h3>
        <ul>
            <li><strong>“Caller reports … missing”</strong>: competitor not there → <span class="ho-knopf">Confirm: Kiken</span>; present after all → <span class="ho-knopf">… is present after all</span>.</li>
            <li><strong>Injury / interruption</strong>: <span class="ho-knopf">⏸ Interrupt match</span>, then <span class="ho-knopf">▶ Resume match</span>.</li>
            <li><strong>Message from the management</strong> appears at the top – read it and tap <span class="ho-knopf">✓ Read</span>.</li>
        </ul>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 4 of 4') ?>
    <h2><span class="ho-nr">!</span>Typed something wrong – what now?</h2>
    <table class="ho-tabelle">
        <thead><tr><th style="width:32%">Situation</th><th>What to do</th></tr></thead>
        <tbody>
            <tr><td><strong>Not sent yet</strong></td><td>Take back a count with <span class="ho-knopf">−</span>, take back a penalty via its badge <span class="ho-knopf">… ✕</span>, overwrite a score in its field – or reset everything with <span class="ho-knopf">Discard</span>.</td></tr>
            <tr><td><strong>Mistyped in the “End match?” window</strong></td><td><span class="ho-knopf">Continue entering</span> – nothing is sent.</td></tr>
            <tr><td><strong>Already sent</strong></td><td>On the right under <strong>“Last entered”</strong> <span class="ho-knopf">Correct</span> → <strong>management PIN</strong> (from the tournament management) → change the values → <span class="ho-knopf">Send result</span>. Works for matches and kata performances.</td></tr>
            <tr><td><strong>Match has to be re-run</strong></td><td>In the correction: <span class="ho-knopf">Delete result – re-run match</span>. The match starts again right away on this tatami.</td></tr>
            <tr><td><strong>“Following match already decided”</strong></td><td>Correct the later match first – or call the <strong>tournament management</strong> (they correct it on the PC).</td></tr>
            <tr><td><strong>Group already closed</strong></td><td>You can still correct via “Last entered”; the group then reopens. If the category has been confirmed: tournament management.</td></tr>
        </tbody>
    </table>
    <div class="ho-schritt">
        <?= $bild('tablet-korrektur-pin.jpg', '“Correct” asks for the management PIN') ?>
        <?= $bild('tablet-korrektur.jpg', 'Correction mode: change and send again – or delete and re-run') ?>
    </div>
    <div class="ho-fehler">
        <h3>Technical problems</h3>
        <ul>
            <li><strong>“offline”</strong> at the top: check the Wi-Fi. What you entered stays – tap <span class="ho-knopf">Send result</span> again as soon as “online” appears.</li>
            <li>Tablet does not respond or shows old data: <strong>reload</strong> the page. You stay logged in.</li>
            <li>Tablet replaced: pair the new device via QR code and have the management approve it.</li>
            <li>Nothing works at all: carry on writing on the <strong>emergency set</strong> (paper, from the management) – ◯ = Ippon, △ = Wazaari – and inform the management.</li>
        </ul>
    </div>
</div>
