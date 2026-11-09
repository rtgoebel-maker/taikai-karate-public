/* Schutz öffentlicher Formulare (Kern\BotSchutz): löst im Hintergrund die Rechenaufgabe – sucht n, sodass
   SHA-256("<aufgabe>:<n>") mit data-bits Null-Bits beginnt – und trägt n ins Feld ein. Wird das Formular vorher
   abgeschickt, wartet es kurz. Eigenes SHA-256 (eine Blockrunde genügt: Eingabe < 56 Byte), damit es auch ohne
   HTTPS im Hallennetz funktioniert (crypto.subtle gibt es dort nicht). */
(() => {
    const K = new Uint32Array([
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
    ]);
    const W = new Uint32Array(64);
    const rotr = (x, n) => (x >>> n) | (x << (32 - n));

    /** Erstes 32-Bit-Wort von SHA-256 einer ASCII-Zeichenkette (< 56 Zeichen). */
    function ersteWort(text) {
        W.fill(0);
        for (let i = 0; i < text.length; i++) { W[i >> 2] |= text.charCodeAt(i) << (24 - (i % 4) * 8); }
        W[text.length >> 2] |= 0x80 << (24 - (text.length % 4) * 8);
        W[15] = text.length * 8;
        for (let i = 16; i < 64; i++) {
            const s0 = rotr(W[i - 15], 7) ^ rotr(W[i - 15], 18) ^ (W[i - 15] >>> 3);
            const s1 = rotr(W[i - 2], 17) ^ rotr(W[i - 2], 19) ^ (W[i - 2] >>> 10);
            W[i] = (W[i - 16] + s0 + W[i - 7] + s1) | 0;
        }
        let a = 0x6a09e667, b = 0xbb67ae85, c = 0x3c6ef372, d = 0xa54ff53a, e = 0x510e527f, f = 0x9b05688c, g = 0x1f83d9ab, h = 0x5be0cd19;
        for (let i = 0; i < 64; i++) {
            const t1 = (h + (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + W[i]) | 0;
            const t2 = ((rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
            h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
        }
        return (0x6a09e667 + a) >>> 0;
    }

    function loesen(feld) {
        const aufgabe = feld.dataset.botschutz;
        const bits = Math.min(24, parseInt(feld.dataset.bits, 10) || 16);
        const grenze = 2 ** (32 - bits); // erstes Wort < grenze ⇔ beginnt mit bits Null-Bits
        return new Promise((fertig) => {
            let n = 0;
            const schritt = () => {
                for (let i = 0; i < 4000; i++, n++) {
                    if (ersteWort(aufgabe + ':' + n) < grenze) { feld.value = String(n); fertig(); return; }
                }
                setTimeout(schritt, 0);
            };
            schritt();
        });
    }

    document.querySelectorAll('input[data-botschutz]').forEach((feld) => {
        const formular = feld.form;
        if (!formular) { return; }
        const bereit = loesen(feld);
        let gelöst = false;
        bereit.then(() => { gelöst = true; });
        formular.addEventListener('submit', (ereignis) => {
            if (gelöst) { return; }
            ereignis.preventDefault();
            ereignis.stopImmediatePropagation();
            const knopf = ereignis.submitter;
            if (knopf) { knopf.disabled = true; knopf.dataset.text = knopf.textContent; knopf.textContent = 'Einen Moment …'; }
            bereit.then(() => {
                if (knopf) { knopf.disabled = false; knopf.textContent = knopf.dataset.text; }
                formular.requestSubmit(knopf || undefined);
            });
        }, true);
    });
})();
