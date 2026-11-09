/* Feedback-Knopf: Dialog mit Art, Beschreibung, Seite und Screenshot (html2canvas, beim ersten Klick geladen). */
'use strict';

(() => {
    const skript = document.currentScript;
    const sendenUrl = skript.dataset.senden;
    const bibliothek = skript.dataset.bibliothek;
    const kompakt = skript.dataset.kompakt === '1';
    const ADRESSE = skript.dataset.adresse || 'feedback@taikai-karate.de';
    const ARTEN = [['fehler', 'Fehler'], ['vorschlag', 'Verbesserungsvorschlag'], ['frage', 'Frage']];
    let screenshot = null;
    let mitScreenshot = true;
    let art = 'fehler';

    const knopf = document.createElement('button');
    knopf.type = 'button';
    knopf.className = 'fb-knopf' + (kompakt ? ' kompakt' : '');
    knopf.setAttribute('data-html2canvas-ignore', '');
    knopf.title = 'Feedback geben';
    // Tablet/Ausruf: Platz in der Kopfzeile, damit der Knopf keine Bedienelemente verdeckt
    const platz = document.querySelector('[data-feedback-platz]');
    if (platz) {
        knopf.className = 'fb-knopf eingebettet ' + (platz.dataset.feedbackPlatz || '');
        knopf.innerHTML = '✎ Feedback';
        platz.appendChild(knopf);
    } else {
        knopf.innerHTML = kompakt ? '✎' : '✎ Feedback';
        document.body.appendChild(knopf);
    }

    function laden() {
        if (window.html2canvas) { return Promise.resolve(); }
        return new Promise((ok, fehler) => {
            const s = document.createElement('script');
            s.src = bibliothek;
            s.onload = ok;
            s.onerror = () => fehler(new Error('Screenshot-Bibliothek nicht ladbar'));
            document.head.appendChild(s);
        });
    }

    /** Sichtbarer Ausschnitt als JPEG (max. 1600 px breit). */
    async function aufnehmen() {
        await laden();
        const faktor = Math.min(1, 1600 / window.innerWidth) * Math.min(window.devicePixelRatio || 1, 1.5);
        const leinwand = await window.html2canvas(document.body, {
            x: window.scrollX, y: window.scrollY, width: window.innerWidth, height: window.innerHeight,
            scale: faktor, logging: false, useCORS: true, backgroundColor: '#ffffff',
            // nur den sichtbaren Ausschnitt nachzeichnen – lange Listen sonst sehr langsam
            ignoreElements: (el) => {
                const r = el.getBoundingClientRect();
                return (r.width || r.height) > 0 && (r.bottom < 0 || r.top > window.innerHeight || r.right < 0 || r.left > window.innerWidth);
            },
            // html2canvas kennt den Zustand von <details> nicht: Inhalt geschlossener Elemente entfernen
            onclone: (kopie) => kopie.querySelectorAll('details:not([open]) > :not(summary)').forEach((el) => el.remove()),
        });
        return leinwand.toDataURL('image/jpeg', 0.75);
    }

    function esc(t) {
        return String(t).replace(/[&<>"']/g, (z) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[z]));
    }

    function seite() {
        return location.pathname + location.search;
    }

    function dialogZeigen() {
        const hinter = document.createElement('div');
        hinter.className = 'fb-hinter';
        hinter.setAttribute('data-html2canvas-ignore', '');
        hinter.innerHTML = `
            <div class="fb-dialog" role="dialog" aria-modal="true" aria-labelledby="fb-titel">
                <h2 id="fb-titel">Feedback geben</h2>
                <p class="fb-leise">Was ist Ihnen aufgefallen? Die Meldung geht per Mail an das Team von Taikai – Karate (${esc(ADRESSE)}) und an die Administration dieser Installation – mit Seite, Ihrem Namen und dem Screenshot.</p>
                <p class="fb-leise fb-datenschutz">Der Screenshot zeigt, was gerade auf dem Bildschirm steht – ggf. auch Namen oder Geburtsdaten. Dann bitte „Screenshot nicht mitsenden“ wählen.</p>
                <div class="fb-arten">${ARTEN.map(([w, t]) => `<button type="button" data-art="${w}" class="${w === art ? 'aktiv' : ''}">${t}</button>`).join('')}</div>
                <label class="fb-label" for="fb-text">Beschreibung</label>
                <textarea id="fb-text" rows="5" maxlength="5000" placeholder="Was haben Sie getan, was ist passiert, was hätten Sie erwartet?"></textarea>
                <div class="fb-leise">Seite: ${esc(seite())}</div>
                <div class="fb-bild">${screenshot
                    ? `<img src="${screenshot}" alt="Screenshot der Seite"><button type="button" class="fb-link" data-fb-aktion="bild">Screenshot nicht mitsenden</button>`
                    : '<span class="fb-leise">Kein Screenshot verfügbar.</span>'}</div>
                <div class="fb-meldung" hidden></div>
                <div class="fb-knoepfe">
                    <button type="button" class="fb-senden" data-fb-aktion="senden" disabled>Senden</button>
                    <button type="button" class="fb-abbrechen" data-fb-aktion="abbrechen">Abbrechen</button>
                </div>
            </div>`;
        document.body.appendChild(hinter);
        const text = hinter.querySelector('#fb-text');
        const senden = hinter.querySelector('[data-fb-aktion="senden"]');
        const meldung = hinter.querySelector('.fb-meldung');
        text.focus();
        text.addEventListener('input', () => { senden.disabled = text.value.trim() === ''; });
        const schliessen = () => { hinter.remove(); document.removeEventListener('keydown', taste); };
        const taste = (ev) => { if (ev.key === 'Escape') { schliessen(); } };
        document.addEventListener('keydown', taste);
        hinter.addEventListener('click', async (ev) => {
            if (ev.target === hinter) { schliessen(); return; }
            // eigene Kennung data-fb-aktion: Klicks im Dialog dürfen keine Aktionen der Seite auslösen
            ev.stopPropagation();
            const b = ev.target.closest('button');
            if (!b) { return; }
            if (b.dataset.art) {
                art = b.dataset.art;
                hinter.querySelectorAll('[data-art]').forEach((x) => x.classList.toggle('aktiv', x === b));
            } else if (b.dataset.fbAktion === 'bild') {
                mitScreenshot = !mitScreenshot;
                hinter.querySelector('.fb-bild img').classList.toggle('aus', !mitScreenshot);
                b.textContent = mitScreenshot ? 'Screenshot nicht mitsenden' : 'Screenshot doch mitsenden';
            } else if (b.dataset.fbAktion === 'abbrechen') {
                schliessen();
            } else if (b.dataset.fbAktion === 'senden') {
                senden.disabled = true;
                senden.textContent = 'Wird gesendet …';
                try {
                    const antwort = await fetch(sendenUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.body.dataset.csrf || '' },
                        body: JSON.stringify({
                            art, beschreibung: text.value, seite: seite(),
                            geraet: `${window.innerWidth}×${window.innerHeight} · ${navigator.userAgent}`,
                            screenshot: mitScreenshot ? screenshot : null,
                        }),
                    });
                    const json = await antwort.json().catch(() => ({}));
                    if (!antwort.ok) { throw new Error(json.fehler || `Fehler ${antwort.status}`); }
                    hinter.querySelector('.fb-dialog').innerHTML = '<h2>Danke!</h2><p>Ihr Feedback ist angekommen.</p>';
                    setTimeout(schliessen, 1600);
                } catch (e) {
                    meldung.hidden = false;
                    meldung.textContent = 'Senden fehlgeschlagen: ' + e.message;
                    senden.disabled = false;
                    senden.textContent = 'Senden';
                }
            }
        });
    }

    knopf.addEventListener('click', async () => {
        knopf.disabled = true;
        knopf.classList.add('laedt');
        screenshot = null;
        mitScreenshot = true;
        try {
            screenshot = await aufnehmen();
        } catch (e) {
            screenshot = null;
        }
        knopf.disabled = false;
        knopf.classList.remove('laedt');
        dialogZeigen();
    });
})();
