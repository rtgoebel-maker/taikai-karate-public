<?php
/**
 * Flyer "Overview" (5 pages A4), English version of templates/handout/flyer.php – same structure, keep in sync.
 */
$bild = static fn (string $datei, string $text, string $klasse = '') => '<figure class="ho-bild' . ($klasse !== '' ? ' ' . $klasse : '') . '"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $titel, string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Taikai – Karate</div><h1 class="fl-seitentitel">' . e($titel) . '</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
$rolle = static fn (string $wer, string $text) => '<h2 class="fl-rolle"><span>' . e($wer) . '</span>' . e($text) . '</h2>';
?>
<div class="blatt fl">
    <header class="fl-held">
        <div class="fl-marke"><img src="<?= e(asset('logo/taikai-logo.svg')) ?>" alt="Taikai – Karate" width="188" height="60"><span>taikai-karate.de</span></div>
        <h1>Run your tournaments –<br>from registration to certificate</h1>
        <p class="fl-unterzeile">For kata, kumite, team kata and team kumite under the DJKB competition rules 2024. In the browser on PC, tablet and phone – no app, nothing to install on the devices. In German and English.</p>
    </header>

    <?= $bild('flyer-status.jpg', 'On tournament day: live status page – who is on which tatami, who should get ready, who is being called up', 'fl-gross') ?>

    <ol class="fl-ablauf">
        <li><strong>Prepare</strong><span>tournament, categories, tatamis, choose features</span></li>
        <li><strong>Register</strong><span>dojos enter their competitors themselves</span></li>
        <li><strong>Assign &amp; draw</strong><span>automatic, fair, traceable</span></li>
        <li><strong>Tournament day</strong><span>management, table, call-up, coaches, spectators</span></li>
        <li><strong>Award</strong><span>placings, winners list, certificates</span></li>
    </ol>

    <h2><span class="ho-nr">1</span>Preparation</h2>
    <div class="fl-zwei">
        <ul>
            <li><strong>Create the tournament</strong>: date, venue, number of tatamis, minimum grade.</li>
            <li><strong>Categories from a template</strong> (DJKB standard or your own scheme) with rules per round, e.g. free kata choice in the final.</li>
            <li><strong>Tatamis &amp; access</strong>: one PIN per tatami for the tablets, a management PIN for corrections; appoint further tournament managers.</li>
        </ul>
        <ul>
            <li><strong>Tournament phases</strong> guide you through: preparation → registration → tournament running → finished. Results can only be entered while the tournament is running.</li>
            <li><strong>Demo mode</strong> for trying things out and for training table officials.</li>
        </ul>
    </div>

    <div class="fl-baukasten">
        <h3>Building blocks: switch features on and off as you like</h3>
        <p>Each tournament uses only what it needs – from a small club tournament to a big championship. Everything is a checkbox in the settings and can be changed at any time.</p>
        <table>
            <tr><td><strong>Online registration</strong></td><td>open and close registration, entry deadline, entry fee per start and team</td></tr>
            <tr><td><strong>Coaching portal</strong></td><td>on/off per tournament; individually: competitors live, results, correction requests, entry fee</td></tr>
            <tr><td><strong>Status page</strong></td><td>public yes/no; competitor lists &amp; brackets, results, host logos</td></tr>
            <tr><td><strong>Match clock on the tablet</strong></td><td>on/off, match time and final time, signal tone</td></tr>
            <tr><td><strong>Tatami allocation</strong></td><td>dynamic (a free tatami takes the next group) or fixed schedule; kata and kumite separately</td></tr>
            <tr><td><strong>Venue laptop</strong></td><td>only if needed: hand the tournament over to a laptop at the venue for the day</td></tr>
            <tr><td><strong>Data protection</strong></td><td>privacy notice per tournament, retention periods with pseudonymisation, accounting export, voluntary invitations to future tournaments</td></tr>
            <tr><td><strong>More</strong></td><td>German/English per person, AKA side at the table, own colour, feedback button, demo mode</td></tr>
        </table>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Registration, categories, draw', 'Page 2 of 5') ?>

    <h2 class="fl-erste"><span class="ho-nr">2</span>Registration area for dojos</h2>
    <div class="fl-zwei fl-anmeldung">
        <div>
            <?= $bild('flyer-meldungen.jpg', 'A dojo’s registration area “My entries”') ?>
        </div>
        <div>
            <p>Each dojo enters its own participants – nobody has to retype lists.</p>
            <ul>
                <li><strong>With a dojo account</strong> under <em>My entries</em>: all tournaments open for registration, your own entries with status and entry fee, an overview to print. Several coaches per dojo possible.</li>
                <li><strong>Without an account</strong> via the tournament’s address; a personal link allows changes until the entry deadline.</li>
            </ul>
        </div>
    </div>
    <div class="fl-zwei fl-anmeldung">
        <div>
            <?= $bild('flyer-anmeldeformular.jpg', 'Registration form with entry fee per start and team', 'fl-formular') ?>
        </div>
        <div>
            <ul>
                <li>Participants, disciplines and teams in <strong>one form</strong>; the entry fee is recalculated with every input.</li>
                <li>The <strong>category is assigned automatically</strong> from gender, age on tournament day and grade.</li>
                <li><strong>Payment request</strong> with a bank transfer QR code; the management records the payment received, checks each entry and approves it – only then are the participants taken over.</li>
                <li>With a link to the tournament’s <strong>privacy notice</strong> and the dojo’s confirmation that its competitors have been informed.</li>
                <li>Alternatively <strong>Excel/CSV import</strong> with preview and error check, or manual entry.</li>
            </ul>
        </div>
    </div>

    <h2><span class="ho-nr">3</span>Assign categories and draw</h2>
    <?= $bild('flyer-klassen.jpg', 'Category overview with number of competitors and status per category', 'fl-breit') ?>
    <div class="fl-zwei fl-mitte">
        <div>
            <?= $bild('flyer-baum.jpg', 'Knockout bracket with bronze match') ?>
        </div>
        <div>
            <ul>
                <li><strong>Categories that are too small</strong> are flagged, with a suggestion for merging.</li>
                <li><strong>Formats</strong>: knockout with bronze match, pools with final round, kata points with final, round robin.</li>
                <li><strong>Draw</strong> at random, but logged and reproducible; competitors from the same dojo meet as late as possible.</li>
            </ul>
        </div>
    </div>
    <div class="ho-hinweis fl-start">
        <strong>“Start tournament” checks first:</strong> all categories drawn, tatamis allocated, PINs for the tablets set, entries approved – and warns about categories that would be cancelled for lack of competitors.
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('On tournament day – who sees what?', 'Page 3 of 5') ?>

    <?= $rolle('Tournament management', 'Control and decide') ?>
    <div class="fl-zwei fl-leitung">
        <?= $bild('leitung-uebersicht.jpg', 'Overview: “no-show” reports, tournament phase') ?>
        <?= $bild('leitung-plan.jpg', 'Tatami schedule: queue and tatamis') ?>
    </div>
    <ul class="fl-liste2">
        <li><strong>Tatami schedule</strong>: free tatamis automatically get the next group; change the order with the mouse, lock tatamis for a break.</li>
        <li><strong>Messages</strong> to single tables or all of them, shown until someone taps “read” there.</li>
        <li><strong>No-show</strong>: present after all, Kiken for this match or for all events.</li>
        <li><strong>Confirm placings</strong>, correct results on the PC – every change is recorded in the history.</li>
    </ul>

    <?= $rolle('Table officials', 'Results on the tablet') ?>
    <div class="fl-zwei fl-tablets">
        <?= $bild('tablet-kumite.jpg', 'Kumite: match clock, Ippon/Wazaari, penalties') ?>
        <?= $bild('tablet-kata.jpg', 'Kata: judges’ scores') ?>
    </div>
    <ul class="fl-liste2">
        <li><strong>Kumite</strong> under the 2024 rules: Shobu Ippon or Sanbon, penalties (rule violation, Jogai, Mubobi) with the calls <em>Keikoku → Hansoku Chui → Hansoku</em>, Hantei, Saishiai, team kumite.</li>
        <li><strong>Match clock</strong> with Hajime/Yame and Ato shibaraku (if switched on).</li>
        <li><strong>Kata</strong> with score boards 0.0–9.9 (highest and lowest dropped) or flags; <em>77</em> = 7.7.</li>
        <li><strong>Typed something wrong?</strong> Change it before sending; afterwards only correct it with the management PIN or re-run the match.</li>
        <li><strong>Pair devices via QR code</strong>, approved by the management; tablet and phone can run full screen or as an app without browser bars.</li>
    </ul>

    <?= $rolle('Caller', 'Call up competitors by phone') ?>
    <div class="fl-ausruf">
        <?= $bild('ausruf-liste.jpg', 'Call-up phone per tatami', 'fl-hoch') ?>
        <div>
            <p>When the table officials start a new group, the caller sees its competitors with their clubs and ticks off each one: <strong>present</strong> or <strong>missing</strong>. Once the group is ticked off, the caller completes the call-up.</p>
            <ul>
                <li>The tablet shows the progress and knows when things can start.</li>
                <li>If someone is missing, the management knows immediately.</li>
                <li>At the same time the status page shows “Please go to tatami …”.</li>
            </ul>
        </div>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Coaches, spectators, results', 'Page 4 of 5') ?>

    <?= $rolle('Coaches', 'Coaching portal – only your own dojo') ?>
    <div class="fl-zwei">
        <div>
            <?= $bild('flyer-coach.jpg', 'My competitors live: tatami, round, how many matches before them') ?>
            <?= $bild('flyer-antraege.jpg', 'Correction requests with the tournament management’s reply', 'fl-schmal') ?>
        </div>
        <div>
            <?= $bild('flyer-medaillen.jpg', 'The dojo’s results with medal table', 'fl-medaillen') ?>
            <ul>
                <li><strong>Competitors live</strong>: where and when each one competes, how many matches are still ahead, call-up status.</li>
                <li><strong>Results</strong> and medal table of the dojo, <strong>entry fee</strong> to print.</li>
                <li><strong>Correction requests</strong> (name, date of birth, grade, category) straight to the management – with the reply in the portal.</li>
            </ul>
        </div>
    </div>

    <?= $rolle('Spectators', 'Status page at the venue and on the phone') ?>
    <div class="fl-zwei">
        <?= $bild('flyer-vollbild.jpg', 'Full screen per tatami for a projector or TV') ?>
        <div>
            <p>Who is <strong>on now</strong> and who should <strong>get ready</strong> – per tatami, updating itself. Plus competitor lists, brackets, confirmed results, club ranking and competitor search.</p>
            <p>Only <strong>name and club</strong> are public – no dates of birth.</p>
        </div>
    </div>

    <h2><span class="ho-nr">5</span>After the tournament</h2>
    <div class="fl-drei fl-ehrung">
        <?= $bild('flyer-urkunde.jpg', 'Certificate per place') ?>
        <?= $bild('flyer-siegerliste.jpg', 'Winners list (A4)') ?>
        <div>
            <?= $bild('flyer-vereinswertung.jpg', 'Club ranking') ?>
            <p><strong>Certificates</strong> with logos and signatures or as text for pre-printed forms, winners list, results lists, competitors per club.</p>
            <p><strong>Data protection</strong>: accounting export for archiving, status page offline after a few days, competitors pseudonymised after the retention period – the statistics remain.</p>
        </div>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Security, contingency plan, operation', 'Page 5 of 5') ?>

    <div class="fl-kasten fl-sicher">
        <h2 class="fl-erste">🔒 Security and data protection</h2>
        <p>The software handles data of children and young people – security is built in from the start:</p>
        <div class="fl-zwei">
            <ul>
                <li>Checked against the <strong>OWASP Top 10</strong> (2025), the standard for web security; over 400 automated tests, security check with every release</li>
                <li><strong>Permissions per role and tournament</strong>: tournament managers only for their tournaments, coaches only for their dojo, table officials only for their tatami</li>
                <li>Passwords and PINs stored only in <strong>encrypted</strong> (hashed) form</li>
                <li><strong>Lockout after failed attempts</strong> at login and PIN entry, throttling against mass requests</li>
            </ul>
            <ul>
                <li>Protection against <strong>forged forms</strong> (CSRF) and <strong>injected scripts</strong> (Content Security Policy)</li>
                <li>Corrections on the tablet only with the <strong>management PIN</strong>; every change in the <strong>history</strong>: who, when, what</li>
                <li>Only <strong>name and club</strong> public, no dates of birth; <strong>GDPR tools</strong>: notices, retention periods, access requests</li>
                <li><strong>Signed transfer</strong> between venue and online, backups with checksum</li>
            </ul>
        </div>
    </div>

    <div class="fl-kasten fl-notfall">
        <h2 class="fl-erste">🛟 Contingency plan – if the technology fails</h2>
        <p>For all our love of technology: a tournament must not fail because of a flat battery or weak Wi-Fi.</p>
        <table>
            <tr><td><strong>Wi-Fi briefly gone</strong></td><td>The tablet shows “offline” and keeps the input; as soon as it is “online” again, it sends again.</td></tr>
            <tr><td><strong>No internet at the venue</strong></td><td>The venue laptop runs the tournament on its own venue Wi-Fi; online everything stays visible and is synced later.</td></tr>
            <tr><td><strong>Laptop fails</strong></td><td>Online takes over again; the last transferred state is available there.</td></tr>
            <tr><td><strong>A tablet stops working</strong></td><td>Any other device with a browser can be paired via QR code (or logged in with the tatami PIN), or the management enters results on the PC.</td></tr>
            <tr><td><strong>Technology fails completely</strong></td><td>The pre-printed <strong>emergency set</strong> (brackets and match lists to continue by hand, ◯ Ippon / △ Wazaari) is at every table, plus A4/A3 notices instead of the status page; results are entered on the PC later.</td></tr>
            <tr><td><strong>Back up data</strong></td><td>Export of the whole tournament with checksum, database download – also for moving between computer and online.</td></tr>
        </table>
    </div>

    <div class="fl-unten3">
        <div>
            <h2>Operation of your choice</h2>
            <ul>
                <li><strong>Online</strong> on standard web hosting (PHP, MySQL): registration, portal and status page for everyone.</li>
                <li><strong>Online + venue laptop</strong> (Windows or Linux) with its own Wi-Fi at the venue.</li>
                <li><strong>Local only</strong>: one computer, completely offline – ideal for club tournaments.</li>
            </ul>
            <p class="klein">Requirements: PHP 8.3 and an up-to-date browser – no app needed.</p>
        </div>
        <div>
            <h2>Help and training</h2>
            <ul>
                <li><strong>Manual</strong> (German and English) and help inside the software</li>
                <li><strong>Handouts</strong> for table, call-up and management – with “Typed something wrong – what now?”</li>
                <li><strong>Demo mode</strong> for practice</li>
                <li><strong>Feedback button</strong> with screenshot</li>
            </ul>
        </div>
        <div class="fl-spenden">
            <h3>♥ Support the project</h3>
            <img src="<?= e(asset('spenden-qr.png')) ?>" alt="QR code for the PayPal donation page">
            <p>Free of charge and open, developed in our spare time. A donation helps with maintenance, security and further development.</p>
        </div>
    </div>
    <p class="fl-urheber">Authors: Robert Göbel and Wolfgang Maier · free software (AGPL-3.0)</p>
    <div class="fl-kontakt">Contact: <span></span></div>
</div>
