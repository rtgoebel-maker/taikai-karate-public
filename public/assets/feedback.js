/* Feedback-Knopf: Dialog mit Art, Beschreibung, Seite und Screenshot (html2canvas, beim ersten Klick geladen). */
'use strict';

(() => {
    const skript = document.currentScript;
    const sendenUrl = skript.dataset.senden;
    const bibliothek = skript.dataset.bibliothek;
    const kompakt = skript.dataset.kompakt === '1';
    const ADRESSE = skript.dataset.adresse || 'feedback@taikai-karate.de';
    const ARTEN = [['fehler', Wettkampf.t('Fehler')], ['vorschlag', Wettkampf.t('Verbesserungsvorschlag')], ['frage', Wettkampf.t('Frage')]];
    let screenshot = null;
    let mitScreenshot = true;
    let art = 'fehler';

    const knopf = document.createElement('button');
    knopf.type = 'button';
    knopf.className = 'fb-knopf' + (kompakt ? ' kompakt' : '');
    knopf.setAttribute('data-html2canvas-ignore', '');
    knopf.title = Wettkampf.t('Feedback geben');
    // Tablet/Ausruf: Platz in der Kopfzeile, damit der Knopf keine Bedienelemente verdeckt
    const platz = document.querySelector('[data-feedback-platz]');
    if (platz) {
        knopf.className = 'fb-knopf eingebettet ' + (platz.dataset.feedbackPlatz || '');
        knopf.textContent = '✎ ' + Wettkampf.t('Feedback');
        platz.appendChild(knopf);
    } else {
        knopf.textContent = kompakt ? '✎' : '✎ ' + Wettkampf.t('Feedback');
        document.body.appendChild(knopf);
    }

    function laden() {
        if (window.html2canvas) { return Promise.resolve(); }
        return new Promise((ok, fehler) => {
            const s = document.createElement('script');
            s.src = bibliothek;
            s.onload = ok;
            s.onerror = () => fehler(new Error(Wettkampf.t('Screenshot-Bibliothek nicht ladbar')));
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
            onclone: (kopie) => {
                // html2canvas kennt den Zustand von <details> nicht: Inhalt geschlossener Elemente entfernen
                kopie.querySelectorAll('details:not([open]) > :not(summary)').forEach((el) => el.remove());
                farbenVereinfachen(kopie);
            },
        });
        return leinwand.toDataURL('image/jpeg', 0.75);
    }

    const FARB_EIGENSCHAFTEN = ['color', 'background-color', 'background-image', 'border-top-color', 'border-right-color',
        'border-bottom-color', 'border-left-color', 'outline-color', 'text-decoration-color', 'box-shadow', 'text-shadow'];
    const MODERNE_FARBE = /\b(?:color|color-mix|oklch|oklab|lch|lab|hwb)\(/;

    /** Kanalwert aus color(srgb …): 0–1 oder Prozent → 0–255 */
    function kanal(w) {
        const z = parseFloat(w);
        return Math.round(Math.min(1, Math.max(0, w.endsWith('%') ? z / 100 : z)) * 255);
    }

    /**
     * html2canvas 1.4 bricht bei modernen Farbfunktionen ab (z. B. color-mix(), vom Browser als color(srgb …)
     * berechnet) – dann gäbe es gar keinen Screenshot. In der Kopie solche Werte in rgba() umrechnen,
     * was sich nicht umrechnen lässt, neutral ersetzen.
     */
    function farbenVereinfachen(kopie) {
        const fenster = kopie.defaultView || window;
        kopie.querySelectorAll('*').forEach((el) => {
            const stil = fenster.getComputedStyle(el);
            FARB_EIGENSCHAFTEN.forEach((eig) => {
                const wert = stil.getPropertyValue(eig);
                if (!MODERNE_FARBE.test(wert)) { return; }
                let neu = wert.replace(/color\(srgb\s+([\d.]+%?)\s+([\d.]+%?)\s+([\d.]+%?)(?:\s*\/\s*([\d.]+%?))?\)/g,
                    (_, r, g, b, a) => `rgba(${kanal(r)}, ${kanal(g)}, ${kanal(b)}, ${a === undefined ? 1 : kanal(a) / 255})`);
                if (MODERNE_FARBE.test(neu)) {
                    neu = eig === 'color' ? 'rgb(0, 0, 0)' : (eig === 'background-image' || eig.endsWith('shadow') ? 'none' : 'transparent');
                }
                el.style.setProperty(eig, neu, 'important');
            });
        });
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
                <h2 id="fb-titel">${esc(Wettkampf.t('Feedback geben'))}</h2>
                <p class="fb-leise">${esc(Wettkampf.t('Was ist Ihnen aufgefallen? Die Meldung geht per Mail an das Team von Taikai – Karate ({adresse}) und an die Administration dieser Installation – mit Seite, Ihrem Namen und dem Screenshot.', { adresse: ADRESSE }))}</p>
                <p class="fb-leise fb-datenschutz">${esc(Wettkampf.t('Der Screenshot zeigt, was gerade auf dem Bildschirm steht – ggf. auch Namen oder Geburtsdaten. Dann bitte „Screenshot nicht mitsenden“ wählen.'))}</p>
                <div class="fb-arten">${ARTEN.map(([w, t]) => `<button type="button" data-art="${w}" class="${w === art ? 'aktiv' : ''}">${esc(t)}</button>`).join('')}</div>
                <label class="fb-label" for="fb-text">${esc(Wettkampf.t('Beschreibung'))}</label>
                <textarea id="fb-text" rows="5" maxlength="5000" placeholder="${esc(Wettkampf.t('Was haben Sie getan, was ist passiert, was hätten Sie erwartet?'))}"></textarea>
                <div class="fb-leise">${esc(Wettkampf.t('Seite: {seite}', { seite: seite() }))}</div>
                <div class="fb-bild">${screenshot
                    ? `<img src="${screenshot}" alt="${esc(Wettkampf.t('Screenshot der Seite'))}"><button type="button" class="fb-link" data-fb-aktion="bild">${esc(Wettkampf.t('Screenshot nicht mitsenden'))}</button>`
                    : `<span class="fb-leise">${esc(Wettkampf.t('Kein Screenshot verfügbar.'))}</span>`}</div>
                <div class="fb-meldung" hidden></div>
                <div class="fb-knoepfe">
                    <button type="button" class="fb-senden" data-fb-aktion="senden" disabled>${esc(Wettkampf.t('Senden'))}</button>
                    <button type="button" class="fb-abbrechen" data-fb-aktion="abbrechen">${esc(Wettkampf.t('Abbrechen'))}</button>
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
                b.textContent = mitScreenshot ? Wettkampf.t('Screenshot nicht mitsenden') : Wettkampf.t('Screenshot doch mitsenden');
            } else if (b.dataset.fbAktion === 'abbrechen') {
                schliessen();
            } else if (b.dataset.fbAktion === 'senden') {
                senden.disabled = true;
                senden.textContent = Wettkampf.t('Wird gesendet …');
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
                    if (!antwort.ok) { throw new Error(json.fehler || Wettkampf.t('Fehler {status}', { status: antwort.status })); }
                    hinter.querySelector('.fb-dialog').innerHTML = `<h2>${esc(Wettkampf.t('Danke!'))}</h2><p>${esc(Wettkampf.t('Ihr Feedback ist angekommen.'))}</p>`;
                    setTimeout(schliessen, 1600);
                } catch (e) {
                    meldung.hidden = false;
                    meldung.textContent = Wettkampf.t('Senden fehlgeschlagen: {grund}', { grund: e.message });
                    senden.disabled = false;
                    senden.textContent = Wettkampf.t('Senden');
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
