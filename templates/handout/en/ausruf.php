<?php
/**
 * Handout for the call-up (phone), English version of templates/handout/ausruf.php – same structure, keep in sync.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Call-up</div><h1>Calling up and ticking off competitors</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Page 1 of 2') ?>
    <p>The caller calls up the competitors of a group and reports who is present. The caller uses a phone with the <strong>“Call-up”</strong> view of the same tatami.</p>

    <h2><span class="ho-nr">1</span>Log in</h2>
    <p>Scan the tatami’s <strong>“Call-up” QR code</strong>, enter your name and tell the tournament management the <strong>check word</strong> – once approved, the phone logs itself in. Fallback, if allowed: address with <strong>/tisch</strong>, tatami, view “Call-up”, PIN.</p>
    <p><strong>Tip:</strong> add the page to the home screen – it then runs like an app without address and tab bars. Language can be switched at the top (<strong>Deutsch</strong>).</p>

    <h2><span class="ho-nr">2</span>Tick off competitors</h2>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-liste.jpg', 'The group’s tick list') ?>
        <div>
            <p>The names appear as soon as the table officials have <strong>started the call-up</strong> (until then it says “Next group … – the names will appear as soon as …”).</p>
            <ol>
                <li>Call up the group out loud. With <span class="ho-knopf">📣 Call up group …</span> the names also appear on the status page under “Please go to tatami”. If the table officials started the call-up, this has already happened.</li>
                <li>Tap <span class="ho-knopf">✓ present</span> or <span class="ho-knopf">✗ missing</span> for each competitor.</li>
            </ol>
            <p>Anyone who has already been “present” or has competed in this category does not need to be called up again in later rounds.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Page 2 of 2') ?>
    <h2><span class="ho-nr">3</span>Complete the call-up</h2>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-abhaken.jpg', 'Everyone ticked off – complete') ?>
        <div>
            <ol>
                <li>Once everyone is ticked off, <span class="ho-knopf">Complete call-up for group …</span> turns green – tap it.</li>
                <li>The phone then shows <strong>“Now on the tatami”</strong> and <strong>“Get ready”</strong> – send the next competitors to the edge of the mat.</li>
                <li>Anyone <strong>missing</strong> is reported to the tournament management; they decide (the competitor may be on another tatami right now).</li>
            </ol>
        </div>
    </div>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-fertig.jpg', 'After completing: on now and get ready') ?>
        <div>
            <div class="ho-fehler">
                <h3>Ticked off wrongly?</h3>
                <ul>
                    <li><strong>Before completing</strong>: tap the same button again – the tick is gone – and set it correctly.</li>
                    <li><strong>After completing</strong>: under “Called up – ready” tap <span class="ho-knopf">reopen</span>, change the ticks, complete again.</li>
                    <li><strong>“missing” was wrong</strong> and the competitor is there after all: change it to “present” – or tell the table officials, they have <span class="ho-knopf">… is present after all</span>.</li>
                </ul>
            </div>
            <div class="ho-hinweis">
                <h3>Good to know</h3>
                <ul>
                    <li>“Cannot be called up yet”: a competitor is still competing on another tatami – wait, the list updates itself.</li>
                    <li>If the pools of a category run on several tatamis, each tatami calls up only its own pool.</li>
                    <li>“offline” at the top right: check the Wi-Fi; the page catches up by itself.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
