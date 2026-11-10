/* Wettkampf – kleine Helfer ohne Abhängigkeiten. */
'use strict';

const Wettkampf = {
    csrf() {
        return document.body.dataset.csrf || '';
    },

    /**
     * App-Dialog statt der Browser-Fenster (confirm/alert/prompt): einheitlich auf Tablet und PC.
     * optionen: titel, ja (Text des Bestätigungsknopfs), nein (Abbrechen, null = nur ein Knopf),
     * art ('gefahr' | 'aka' | 'shiro' | ''), symbol, eingabe (Feld, z. B. {typ: 'password'}).
     * @returns {Promise<boolean|string|null>} true/false, bei Eingabe der Text bzw. null
     */
    dialog(text, optionen = {}) {
        const o = { titel: '', ja: 'OK', nein: 'Abbrechen', art: '', symbol: '', eingabe: null, ...optionen };
        const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        return new Promise((fertig) => {
            const vorher = document.activeElement;
            const hinter = document.createElement('div');
            hinter.className = 'app-dialog-hinter';
            hinter.innerHTML = `<div class="app-dialog ${esc(o.art)}" role="dialog" aria-modal="true">
                ${o.symbol ? `<div class="app-dialog-symbol">${esc(o.symbol)}</div>` : ''}
                ${o.titel ? `<h2>${esc(o.titel)}</h2>` : ''}
                <p>${esc(text).replace(/\n/g, '<br>')}</p>
                ${o.eingabe ? `<input class="app-dialog-eingabe" type="${o.eingabe.typ === 'password' ? 'password' : 'text'}" inputmode="${esc(o.eingabe.inputmode || 'text')}" autocomplete="off">` : ''}
                <div class="app-dialog-knoepfe">
                    ${o.nein !== null ? `<button type="button" class="app-dialog-nein" data-antwort="nein">${esc(o.nein)}</button>` : ''}
                    <button type="button" class="app-dialog-ja" data-antwort="ja">${esc(o.ja)}</button>
                </div></div>`;
            const feld = hinter.querySelector('.app-dialog-eingabe');
            const schliessen = (ja) => {
                hinter.remove();
                document.removeEventListener('keydown', taste, true);
                if (vorher && vorher.focus) { vorher.focus(); }
                fertig(o.eingabe ? (ja ? feld.value : null) : ja);
            };
            const taste = (ev) => {
                if (ev.key === 'Escape') { ev.preventDefault(); schliessen(false); }
                if (ev.key === 'Enter') { ev.preventDefault(); schliessen(true); }
            };
            hinter.addEventListener('click', (ev) => {
                ev.stopPropagation(); // keine Aktionen der Seite darunter auslösen
                const b = ev.target.closest('[data-antwort]');
                if (b) { schliessen(b.dataset.antwort === 'ja'); } else if (ev.target === hinter && o.nein !== null) { schliessen(false); }
            });
            document.addEventListener('keydown', taste, true);
            document.body.appendChild(hinter);
            (feld || hinter.querySelector('.app-dialog-ja')).focus();
        });
    },

    /** Rückfrage (statt confirm) */
    frage(text, optionen = {}) {
        return this.dialog(text, optionen);
    },

    /** Hinweis mit einem Knopf (statt alert) */
    hinweis(text, optionen = {}) {
        return this.dialog(text, { nein: null, ...optionen });
    },

    /**
     * POST mit JSON. optionen.befehl (true oder eine ID aus befehlsId()): Befehl mit eindeutiger ID senden und bei
     * Netzfehlern bzw. 409/502/503/504 bis zu dreimal automatisch wiederholen – der Server führt ihn trotzdem nur einmal
     * aus (Middleware EinmaligeBefehle). Scheitert alles, hat der Fehler err.unklar = true: Der Befehl ist evtl. schon
     * angekommen; erneutes Senden mit derselben ID ist gefahrlos.
     */
    async post(url, daten, optionen = {}) {
        const id = optionen.befehl === true ? this.befehlsId() : (optionen.befehl || null);
        const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf() };
        if (id) { headers['X-Befehl-Id'] = id; }
        const versuche = id ? 4 : 1;
        let letzterFehler = null;
        for (let versuch = 0; versuch < versuche; versuch++) {
            if (versuch > 0) { await new Promise((ok) => setTimeout(ok, 800 * 2 ** (versuch - 1))); }
            let antwort;
            try {
                antwort = await fetch(url, { method: 'POST', headers, body: JSON.stringify(daten || {}), credentials: 'same-origin' });
            } catch (netz) {
                letzterFehler = netz;
                continue; // keine Antwort: wiederholen (nur mit Befehls-ID)
            }
            if (id && [409, 502, 503, 504].includes(antwort.status)) {
                letzterFehler = new Error(`Fehler ${antwort.status}`);
                continue;
            }
            const json = await antwort.json().catch(() => ({}));
            if (!antwort.ok) {
                throw new Error(json.fehler || `Fehler ${antwort.status}`);
            }
            return json;
        }
        const fehler = new Error('Keine Antwort vom Server' + (letzterFehler && letzterFehler.message ? ` (${letzterFehler.message})` : ''));
        fehler.unklar = true;
        throw fehler;
    },

    /** Zufällige Befehls-ID (auch ohne HTTPS im Hallennetz, wo crypto.randomUUID fehlt). */
    befehlsId() {
        const b = new Uint8Array(16);
        crypto.getRandomValues(b);
        return Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
    },

    /**
     * Nachrichten der Wettkampfleitung (Tablet/Ausruf): Einblendung über der Kopfzeile,
     * unabhängig von der Kampfkarte – eine begonnene Eingabe bleibt unberührt. Jede
     * Nachricht bleibt stehen, bis „Gelesen“ getippt wird (nurLesen: Leitungsansicht).
     */
    nachrichten(liste, bestaetigenUrl, nurLesen) {
        let box = document.getElementById('nachrichten');
        if (!box) {
            box = document.createElement('div');
            box.id = 'nachrichten';
            box.className = 'nachrichten';
            box.setAttribute('role', 'alert');
            document.body.appendChild(box);
            box.addEventListener('click', async (ev) => {
                const knopf = ev.target.closest('[data-nachricht]');
                if (!knopf) { return; }
                knopf.disabled = true;
                try {
                    await this.post(bestaetigenUrl(knopf.dataset.nachricht), {});
                    knopf.closest('.nachricht').remove();
                    box.hidden = !box.children.length;
                } catch (e) {
                    knopf.disabled = false;
                    Wettkampf.hinweis(e.message, { titel: 'Fehler', art: 'gefahr' });
                }
            });
        }
        const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        box.innerHTML = (liste || []).map((n) => `<div class="nachricht">
            <span class="nachricht-symbol">✉</span>
            <div class="nachricht-text"><strong>${esc(n.text)}</strong><span>Wettkampfleitung · ${esc(String(n.erstellt_am).slice(11, 16))} Uhr</span></div>
            ${nurLesen ? '<span class="nachricht-offen">wartet auf Bestätigung am Tisch</span>' : `<button type="button" data-nachricht="${n.id}">✓ Gelesen</button>`}
        </div>`).join('');
        box.hidden = !box.children.length;
    },

    /**
     * Fragt regelmäßig die Revision ab und ruft bei Änderung neuLaden() auf.
     * Robust bei Verbindungsabbrüchen (WLAN in der Halle).
     */
    beobachten(revisionUrl, neuLaden, intervallMs = 4000) {
        let bekannt = null;
        const pruefen = async () => {
            try {
                const antwort = await fetch(revisionUrl, { cache: 'no-store', credentials: 'same-origin' });
                const { revision } = await antwort.json();
                if (bekannt !== null && revision !== bekannt) {
                    await neuLaden();
                }
                bekannt = revision;
                document.body.classList.remove('offline');
            } catch (e) {
                document.body.classList.add('offline');
            }
        };
        pruefen();
        return setInterval(pruefen, intervallMs);
    },
};

/* Bestätigungsabfrage für Formulare mit data-bestaetigen="Text" (App-Dialog; nach „OK“ wird erneut abgeschickt) */
document.addEventListener('submit', (ereignis) => {
    const form = ereignis.target;
    const text = form.dataset && form.dataset.bestaetigen;
    if (!text || form.dataset.bestaetigt === '1') { return; }
    ereignis.preventDefault();
    const absender = ereignis.submitter;
    Wettkampf.frage(text, { ja: form.dataset.bestaetigenJa || 'OK' }).then((ok) => {
        if (!ok) { return; }
        form.dataset.bestaetigt = '1';
        form.requestSubmit(absender && absender.form === form ? absender : undefined);
        delete form.dataset.bestaetigt;
    });
});

/* Info-Knöpfe (ⓘ): immer nur einer offen, Klick daneben schließt */
document.addEventListener('click', (ev) => {
    const offen = ev.target.closest('details.info');
    document.querySelectorAll('details.info[open]').forEach((d) => { if (d !== offen) { d.open = false; } });
});
document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') { document.querySelectorAll('details.info[open]').forEach((d) => { d.open = false; }); }
});

/* Seite bei Änderungen im Turnier neu laden: <div hidden data-aktualisieren="Revisions-URL" data-intervall="ms"> */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-aktualisieren]').forEach((el) => {
        Wettkampf.beobachten(el.dataset.aktualisieren, () => location.reload(), parseInt(el.dataset.intervall || '4000', 10));
    });
});

/*
 * Vollbild-Knopf (data-vollbild) für Tisch und Ausruf: blendet Adress- und Tableiste des Handy-/Tablet-Browsers aus.
 * Nur sichtbar, wo der Browser es kann (nicht im iPhone-Safari) und die Seite nicht schon als App vom Startbildschirm läuft.
 */
document.addEventListener('DOMContentLoaded', () => {
    const knoepfe = document.querySelectorAll('[data-vollbild]');
    const el = document.documentElement;
    const anfordern = el.requestFullscreen || el.webkitRequestFullscreen;
    const beenden = document.exitFullscreen || document.webkitExitFullscreen;
    const aktiv = () => document.fullscreenElement || document.webkitFullscreenElement;
    const alsApp = window.matchMedia('(display-mode: fullscreen), (display-mode: standalone)').matches || navigator.standalone === true;
    if (!knoepfe.length || !anfordern || alsApp || !(document.fullscreenEnabled || document.webkitFullscreenEnabled)) { return; }
    const anzeigen = () => knoepfe.forEach((k) => {
        k.classList.toggle('an', !!aktiv());
        k.title = aktiv() ? 'Vollbild beenden' : 'Vollbild: Adress- und Tableiste ausblenden';
    });
    knoepfe.forEach((k) => {
        k.hidden = false;
        k.addEventListener('click', () => {
            const ergebnis = aktiv() ? beenden.call(document) : anfordern.call(el, { navigationUI: 'hide' });
            if (ergebnis && ergebnis.catch) { ergebnis.catch(() => {}); }
        });
    });
    document.addEventListener('fullscreenchange', anzeigen);
    document.addEventListener('webkitfullscreenchange', anzeigen);
});

/* Links „Mehr in der Hilfe“ innerhalb eines Turniers: Turniermenü auf der Hilfeseite behalten */
document.addEventListener('DOMContentLoaded', () => {
    const turnier = /\/turnier\/(\d+)/.exec(location.pathname);
    if (!turnier) { return; }
    document.querySelectorAll('details.info .info-text a').forEach((a) => {
        const ziel = new URL(a.href, location.href);
        ziel.searchParams.set('turnier', turnier[1]);
        a.href = ziel.href;
    });
});
