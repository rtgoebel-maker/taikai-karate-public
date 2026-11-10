/* Tablet-Oberfläche der Tischbesatzung. Benötigt app.js (Wettkampf.post/beobachten). */
'use strict';

(() => {
    const body = document.body;
    const T = {
        api: body.dataset.api,
        query: body.dataset.query || '',
        leitung: body.dataset.leitung === '1',
        pinPflicht: body.dataset.pinPflicht === '1',
        zustand: null,
        aktuell: null,
        werte: {},
        korrektur: null, // { pin }
        uebergang: null, // nach dem Gruppen-Abschluss: { klasse } bis „Weiter“
        dirty: false,
        sendet: false,
    };
    const KATAS = ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Tekki Nidan', 'Tekki Sandan',
        'Bassai Dai', 'Bassai Sho', 'Kanku Dai', 'Kanku Sho', 'Jion', 'Jitte', 'Enpi', 'Hangetsu', 'Gankaku', 'Chinte', 'Meikyo',
        'Nijushiho', 'Sochin', 'Unsu', 'Gojushiho Dai', 'Gojushiho Sho', 'Wankan'];
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const zahl = (t) => { const z = parseFloat(String(t ?? '').replace(',', '.')); return Number.isFinite(z) ? z : null; };
    const url = (pfad) => T.api + pfad + T.query;
    /** Aktion mit eindeutiger Befehls-ID: wird bei WLAN-Aussetzern automatisch wiederholt, wirkt aber nur einmal. */
    const befehl = (pfad, daten, id) => Wettkampf.post(url(pfad), daten, { befehl: id || true });
    /** Kämpfe, die dieses Tablet schon automatisch gestartet hat (nicht erneut nach „unterbrechen“). */
    const automatischGestartet = new Set();
    /** Ergebnisse nur, wenn die Leitung das Turnier gestartet hat (Phase „läuft“). */
    const turnierLaeuft = () => !T.zustand || !T.zustand.phase || T.zustand.phase === 'laeuft';
    const PHASE_HINWEIS = {
        vorbereitung: 'Das Turnier ist noch nicht gestartet. Ergebnisse können erst eingetragen werden, wenn die Wettkampfleitung das Turnier startet.',
        meldephase: 'Das Turnier ist noch nicht gestartet. Ergebnisse können erst eingetragen werden, wenn die Wettkampfleitung das Turnier startet.',
        abgeschlossen: 'Das Turnier ist abgeschlossen – Ergebnisse sind gesperrt.',
    };

    // --- Laden & Polling ------------------------------------------------------

    async function laden() {
        const antwort = await fetch(url(''), { cache: 'no-store', credentials: 'same-origin' });
        if (antwort.status === 401) { location.href = location.pathname.replace(/\/app$/, ''); return; }
        T.zustand = await antwort.json();
        // neuer Programmstand auf dem Server (Update): Seite neu laden – nicht mitten in einer Eingabe
        if (T.zustand.programm && document.body.dataset.programm && T.zustand.programm !== document.body.dataset.programm && !T.dirty && !T.korrektur) {
            location.reload();
            return;
        }
        uebergangWiederherstellen();
        if (!T.dirty) {
            const neu = T.zustand.laufend || T.zustand.naechste[0] || null;
            if (!T.aktuell || !neu || neu.id !== T.aktuell.id || neu.typ !== T.aktuell.typ || neu.version !== T.aktuell.version) {
                T.aktuell = neu;
                T.werte = startwerte(neu);
                T.korrektur = null;
            } else {
                T.aktuell = neu;
            }
            karteRendern();
        }
        listenRendern();
        ausrufStandRendern();
        Wettkampf.nachrichten(T.zustand.nachrichten, (id) => url('/nachricht/' + id + '/bestaetigen'), document.body.dataset.leitung === '1');
        automatischStarten();
    }

    /**
     * Der nächste Kampf beginnt automatisch, sobald er auf dem Tablet ansteht – nicht beim
     * Gruppenabschluss, bei einer Korrektur oder begonnener Eingabe und nicht in der Leitungsansicht.
     * Unterbrechen/Fortsetzen geht über den Knopf unter der Karte.
     */
    function automatischStarten() {
        const e = T.aktuell;
        // fehlt laut Ausrufer ein Starter, bestätigt die Tischbesatzung erst (Kiken oder „doch da“)
        if (!e || e.typ !== 'begegnung' || e.status !== 'bereit' || document.body.dataset.leitung === '1' || fehltSeiten(e).length
            || T.dirty || T.korrektur || T.uebergang || (T.zustand && T.zustand.abschluss) || !turnierLaeuft()
            || automatischGestartet.has(e.id)) { return; }
        automatischGestartet.add(e.id);
        befehl('/begegnung/' + e.id + '/starten', {}).then(laden).catch(() => {});
    }

    /**
     * Übergang zur neuen Gruppe kommt vom Server (überlebt das Neuladen der Seite), bis die
     * Tischbesatzung „Weiter“ tippt – das merkt sich das Tablet je Abschluss.
     */
    function uebergangSchluessel() {
        return 'uebergang-weiter' + T.query;
    }
    function uebergangWiederherstellen() {
        const u = T.zustand && T.zustand.uebergang;
        if (T.uebergang && !u && T.zustand && T.zustand.laufend) { T.uebergang = null; } // neue Gruppe hat begonnen
        if (!u || T.uebergang || T.korrektur) { return; }
        let weiter = null;
        try { weiter = sessionStorage.getItem(uebergangSchluessel()); } catch (e) { weiter = null; }
        if (weiter !== u.am) {
            T.uebergang = { klasse: u.klasse, am: u.am };
        }
    }

    /** Seiten, die der Ausrufer als „fehlt“ gemeldet hat (aka/shiro bzw. starter). */
    function fehltSeiten(e) {
        const a = (e && e.aufruf) || {};
        return ['aka', 'shiro', 'starter'].filter((s) => a[s] === 'fehlt');
    }

    /**
     * Ausrufer meldet „fehlt“: Die Tischbesatzung bestätigt, bevor es offiziell gilt –
     * Kiken (Gegner gewinnt kampflos bzw. Vortrag ohne Wertung, weiter zum nächsten Kampf)
     * oder „doch da“ (Ausruf auf da, Kampf beginnt).
     */
    function fehltBestaetigung(e) {
        const fehlt = fehltSeiten(e);
        if (!fehlt.length || T.korrektur || !turnierLaeuft()) { return ''; }
        const person = (s) => (s === 'starter' ? e.starter : e[s]) || {};
        const kennung = (s) => (s === 'starter' ? 'Starter' : s.toUpperCase());
        const dochDa = fehlt.map((s) => `<button class="gross-knopf neutral" data-aktion="doch-da" data-seite="${s}">${esc(person(s).name)} ist doch da${e.typ === 'begegnung' && fehlt.length === 1 ? ' – Kampf beginnen' : ''}</button>`).join('');
        if (e.typ === 'begegnung' && fehlt.length === 2) {
            return `<div class="fehlt-bestaetigung"><strong>Laut Ausrufer fehlen beide Starter.</strong>
                <p>Bitte unten unter „Kampflos / Disqualifikation“ entscheiden oder die Wettkampfleitung fragen.</p><div class="knoepfe">${dochDa}</div></div>`;
        }
        const s = fehlt[0];
        const ergebnis = e.typ === 'auftritt'
            ? 'Kiken – Vortrag ohne Wertung'
            : `Kiken – ${s === 'aka' ? 'SHIRO' : 'AKA'} (${esc(person(s === 'aka' ? 'shiro' : 'aka').name)}) gewinnt kampflos`;
        return `<div class="fehlt-bestaetigung">
            <strong>Laut Ausrufer fehlt ${kennung(s)}: ${esc(person(s).name)}</strong>
            <p>Bitte bestätigen, bevor es gilt. Danach geht es direkt mit dem nächsten Kampf weiter.</p>
            <div class="knoepfe">
                <button class="gross-knopf senden" data-aktion="fehlt-bestaetigen" data-gegen="${s}">✓ Bestätigen: ${ergebnis}</button>
                ${dochDa}
            </div></div>`;
    }

    /**
     * Jiyu-Kumite: Wer direkt nach seinem letzten Kampf wieder antritt, wird gefragt, ob er eine
     * Pause braucht – bei Bedarf „Kampf unterbrechen“.
     */
    function pauseHinweis(e) {
        if (!e || e.typ !== 'begegnung' || e.kumite_form !== 'jiyu' || T.korrektur || !(e.direkt_wieder || []).length) { return ''; }
        const namen = e.direkt_wieder.map((s) => `<strong>${esc(e[s].name)}</strong> (${s === 'aka' ? 'AKA' : 'SHIRO'})`).join(' und ');
        return `<div class="pause-hinweis">⏸ ${namen} ${e.direkt_wieder.length > 1 ? 'haben' : 'hat'} gerade gekämpft und ${e.direkt_wieder.length > 1 ? 'treten' : 'tritt'} direkt wieder an.
            Bitte fragen, ob eine Pause benötigt wird – dann „Kampf unterbrechen“ und später fortsetzen.</div>`;
    }

    /** Gespeicherte Wertung (7.2) wie eingegeben anzeigen (7,2). */
    function komma(x) {
        return String(x).replace('.', ',');
    }

    function startwerte(e) {
        if (!e) { return {}; }
        const w = e.wertung || {};
        if (e.typ === 'auftritt') {
            return { werte: (w.werte || []).map(komma), kata: e.kata || '' };
        }
        const v = { kata_aka: e.kata_aka || '', kata_shiro: e.kata_shiro || '' };
        if (e.system === 'flaggen' && w.flaggen) { v.flaggen_aka = w.flaggen.aka; v.flaggen_shiro = w.flaggen.shiro; }
        if (istKumite(e)) {
            for (const s of ['aka', 'shiro']) {
                v[s + '_ippon'] = (w[s] && w[s].ippon) || 0;
                v[s + '_wazaari'] = (w[s] && w[s].wazaari) || 0;
                v[s + '_strafen'] = { ...KEINE_STRAFEN, ...((w[s] && w[s].strafen) || {}) };
            }
            if (w.kaempfe) { v.kaempfe = w.kaempfe; }
            if (w.saishiai && w.hauptkampf) {
                v.saishiai = true;
                v.hauptkampf = {};
                for (const s of ['aka', 'shiro']) {
                    const h = w.hauptkampf[s] || {};
                    Object.assign(v.hauptkampf, { [s + '_ippon']: h.ippon || 0, [s + '_wazaari']: h.wazaari || 0, [s + '_strafen']: { ...KEINE_STRAFEN, ...(h.strafen || {}) } });
                }
            }
            v.zeit_abgelaufen = !!(w.zeit_abgelaufen || w.flaggen);
        }
        if (e.system === 'punkte') {
            v.aka_werte = ((w.aka && w.aka.werte) || []).map(komma);
            v.shiro_werte = ((w.shiro && w.shiro.werte) || []).map(komma);
        }
        if (w.flaggen && e.system !== 'flaggen') { v.hantei_aka = w.flaggen.aka; v.hantei_shiro = w.flaggen.shiro; }
        return v;
    }

    // --- Darstellung ------------------------------------------------------------

    /** Runde gut sichtbar: Vorrunde, Viertelfinale, Halbfinale, Kampf um Platz 3, Finale. */
    function rundeMarke(e, klein = false) {
        let text = e.runde || '';
        let art = 'vorrunde';
        if (e.typ === 'auftritt') {
            art = /final/i.test(text) ? 'finale' : 'vorrunde';
        } else if (e.rundenart === 'jgj') {
            text += ' · jeder gegen jeden';
        } else if (e.rundenart === 'platz3') {
            art = 'platz3';
        } else if (text === 'Finale') {
            art = 'finale';
        } else if (text === 'Halbfinale') {
            art = 'halbfinale';
        } else if (text === 'Viertelfinale') {
            art = 'viertelfinale';
        } else if (/^Runde \d+$/.test(text)) {
            text = 'Vorrunde';
        }
        return `<span class="runde-marke r-${art}${klein ? ' klein' : ''}">${esc(text)}</span>`;
    }

    /** Stand des Ausrufs als Marke (Ansicht „Ausruf“ der Tischbesatzung). */
    function ausrufMarke(e) {
        const a = e.aufruf;
        if (!a) { return ''; }
        const fehlt = Object.entries(a).filter(([k, v]) => !['fertig', 'am'].includes(k) && v === 'fehlt').map(([k]) => k === 'starter' ? 'Starter' : k.toUpperCase());
        if (!a.fertig) { return '<span class="marke ausruf-offen" title="Der Ausrufer hat die Starter noch nicht bestätigt">Ausruf offen</span>'; }
        return fehlt.length
            ? `<span class="marke ausruf-fehlt">Ausruf: ${fehlt.join(', ')} fehlt</span>`
            : '<span class="marke ausruf-bereit">Ausruf ✓ bereit</span>';
    }


    function listenRendern() {
        const z = T.zustand;
        $('fortschritt').textContent = `${z.gesperrt ? '🔒 gesperrt · ' : ''}${z.beendet} beendet · ${z.offen} offen`;
        $('fortschritt').title = z.gesperrt ? 'Von der Wettkampfleitung gesperrt – keine neuen Gruppen' + (z.sperr_grund ? ': ' + z.sperr_grund : '') : '';
        $('naechste').innerHTML = z.naechste.filter((e) => !T.aktuell || e.id !== T.aktuell.id || e.typ !== T.aktuell.typ).slice(0, 8).map((e) => `
            <li><strong>${esc(e.klasse)}</strong> · ${e.typ === 'auftritt' ? 'Vortrag ' + esc(e.nr) : 'Kampf ' + esc(e.nr)} ${rundeMarke(e, true)} ${ausrufMarke(e)}<br>
            ${e.typ === 'auftritt' ? esc(e.starter.name) : `<span style="color:var(--aka)">${esc(e.aka.name)}</span> – ${esc(e.shiro.name)}`}</li>`).join('')
            || '<li class="klein">keine weiteren</li>';
        $('letzte').innerHTML = (z.letzte || []).map((e) => `
            <li>${e.typ === 'auftritt'
                ? `<strong>${esc(e.klasse)}</strong> · Vortrag ${esc(e.nr)}<br>
                   ${esc(e.starter.name)}: <strong>${esc(e.ergebnis)}</strong>`
                : `<strong>${esc(e.klasse)}</strong> · Kampf ${esc(e.nr)}<br>
                   Sieger: <strong>${esc(e.sieger === 'aka' ? e.aka.name : e.shiro.name)}</strong> <span class="klein">(${esc(e.entscheidung_text || e.entscheidung)})</span>`}<br>
            <button class="knopf-korrektur" data-aktion="korrigieren" data-typ="${e.typ}" data-id="${e.id}">Korrigieren</button></li>`).join('')
            || '<li class="klein">noch keine</li>';
    }

    /**
     * Gruppe, deren Ausruf gerade läuft (aktuelle Gruppe bzw. im Übergang die neue) – mit allen
     * Kämpfen/Vorträgen, deren Ausruf noch nicht abgeschlossen ist; null, wenn nichts offen ist.
     */
    function ausrufGruppe() {
        const z = T.zustand;
        if (!z) { return null; }
        const basis = T.uebergang ? (z.laufend || z.naechste[0]) : (T.aktuell || z.laufend || z.naechste[0]);
        if (!basis) { return null; }
        const eintraege = [z.laufend, ...z.naechste].filter((e) => e && e.klasse_id === basis.klasse_id && !(e.aufruf && e.aufruf.fertig));
        return eintraege.length ? { klasse: basis.klasse, klasse_id: basis.klasse_id, eintraege } : null;
    }

    /** Starter der Gruppe – jeder einmal, mit Kampf/Vortrag, Seite und Ausruf-Stand. */
    function ausrufStarter(eintraege) {
        const liste = new Map();
        for (const e of eintraege) {
            const seiten = e.typ === 'auftritt' ? [['starter', e.starter]] : [['aka', e.aka], ['shiro', e.shiro]];
            for (const [s, p] of seiten) {
                const id = (e.starts || {})[s];
                if (!id || !p || liste.has(id)) { continue; }
                liste.set(id, { id, name: p.name, verein: p.verein, seite: s, stand: (e.aufruf || {})[s] || null,
                    wo: e.typ === 'auftritt' ? `Vortrag ${e.nr}` : `Kampf ${e.nr}` });
            }
        }
        return [...liste.values()];
    }

    /**
     * Ausruf-Stand für die Tischbesatzung: wer ist da, wer fehlt – oben in der Seitenleiste,
     * solange der Ausruf der Gruppe läuft. Im Notfall (kein Ausrufer) kann sie selbst abhaken und
     * den Ausruf abschließen. Unabhängig von der Karte – eine begonnene Eingabe bleibt unberührt.
     */
    function ausrufStandRendern() {
        const ziel = $('ausruf-stand');
        if (!ziel) { return; }
        const g = turnierLaeuft() ? ausrufGruppe() : null;
        if (!g) { ziel.innerHTML = ''; return; }
        const starter = ausrufStarter(g.eintraege);
        const da = starter.filter((x) => x.stand === 'da').length;
        const fehlt = starter.filter((x) => x.stand === 'fehlt').length;
        const alleAbgehakt = starter.every((x) => x.stand);
        // Ausruf der aktuellen Gruppe nie gestartet (z. B. im Übergang „Weiter“ gewählt): Der Ausrufer sieht die Namen
        // erst nach dem Start bzw. sobald der erste Kampf/Vortrag läuft – hier nachholen (wie in der Ausruf-Ansicht)
        const z = T.zustand;
        const laeuftSchon = !!z.laufend && z.laufend.klasse_id === g.klasse_id && (z.laufend.status === 'laeuft' || !!z.laufend.gruppe_begonnen);
        const gestartet = laeuftSchon || [z.laufend, ...z.naechste].some((e) => e && e.klasse_id === g.klasse_id && (e.ausruf_freigegeben || e.gruppe_aufgerufen
            || (e.aufruf && (e.aufruf.fertig || e.aufruf.aka || e.aufruf.shiro || e.aufruf.starter))));
        const startKnopf = gestartet || T.uebergang ? '' : `<p class="ausruf-nicht-gestartet">Noch nicht gestartet – der Ausrufer sieht die Starter erst danach.</p>
            <button class="knopf-ausruf" data-aktion="ausruf-freigeben" data-klasse="${g.klasse_id}">📣 Ausruf starten</button>`;
        ziel.innerHTML = `<section class="ausruf-stand">
            <h2>📣 Ausruf ${esc(g.klasse)} <span class="zahl">${da} von ${starter.length} da${fehlt ? ` · <span class="fehlt-text">${fehlt} fehlt</span>` : ''}</span></h2>
            ${startKnopf}
            <ul>${starter.map((x) => `<li class="${x.stand || 'offen'}">
                <span class="stand-marke ${x.stand || 'offen'}">${x.stand === 'da' ? '✓ da' : x.stand === 'fehlt' ? '✗ fehlt' : 'offen'}</span>
                <span class="name">${esc(x.name)}</span> <span class="klein">${esc(x.verein)} · ${esc(x.wo)}${x.seite === 'starter' ? '' : ` · ${x.seite === 'aka' ? 'AKA' : 'SHIRO'}`}</span>
                ${T.ausrufSelbst ? `<span class="knoepfe">
                    <button data-aktion="ausruf-starter" data-klasse="${g.klasse_id}" data-start="${x.id}" data-status="${x.stand === 'da' ? '' : 'da'}" class="${x.stand === 'da' ? 'aktiv' : ''}">✓ da</button>
                    <button data-aktion="ausruf-starter" data-klasse="${g.klasse_id}" data-start="${x.id}" data-status="${x.stand === 'fehlt' ? '' : 'fehlt'}" class="${x.stand === 'fehlt' ? 'aktiv' : ''}">✗ fehlt</button></span>` : ''}
            </li>`).join('')}</ul>
            ${T.ausrufSelbst
                ? `<button class="knopf-ausruf" data-aktion="ausruf-gruppe-abschliessen" data-klasse="${g.klasse_id}"${alleAbgehakt ? '' : ' disabled'}>Ausruf Gruppe ${esc(g.klasse)} abschließen</button>
                   <button class="knopf-leise-hell" data-aktion="ausruf-selbst" data-an="0">Fertig</button>`
                : '<button class="knopf-leise-hell" data-aktion="ausruf-selbst" data-an="1">Notfall: am Tablet abhaken</button>'}
        </section>`;
    }

    /**
     * Ausruf der nächsten Gruppe starten: erst dann sieht der Ausrufer deren Starter.
     * Am besten schon während der letzten Kämpfe der laufenden Gruppe.
     */
    function ausrufStartLeiste(aktuelleKlasse) {
        const z = T.zustand;
        if (!z || !turnierLaeuft()) { return ''; }
        const g = z.naechste.find((e) => e.klasse_id !== aktuelleKlasse);
        if (!g) { return ''; }
        // gestartet, aufgerufen oder schon Starter abgehakt – wie in der Ausruf-Ansicht
        const begonnen = z.naechste.some((e) => e.klasse_id === g.klasse_id && (e.ausruf_freigegeben || e.gruppe_aufgerufen
            || (e.aufruf && (e.aufruf.fertig || e.aufruf.aka || e.aufruf.shiro || e.aufruf.starter))));
        if (begonnen) {
            return `<div class="ausruf-leiste gestartet">📣 Ausruf für die nächste Gruppe <strong>${esc(g.klasse)}</strong> läuft</div>`;
        }
        return `<div class="ausruf-leiste">Nächste Gruppe <strong>${esc(g.klasse)}</strong> <span class="klein">${esc(g.klasse_name)}</span>
            <button class="knopf-ausruf" data-aktion="ausruf-freigeben" data-klasse="${g.klasse_id}">📣 Ausruf starten</button></div>`;
    }

    /** Gruppe fertig: Tischbesatzung bestätigt den Abschluss (vor der nächsten Gruppe). */
    function abschlussRendern(a) {
        $('karte').innerHTML = `
            <div class="abschluss">
                <div class="abschluss-kopf">✓ Gruppe <strong>${esc(a.klasse)}</strong> ist fertig</div>
                <div class="klein">${esc(a.klasse_name)} · ${a.kaempfe} Kämpfe/Vorträge eingetragen</div>
                <h3>Platzierungen</h3>
                <ol class="podest-liste">${a.platzierungen.map((p) => `<li><span class="platz platz-${p.platz}">${p.platz}.</span> <strong>${esc(p.name)}</strong> <span class="klein">${esc(p.verein)}</span>${p.punkte === null || p.punkte === undefined ? '' : `<span class="punktzahl">${p.kiken ? 'Kiken' : Number(p.punkte).toFixed(1).replace('.', ',') + ' Punkte'}</span>`}</li>`).join('')
                    || '<li class="klein">– (Stichentscheid durch die Wettkampfleitung)</li>'}</ol>
                <p class="erklaerung">Bitte prüfen, ob alle Ergebnisse stimmen (Korrekturen rechts unter „Zuletzt eingetragen“). Mit dem Abschluss wird die nächste Gruppe geladen.</p>
                <button class="gross-knopf senden" data-aktion="gruppe-abschliessen" data-klasse="${a.klasse_id}">Gruppe ${esc(a.klasse)} abschließen</button>
                ${ausrufStartLeiste(a.klasse_id)}
            </div>`;
        ausrufStandRendern();
    }

    /** Nach dem Abschluss bzw. zu Turnierbeginn: Hinweis, dass die (erste) Gruppe geladen wird und der Ausruf beginnen soll. */
    function uebergangRendern() {
        const z = T.zustand;
        const erste = T.uebergang && T.uebergang.klasse === '';
        const laeuft = turnierLaeuft();
        const n = z.laufend || z.naechste[0];
        // Ausruf der neuen Gruppe: nicht gestartet → läuft → abgeschlossen (dann kann der Kampf beginnen)
        const gruppe = n ? [z.laufend, ...z.naechste].filter((e) => e && e.klasse_id === n.klasse_id) : [];
        const starter = ausrufStarter(gruppe);
        const da = starter.filter((x) => x.stand === 'da').length;
        const fehlt = starter.filter((x) => x.stand === 'fehlt');
        const abgeschlossen = gruppe.length > 0 && gruppe.every((e) => e.aufruf && e.aufruf.fertig);
        const begonnen = n && (n.ausruf_freigegeben || n.gruppe_aufgerufen || gruppe.some((e) => e.aufruf && (e.aufruf.fertig || e.aufruf.aka || e.aufruf.shiro || e.aufruf.starter)));
        let stand;
        if (!n) {
            stand = '<p>Alle Gruppen dieser Tatami sind abgeschlossen.</p>';
        } else if (abgeschlossen) {
            stand = `<p class="uebergang-fertig">✓ Ausruf abgeschlossen – ${fehlt.length
                ? `${da} von ${starter.length} da · <strong>${fehlt.length} fehlt</strong> (${fehlt.map((x) => esc(x.name)).join(', ')})`
                : `alle ${starter.length} Starter da`}</p>`;
        } else if (begonnen) {
            stand = `<p class="uebergang-hinweis">📣 Der Ausruf läuft – ${da} von ${starter.length} da${fehlt.length ? ` · ${fehlt.length} fehlt` : ''}. Sobald der Ausrufer ihn abschließt, steht es hier.</p>`;
        } else {
            stand = `<p class="uebergang-hinweis">${erste ? 'Die erste Gruppe dieser Tatami ist zugeteilt' : 'Die neue Gruppe wird geladen'} – <strong>bitte jetzt den Ausruf starten</strong>, damit der Ausrufer die Starter sieht.</p>
                <button class="gross-knopf start" data-aktion="ausruf-freigeben" data-klasse="${n.klasse_id}">📣 Ausruf Gruppe ${esc(n.klasse)} starten</button>`;
        }
        $('karte').innerHTML = `
            <div class="uebergang">
                <div class="uebergang-symbol">${abgeschlossen ? '✅' : '📣'}</div>
                <h2>${n ? `${erste ? 'Erste' : 'Nächste'} Gruppe: <span class="code">${esc(n.klasse)}</span>` : 'Keine weitere Gruppe auf dieser Tatami'}</h2>
                ${n ? `<p>${esc(n.klasse_name)}</p>` : ''}
                ${stand}
                ${n && !laeuft ? '<p class="uebergang-hinweis">Das Turnier ist noch nicht gestartet – der Ausruf geht schon, Kämpfe erst nach dem Start durch die Wettkampfleitung.</p>' : ''}
                <div class="aktionen">
                    <a class="gross-knopf neutral" href="${esc(location.pathname.replace(/\/app$/, '/ausruf') + T.query)}">📣 Zum Ausruf</a>
                    ${abgeschlossen && laeuft
                        ? '<button class="gross-knopf start" data-aktion="uebergang-weiter" data-starten="1">▶ Kampf beginnen</button>'
                        : '<button class="gross-knopf senden" data-aktion="uebergang-weiter">Weiter</button>'}
                </div>
            </div>`;
        ausrufStandRendern();
    }

    function karteRendern() {
        if (T.zustand && T.zustand.abschluss && !T.korrektur) { abschlussRendern(T.zustand.abschluss); return; }
        if (T.uebergang && !T.korrektur) { uebergangRendern(); return; }
        const e = T.aktuell;
        if (!e) {
            const z = T.zustand || {};
            $('karte').innerHTML = z.gesperrt
                ? `<p class="leer">🔒 Diese Tatami ist von der Wettkampfleitung gesperrt – es werden keine neuen Gruppen zugeteilt.${z.sperr_grund ? `<br><span class="klein">${esc(z.sperr_grund)}</span>` : ''}</p>`
                : z.turnier_rest === 0
                    ? '<p class="leer">✅ Alle Kämpfe und Vorträge des Turniers sind beendet.<br><span class="klein">Auf dieser Tatami kommt nichts mehr – vielen Dank!</span></p>'
                    : `<p class="leer">Keine offenen Kämpfe oder Vorträge auf dieser Tatami.<br><span class="klein">${z.verteilung === 'dynamisch'
                        ? 'Die nächste Gruppe wird automatisch zugeteilt, sobald eine bereitsteht.' : 'Neue Kämpfe erscheinen automatisch.'}</span></p>`;
            return;
        }
        const istKata = e.disziplin.startsWith('kata');
        const kopf = `
            <div class="kopfzeile">
                <div class="titel">${esc(e.klasse)} · ${e.typ === 'auftritt' ? 'Vortrag ' + esc(e.nr) : 'Kampf ' + esc(e.nr)} ${rundeMarke(e)}
                    ${e.status === 'laeuft' ? (e.unterbrochen ? '<span class="marke unterbrochen">⏸ unterbrochen</span>' : '<span class="marke laeuft">läuft</span>') : ''}
                    ${T.korrektur ? '<span class="marke korrektur">Korrektur</span>' : ausrufMarke(e)}</div>
                <div><span class="marke">${esc({ flaggen: 'Flaggen', punkte: 'Punkte', kumite: 'Ippon/Wazaari', sanbon: 'Shobu Sanbon' }[e.system] || e.system)}</span>
                    ${e.kumite_form ? `<span class="marke">${esc({ kihon_ippon: 'Kihon-Ippon', jiyu_ippon: 'Jiyu-Ippon', jiyu: 'Jiyu-Kumite' }[e.kumite_form] || '')}</span>` : ''}</div>
            </div>
            <div class="klein">${esc(e.klasse_name)} · ${esc(e.phase)}</div>
            ${turnierLaeuft() ? '' : `<div class="phase-hinweis">${esc(PHASE_HINWEIS[T.zustand.phase] || '')}</div>`}
            ${fehltBestaetigung(e)}
            ${pauseHinweis(e)}`;

        let inhalt;
        if (e.typ === 'auftritt') {
            inhalt = `
                <div class="seiten" style="grid-template-columns:1fr">
                    <div class="seite shiro">
                        <div class="name">${esc(e.starter.name)}</div><div class="verein">${esc(e.starter.verein)}</div>
                        <div class="zeile"><label>Kata</label>${kataFeld('kata', e.kata_liste)}</div>
                        <div class="zeile"><label>Wertungen</label>${krFelder('werte', e.kampfrichter)}<span class="summe" id="summe-werte"></span></div>
                        <div class="erklaerung">Je Kampfrichter (KR) eine Wertung${e.kampfrichter >= 5 ? ' · höchste und niedrigste werden gestrichen' : ''}${e.punkte ? ` · erlaubt ${String(e.punkte.min).replace('.', ',')}–${String(e.punkte.max).replace('.', ',')}` : ''} · schnell ohne Komma: 77 = 7,7</div>
                    </div>
                </div>`;
        } else {
            const team = e.disziplin === 'kumite_team' && e.system === 'kumite';
            // Kumite (Ippon/Wazaari): kompakt, damit Eingabe und „Ergebnis senden“ ohne Scrollen passen
            const kompakt = !team && istKumite(e);
            inhalt = `${uhrBlock(e)}<div class="seiten${kompakt ? ' kompakt' : ''}">${['aka', 'shiro'].map((s) => `
                <div class="seite ${s}" id="seite-${s}">
                    <div class="kennung">${s === 'aka' ? 'AKA' : 'SHIRO'}</div>
                    <div class="name">${esc(e[s].name)}</div><div class="verein">${esc(e[s].verein)}</div>
                    ${istKata ? `<div class="zeile"><label>Kata</label>${kataFeld('kata_' + s, e.kata_liste)}</div>` : ''}
                    ${!team && e.system === 'flaggen' ? `<div class="zeile"><label>Flaggen</label>${zaehler('flaggen_' + s)}<span class="erklaerung">Kampfrichter, die ${s === 'aka' ? 'Rot (AKA)' : 'Weiß (SHIRO)'} zeigen</span></div>` : ''}
                    ${!team && istKumite(e) ? `
                        <div class="punkte-reihe">
                            <div class="zeile"><label title="voller Punkt">Ippon</label>${zaehler(s + '_ippon')}</div>
                            <div class="zeile"><label title="halber Punkt · 2 Wazaari = Ippon">Wazaari</label>${zaehler(s + '_wazaari')}</div>
                        </div>
                        ${strafenAuswahl(s)}
                        <div class="stand" id="stand-${s}"></div>` : ''}
                    ${e.system === 'punkte' ? `<div class="zeile">${krFelder(s + '_werte', e.kampfrichter)}<span class="summe" id="summe-${s}_werte"></span></div>` : ''}
                </div>`).join('')}</div>
                ${e.system === 'flaggen' && !team ? `<div class="unentschieden" id="flaggen-mitte"></div>` : ''}
                ${team ? teamTabelle() : ''}
                <div id="hantei"></div>`;
        }

        const KAMPFLOS = {
            kiken: ['Kiken', (s) => `${s} tritt nicht an oder gibt auf`],
            hansoku: ['Hansoku', (s) => `${s} wird in diesem Kampf disqualifiziert`],
            shikkaku: ['Shikkaku', (s) => `${s} wird vom ganzen Turnier ausgeschlossen`],
        };
        const kampflos = e.typ === 'auftritt'
            ? `<div class="kampflos-knoepfe"><button data-aktion="kampflos" data-entscheidung="kiken" class="${T.werte.entscheidung === 'kiken' ? 'aktiv' : ''}">
                Kiken<span class="erklaerung">nicht angetreten bzw. Vortrag abgebrochen – keine Wertung</span></button></div>`
            : `<div class="kampflos-knoepfe">${Object.entries(KAMPFLOS).map(([d, [titel, text]]) => ['aka', 'shiro'].map((s) => {
                const seite = s === 'aka' ? 'AKA' : 'SHIRO';
                const gewinner = s === 'aka' ? 'SHIRO' : 'AKA';
                return `<button data-aktion="kampflos" data-entscheidung="${d}" data-gegen="${s}" class="${T.werte.entscheidung === d && T.werte.gegen === s ? 'aktiv' : ''}">
                    ${titel} gegen ${seite}<span class="erklaerung">${text(seite)} → ${gewinner} gewinnt</span></button>`;
            }).join('')).join('')}</div>`;

        $('karte').innerHTML = `${kopf}${inhalt}
            <div class="ergebnis-banner" id="ergebnis" hidden></div>
            <div class="aktionen">
                ${e.typ === 'begegnung' && e.status === 'bereit' && !T.korrektur && turnierLaeuft() ? '<button class="gross-knopf start" data-aktion="starten">Kampf beginnt</button>' : ''}
                ${e.typ === 'begegnung' && e.status === 'laeuft' && !T.korrektur && turnierLaeuft()
                    ? (e.unterbrochen
                        ? '<button class="gross-knopf start" data-aktion="unterbrechen" data-an="0">▶ Kampf fortsetzen</button>'
                        : '<button class="gross-knopf neutral" data-aktion="unterbrechen" data-an="1">⏸ Kampf unterbrechen</button>')
                    : ''}
                ${T.dirty || T.korrektur ? '<button class="gross-knopf neutral" data-aktion="verwerfen">Verwerfen</button>' : ''}
                <button class="gross-knopf senden" data-aktion="senden" id="senden">Ergebnis senden</button>
            </div>
            <details class="kampflos"${T.werte.entscheidung ? ' open' : ''}><summary>Kampflos / Disqualifikation</summary>${kampflos}</details>
            ${T.korrektur ? '' : ausrufStartLeiste(e.klasse_id)}
            ${T.korrektur && e.typ === 'begegnung' ? `<div class="zuruecksetzen">
                <button class="knopf-korrektur" data-aktion="zuruecksetzen">Ergebnis löschen – Kampf neu austragen</button>
                <span class="erklaerung">Falls das Ergebnis fälschlich gesendet wurde: Der Kampf wird sofort auf dieser Tatami neu ausgetragen.</span></div>` : ''}
            ${begriffe()}`;
        vorschau();
        ausrufStandRendern();
    }

    /** Kurze Erklärung der Fachbegriffe für die Tischbesatzung. */
    function begriffe() {
        const liste = [
            ['AKA / SHIRO', 'Rote bzw. weiße Seite der Begegnung.'],
            ['Flaggen', 'Jede/r Kampfrichter/in zeigt Rot (AKA), Weiß (SHIRO) oder gekreuzt/unten (unentschieden). Es gewinnt die Seite mit mehr Flaggen.'],
            ['Hikiwake', 'Unentschieden. Jede Flagge, die weder AKA noch SHIRO zugeordnet ist, zählt als unentschieden.'],
            ['Hantei', 'Kampfrichterentscheid per Flagge, wenn nach Punkten Gleichstand herrscht.'],
            ['Kampfzeit abgelaufen', 'Ohne Ippon gewinnt die höhere Wertung (z. B. ein Wazaari), bei Gleichstand Saishiai oder Hantei.'],
            ['Saishiai', 'Verlängerung bei Gleichstand (Einzel): ein neuer Kampf über 2 Minuten, Strafen beginnen neu. Endet auch er unentschieden, entscheidet Hantei.'],
            ['Ippon', 'Voller Punkt – entscheidet den Kampf (Shobu Ippon).'],
            ['Wazaari', 'Halber Punkt; zwei Wazaari ergeben ein Ippon (Awazete Ippon).'],
            ['Shobu Sanbon', 'Finale über zwei volle Punkte: der Kampf endet, wenn eine Seite zwei Ippon (bzw. vier Wazaari) erreicht.'],
            ['Keikoku', 'Ermahnung – 1. Strafstufe.'],
            ['Hansoku Chui', 'Verwarnung – 2. Strafstufe. Strafen geben dem Gegner keine Punkte, sie zählen beim Hantei.'],
            ['Jogai / Mubobi', 'Verlassen der Kampffläche / Selbstgefährdung – eigene Strafreihe: Keikoku, Chui, Hansoku.'],
            ['Kiken', 'Nicht angetreten oder aufgegeben – der Kampf geht an die Gegenseite.'],
            ['Hansoku', 'Disqualifikation in diesem Kampf (3. Strafstufe einer Strafart) – der Gegner gewinnt, im Team zählt das als Ippon.'],
            ['Shikkaku', 'Ausschluss vom gesamten Turnier.'],
            ['Kihon-Ippon / Jiyu-Ippon / Jiyu-Kumite', 'Kampfformen: festgelegte Angriffe / freie Abwehr mit angesagten Angriffen / freier Kampf (Shobu Ippon).'],
            ['Kata', 'Formlauf; bei Punktwertung geben die Kampfrichter Punkte, höchste und niedrigste Wertung werden gestrichen.'],
        ];
        return `<details class="begriffe"><summary>Begriffe kurz erklärt</summary><dl>
            ${liste.map(([b, t]) => `<dt>${b}</dt><dd>${t}</dd>`).join('')}</dl></details>`;
    }

    function kataFeld(feld, liste) {
        const wert = T.werte[feld] || '';
        if (liste && liste.length) {
            const optionen = [...liste];
            if (wert && !optionen.includes(wert)) { optionen.push(wert); }
            return `<select class="gross" data-feld="${feld}"><option value="">– Kata wählen –</option>
                ${optionen.map((k) => `<option${k === wert ? ' selected' : ''}>${esc(k)}</option>`).join('')}</select>`;
        }
        return `<input class="gross" data-feld="${feld}" list="kata-liste" value="${esc(wert)}" placeholder="Kata (frei)">
            <datalist id="kata-liste">${KATAS.map((k) => `<option value="${k}">`).join('')}</datalist>`;
    }

    function krFelder(feld, anzahl) {
        const werte = T.werte[feld] || [];
        let html = '';
        for (let i = 0; i < anzahl; i++) {
            html += `<input class="kr" inputmode="decimal" data-feld="${feld}.${i}" value="${esc(werte[i] || '')}" placeholder="KR${i + 1}" aria-label="Kampfrichter ${i + 1}">`;
        }
        return html;
    }

    function zaehler(feld) {
        return `<span class="zaehler"><button data-aktion="minus" data-feld="${feld}">−</button><span class="wert">${T.werte[feld] || 0}</span><button data-aktion="plus" data-feld="${feld}">+</button></span>`;
    }

    /** Flaggen einer Entscheidung: Aka, Shiro, Unentschieden (Rest bis zur Zahl der Kampfrichter). */
    function flaggenStand(prefix, anzahl) {
        const a = T.werte[prefix + '_aka'] || 0;
        const s = T.werte[prefix + '_shiro'] || 0;
        return { a, s, u: Math.max(0, anzahl - a - s), erfasst: a + s > 0 || T.werte[prefix + '_erfasst'] === true };
    }

    /**
     * Strafen im Kumite (Wettkampfordnung B 8–10): je Art drei Stufen – Keikoku (Ermahnung),
     * Hansoku Chui (Verwarnung), Hansoku (Disqualifikation, der Gegner gewinnt). Strafen geben
     * keine Punkte, sie zählen nur beim Hantei.
     */
    const STRAFARTEN = [['regel', 'Regelverstoß', '', 'Regel'], ['jogai', 'Jogai', 'Jogai-', 'Jogai'], ['mubobi', 'Mubobi', 'Mubobi-', 'Mubobi']];
    const STRAFSTUFEN = ['', 'Keikoku', 'Hansoku Chui', 'Hansoku'];
    const KEINE_STRAFEN = { regel: 0, jogai: 0, mubobi: 0 };

    /** Kumite-Begegnung (Shobu Ippon oder Shobu Sanbon)? */
    function istKumite(e) {
        return !!e && (e.system === 'kumite' || e.system === 'sanbon');
    }

    /** Ziel in Wazaari: Shobu Ippon = 1 Ippon (2), Shobu Sanbon = 2 volle Punkte (4). */
    function kumiteZiel(e) {
        return e && e.system === 'sanbon' ? 4 : 2;
    }

    function strafen(seite) {
        return { ...KEINE_STRAFEN, ...(T.werte[seite + '_strafen'] || {}) };
    }

    /** „Keikoku, Jogai-Hansoku Chui“ */
    function strafenText(st) {
        return STRAFARTEN.filter(([art]) => st[art] > 0).map(([art, , vor]) => vor + STRAFSTUFEN[Math.min(3, st[art])]).join(', ');
    }

    /** Seite mit Hansoku (3. Stufe einer Strafart), sonst null. */
    function hansokuSeite() {
        return ['aka', 'shiro'].find((s) => Object.values(strafen(s)).some((n) => n >= 3)) || null;
    }

    /** Wertung einer Seite in Wazaari (Strafen zählen nicht als Punkte). */
    function kumitePunkte(seite) {
        return (T.werte[seite + '_ippon'] || 0) * 2 + (T.werte[seite + '_wazaari'] || 0);
    }

    /** Kumite (Ippon/Wazaari): Seite, die durch Ippon gewonnen hat (wie in der Vorschau), sonst null. */
    function kumiteEntschieden() {
        const e = T.aktuell;
        if (!e || e.typ !== 'begegnung' || !istKumite(e) || e.disziplin === 'kumite_team') { return null; }
        const h = hansokuSeite();
        if (h) { return h === 'aka' ? 'shiro' : 'aka'; }
        const a = kumitePunkte('aka');
        const s = kumitePunkte('shiro');
        return Math.max(a, s) >= kumiteZiel(e) && a !== s ? (a > s ? 'aka' : 'shiro') : null;
    }

    /**
     * Ein Ippon beendet den Kampf: Sobald die Eingabe den Kampf entscheidet, fragt ein großes
     * Fenster „Kampf beenden?“ – „Ja“ sendet das Ergebnis sofort, „Weiter eintragen“ schließt es.
     */
    async function ipponBestaetigen(vorher) {
        const sieger = kumiteEntschieden();
        if (!sieger || vorher || T.korrektur || !turnierLaeuft()) { return; }
        const e = T.aktuell;
        const kennung = sieger === 'aka' ? 'AKA' : 'SHIRO';
        const art = hansokuSeite() ? 'Hansoku' : (kumiteZiel(e) === 4 ? 'Shobu Sanbon' : 'Ippon');
        const ja = await Wettkampf.frage(`${e[sieger].name} · ${e[sieger].verein}
AKA ${kumiteText(kumitePunkte('aka'))} : SHIRO ${kumiteText(kumitePunkte('shiro'))}`, {
            titel: `${art} – ${kennung} gewinnt`, ja: 'Kampf beenden – Ergebnis senden', nein: 'Weiter eintragen', art: sieger, symbol: '🏁',
        });
        if (ja) { await senden(true); }
    }

    /** 3 → „1 Ippon 1 Wazaari“ */
    function kumiteText(punkte) {
        const teile = [];
        if (Math.floor(punkte / 2) > 0) { teile.push(Math.floor(punkte / 2) + ' Ippon'); }
        if (punkte % 2) { teile.push('1 Wazaari'); }
        return teile.length ? teile.join(' ') : '0';
    }

    /** Strafen einer Seite: je Art ein Knopf, der die nächste Stufe gibt, und „−“ zum Zurücknehmen. */
    function strafenAuswahl(seite) {
        const st = strafen(seite);
        return `<div class="verwarnung">
            <div class="zeile"><label>Strafen</label><span class="verwarnung-knoepfe">
                ${STRAFARTEN.map(([art, name, vor]) => {
                    // Knopf = der Ruf des Hauptkampfrichters für die nächste Stufe; gegebene Stufe als Plakette mit ✕
                    const gegeben = st[art] ? `<button class="gesetzt${st[art] >= 3 ? ' eskalation' : ''}" data-aktion="strafe-weg" data-seite="${seite}" data-art="${art}" title="${name}: ${vor}${STRAFSTUFEN[st[art]]} zurücknehmen">${vor}${STRAFSTUFEN[st[art]]} ✕</button>` : '';
                    return gegeben + (st[art] >= 3 ? ''
                        : `<button data-aktion="strafe" data-seite="${seite}" data-art="${art}" title="${name}: ${vor}${STRAFSTUFEN[st[art] + 1]} geben"${st[art] === 2 ? ' class="eskalation"' : ''}>${vor}${STRAFSTUFEN[st[art] + 1]}</button>`);
                }).join('')}
            </span></div>
        </div>`;
    }

    /**
     * Kampfuhr (Turniereinstellung der Wettkampfleitung): nur für Kumite, läuft auf dem Tablet und
     * übersteht das Neuladen (sessionStorage je Kampf). Kampfzeit laut Wettkampfordnung B 4,
     * Finale laut Einstellung, Saishiai immer 2 Minuten; 30 Sekunden vor Ende Ato shibaraku
     * (einfaches Signal), am Ende doppeltes Signal und „Kampfzeit abgelaufen“.
     */
    const UHR = { ton: null };

    function uhrEinstellung() {
        return (T.zustand && T.zustand.kampfuhr) || { aktiv: false };
    }

    function uhrPasst(e) {
        // nicht bei Korrekturen: der Kampf ist vorbei, die Uhr hätte nur den alten Stand
        return !!e && e.typ === 'begegnung' && !T.korrektur && uhrEinstellung().aktiv && (istKumite(e) || e.disziplin === 'kumite_team');
    }

    function uhrSchluessel(e) {
        return 'kampfuhr-' + e.id + (T.werte.saishiai ? '-saishiai' : '');
    }

    function uhrDauer(e) {
        const u = uhrEinstellung();
        if (T.werte.saishiai) { return 2 * 60000; }
        return (e.rundenart === 'finale' ? u.minuten_finale : u.minuten) * 60000;
    }

    function uhrLesen(e) {
        try {
            const x = JSON.parse(sessionStorage.getItem(uhrSchluessel(e)) || 'null');
            if (x && typeof x.rest === 'number') { return x; }
        } catch (er) { /* ohne Speicher: frische Uhr */ }
        return { rest: uhrDauer(e), seit: null, ato: false, ende: false };
    }

    function uhrSchreiben(e, x) {
        try { sessionStorage.setItem(uhrSchluessel(e), JSON.stringify(x)); } catch (er) { /* nur bis zum Neuladen */ }
    }

    function uhrRest(x) {
        return Math.max(0, x.rest - (x.seit ? Date.now() - x.seit : 0));
    }

    function uhrText(ms) {
        const s = Math.ceil(ms / 1000);
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    }

    /** Signalton: einfach (Ato shibaraku) bzw. doppelt (Ende) – Web Audio, ab dem ersten Tippen erlaubt. */
    function uhrTon(anzahl) {
        if (!uhrEinstellung().ton) { return; }
        try {
            const ctx = UHR.ton || (UHR.ton = new (window.AudioContext || window.webkitAudioContext)());
            for (let i = 0; i < anzahl; i++) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                const t = ctx.currentTime + i * 0.6;
                o.frequency.value = 880;
                o.connect(g);
                g.connect(ctx.destination);
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(0.6, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.45);
                o.start(t);
                o.stop(t + 0.5);
            }
        } catch (er) { /* ohne Ton */ }
    }

    function uhrKlasse(x) {
        const rest = uhrRest(x);
        return rest === 0 ? 'ende' : (rest <= 30000 ? 'ato' : (x.seit ? 'laeuft' : ''));
    }

    function uhrBlock(e) {
        if (!uhrPasst(e)) { return ''; }
        const x = uhrLesen(e);
        const rest = uhrRest(x);
        // abgelaufene Uhr (auch nach dem Neuladen): einmal „Kampfzeit abgelaufen“ übernehmen
        if (rest === 0 && istKumite(e) && e.disziplin !== 'kumite_team' && T.uhrGemeldet !== uhrSchluessel(e)) {
            T.uhrGemeldet = uhrSchluessel(e);
            T.werte.zeit_abgelaufen = true;
        }
        return `<div class="kampfuhr ${uhrKlasse(x)}" id="kampfuhr">
            <span class="uhr-zeit" id="uhr-zeit">${uhrText(rest)}</span>
            <span class="uhr-status" id="uhr-status">${rest === 0 ? 'Soremade – Kampfzeit abgelaufen' : (rest <= 30000 ? 'Ato shibaraku' : (T.werte.saishiai ? 'Saishiai' : (e.rundenart === 'finale' ? 'Finale' : 'Kampfzeit')))}</span>
            ${rest > 0 ? (x.seit
                ? '<button class="uhr-knopf stopp" data-aktion="uhr" data-wert="stopp">⏸ Yame</button>'
                : '<button class="uhr-knopf start" data-aktion="uhr" data-wert="start">▶ Hajime</button>') : ''}
            <button class="uhr-knopf neutral" data-aktion="uhr" data-wert="neu" title="Uhr zurücksetzen (z. B. nächster Einzelkampf im Team)">↺</button>
        </div>`;
    }

    /** Läuft jede Viertelsekunde: Anzeige aktualisieren, Signale geben, am Ende „Kampfzeit abgelaufen“ setzen. */
    function uhrTick() {
        const e = T.aktuell;
        const anzeige = $('uhr-zeit');
        if (!anzeige || !uhrPasst(e)) { return; }
        const x = uhrLesen(e);
        if (!x.seit) { return; }
        const rest = uhrRest(x);
        anzeige.textContent = uhrText(rest);
        if (rest <= 30000 && rest > 0 && !x.ato) {
            x.ato = true;
            uhrSchreiben(e, x);
            uhrTon(1);
            karteRendern();
        }
        if (rest === 0 && !x.ende) {
            Object.assign(x, { ende: true, rest: 0, seit: null });
            uhrSchreiben(e, x);
            uhrTon(2);
            if (istKumite(e) && e.disziplin !== 'kumite_team') {
                T.werte.zeit_abgelaufen = true;
                T.dirty = true;
            }
            karteRendern();
        }
    }
    setInterval(uhrTick, 250);

    /** Hantei-Block (Flaggenentscheid bei Gleichstand) mit Zählern je Seite. */
    function hanteiBlock(anzahl) {
        const f = flaggenStand('hantei', anzahl);
        return `<div class="hantei-block">
            <div class="hantei-titel">Hantei – Flaggen der Kampfrichter</div>
            <div class="hantei-zeilen">
                <div class="zeile"><label class="aka-text">Flaggen AKA</label>${zaehler('hantei_aka')}</div>
                <div class="zeile"><label>Flaggen SHIRO</label>${zaehler('hantei_shiro')}</div>
            </div>
            <div class="unentschieden">Unentschieden (Hikiwake): <strong>${f.u}</strong> von ${anzahl} Kampfrichtern</div>
        </div>`;
    }

    function teamTabelle() {
        // Einzelkämpfe laut Ausschreibung (3/5/7), danach der Stichkampf
        const n = (T.aktuell && T.aktuell.team_kaempfe) || 3;
        const k = T.werte.kaempfe || (T.werte.kaempfe = []);
        while (k.length < n + 1) { k.push({}); }
        k.length = n + 1;
        k.forEach((x, i) => { x.entscheidungskampf = i === n || undefined; });
        return `<table class="team-tabelle">${k.map((x, i) => `
            <tr><td><strong>${i < n ? 'Kampf ' + (i + 1) : 'Stichkampf'}</strong></td>
            <td><span class="flaggen">${['aka', 'shiro', ...(i < n ? ['hikiwake'] : [])].map((s) => `
                <button data-aktion="team" data-index="${i}" data-wert="${s}" class="${x.sieger === s ? 'aktiv' : ''}">${{ aka: 'AKA', shiro: 'SHIRO', hikiwake: 'Unentsch.' }[s]}</button>`).join('')}
                ${i < n ? `<button data-aktion="team-hansoku" data-index="${i}" class="${x.hansoku ? 'aktiv' : ''}" title="Sieg durch Hansoku (zählt in der Unterbewertung als Ippon)">Hansoku</button>` : ''}</span></td>
            <td class="klein">A: I <input class="kr" style="width:3rem" data-feld="kaempfe.${i}.aka_ippon" value="${esc(x.aka_ippon || '')}"> W <input class="kr" style="width:3rem" data-feld="kaempfe.${i}.aka_wazaari" value="${esc(x.aka_wazaari || '')}">
                · S: I <input class="kr" style="width:3rem" data-feld="kaempfe.${i}.shiro_ippon" value="${esc(x.shiro_ippon || '')}"> W <input class="kr" style="width:3rem" data-feld="kaempfe.${i}.shiro_wazaari" value="${esc(x.shiro_wazaari || '')}"></td></tr>`).join('')}</table>`;
    }

    /** Punktsumme mit Streichung (Vorschau; maßgeblich ist die Berechnung auf dem Server). */
    function summe(werte, anzahl) {
        const z = (werte || []).map(zahl).filter((x) => x !== null);
        if (z.length !== anzahl) { return null; }
        const sortiert = [...z].sort((a, b) => a - b);
        const gestrichen = anzahl >= 5 ? sortiert.slice(1, -1) : sortiert;
        return { gesamt: gestrichen.reduce((a, b) => a + b, 0), alle: z.reduce((a, b) => a + b, 0) };
    }

    /**
     * Wertungsfelder prüfen: Werte außerhalb des erlaubten Bereichs (oder keine Zahl) werden rot
     * markiert. @return {string} Hinweistext für das erste ungültige Feld, sonst ''.
     */
    function wertungenPruefen(e) {
        const min = e.punkte ? Number(e.punkte.min) : 0;
        const max = e.punkte ? Number(e.punkte.max) : 9.9;
        let hinweis = '';
        document.querySelectorAll('#karte input.kr').forEach((feld) => {
            const text = feld.value.trim();
            const n = zahl(text);
            const falsch = text !== '' && (!/^\d{1,2}([.,]\d{1,2})?$/.test(text) || n === null || n < min - 1e-9 || n > max + 1e-9);
            feld.classList.toggle('ungueltig', falsch);
            feld.setAttribute('aria-invalid', falsch ? 'true' : 'false');
            if (falsch && !hinweis) {
                hinweis = `Wertung „${text}“ ist ungültig – erlaubt sind ${String(min).replace('.', ',')} bis ${String(max).replace('.', ',')}. Bitte korrigieren.`;
            }
        });
        return hinweis;
    }

    /**
     * Ermittelt den voraussichtlichen Sieger, zeigt das Ergebnis groß an,
     * blendet Hantei bei Gleichstand ein und sperrt „Senden“ ohne Sieger.
     */
    function vorschau() {
        const e = T.aktuell;
        if (!e) { return; }
        let sieger = null;
        let gleichstand = false;
        let detail = '';
        let hinweis = '';
        let ippon = false;
        let zeitSieg = '';
        const w = T.werte;
        const kr = e.kampfrichter;
        // Punktwertung: ungültige Werte rot markieren, Summe und Senden erst mit gültigen Werten
        const ungueltig = w.entscheidung ? '' : wertungenPruefen(e);
        let wertungFehlt = false;
        if (w.entscheidung) {
            sieger = e.typ === 'auftritt' ? null : (w.gegen === 'aka' ? 'shiro' : 'aka');
            detail = { kiken: 'Kiken', hansoku: 'Hansoku', shikkaku: 'Shikkaku' }[w.entscheidung] || '';
        } else if (e.typ === 'auftritt') {
            const s = ungueltig ? null : summe(w.werte, kr);
            $('summe-werte').textContent = s ? s.gesamt.toFixed(1).replace('.', ',') : '';
            wertungFehlt = !s;
            hinweis = ungueltig;
        } else if (e.system === 'flaggen' && !w.kaempfe) {
            const f = flaggenStand('flaggen', kr);
            $('flaggen-mitte').innerHTML = `Unentschieden (Hikiwake): <strong>${f.u}</strong> von ${kr} Kampfrichtern
                <span class="klein">· jede nicht vergebene Flagge zählt als unentschieden</span>`;
            if (f.a !== f.s) {
                sieger = f.a > f.s ? 'aka' : 'shiro';
                detail = `${Math.max(f.a, f.s)} : ${Math.min(f.a, f.s)} Flaggen${f.u ? ` · ${f.u} unentschieden` : ''}`;
            } else if (f.erfasst) {
                hinweis = `Gleichstand ${f.a} : ${f.s}${f.u ? ` (${f.u} unentschieden)` : ''} – kein Sieger. Kampf verlängern bzw. erneut entscheiden lassen und dann neu eintragen.`;
            } else {
                hinweis = 'Flaggen eintragen: Wie viele Kampfrichter zeigen AKA (rot), wie viele SHIRO (weiß)?';
            }
        } else if (w.kaempfe) {
            const siege = { aka: 0, shiro: 0 };
            const n = e.team_kaempfe || 3;
            w.kaempfe.slice(0, n).forEach((k) => { if (siege[k.sieger] !== undefined) { siege[k.sieger]++; } });
            if (siege.aka !== siege.shiro) { sieger = siege.aka > siege.shiro ? 'aka' : 'shiro'; } else if (w.kaempfe[n] && w.kaempfe[n].sieger) { sieger = w.kaempfe[n].sieger; }
        } else if (istKumite(e)) {
            const a = kumitePunkte('aka');
            const s = kumitePunkte('shiro');
            const ziel = kumiteZiel(e);
            const zielText = ziel === 4 ? 'zwei volle Punkte' : 'Ippon';
            const stand = `AKA ${kumiteText(a)} : SHIRO ${kumiteText(s)}`;
            for (const seite of ['aka', 'shiro']) {
                const text = strafenText(strafen(seite));
                $('stand-' + seite).innerHTML = `Wertung: <strong>${kumiteText(seite === 'aka' ? a : s)}</strong>`
                    + (text ? ` <span class="klein">· Strafen: ${esc(text)} (zählen beim Hantei)</span>` : '');
            }
            const h = hansokuSeite();
            ippon = !!h || (Math.max(a, s) >= ziel && a !== s);
            if (h) {
                sieger = h === 'aka' ? 'shiro' : 'aka';
                detail = `Hansoku gegen ${h === 'aka' ? 'AKA' : 'SHIRO'} (${strafenText(strafen(h))})`;
            } else if (ippon) {
                sieger = a > s ? 'aka' : 'shiro';
                detail = `${ziel === 4 ? 'Shobu Sanbon – zwei volle Punkte' : 'Ippon'} erreicht · ${stand}`;
            } else if (!w.zeit_abgelaufen) {
                hinweis = a + s === 0
                    ? `Wertung eintragen (Ippon/Wazaari${ziel === 4 ? ' – Shobu Sanbon: zwei volle Punkte' : ''}). Endet die Kampfzeit vorher: „Kampfzeit abgelaufen“ tippen.`
                    : `Noch ${ziel === 4 ? 'keine zwei vollen Punkte' : 'kein Ippon'} erreicht (${stand}) – der Kampf geht weiter. Ist die Kampfzeit abgelaufen: „Kampfzeit abgelaufen“ tippen.`;
            } else if (a !== s) {
                sieger = a > s ? 'aka' : 'shiro';
                detail = `Kampfzeit abgelaufen · ${stand}`;
                zeitSieg = `Hinweis: Kein ${zielText} erreicht – Sieg nach Ablauf der Kampfzeit mit ${kumiteText(Math.max(a, s))}.`;
            } else {
                gleichstand = true;
            }
        } else if (e.system === 'punkte') {
            const a = ungueltig ? null : summe(w.aka_werte, e.kampfrichter);
            const s = ungueltig ? null : summe(w.shiro_werte, e.kampfrichter);
            $('summe-aka_werte').textContent = a ? a.gesamt.toFixed(1).replace('.', ',') : '';
            $('summe-shiro_werte').textContent = s ? s.gesamt.toFixed(1).replace('.', ',') : '';
            if (a && s) {
                const v = (a.gesamt - s.gesamt) || (a.alle - s.alle);
                if (Math.abs(v) > 1e-9) { sieger = v > 0 ? 'aka' : 'shiro'; } else { gleichstand = true; }
            }
        }
        const hantei = $('hantei');
        if (hantei) {
            const kumite = istKumite(e) && !w.kaempfe && !w.entscheidung;
            let zeit = '';
            if (kumite && w.saishiai && w.hauptkampf) {
                const h = w.hauptkampf;
                zeit += `<div class="saishiai-hinweis">Saishiai (Verlängerung) – neuer Kampf. Hauptkampf: AKA ${kumiteText((h.aka_ippon || 0) * 2 + (h.aka_wazaari || 0))} : SHIRO ${kumiteText((h.shiro_ippon || 0) * 2 + (h.shiro_wazaari || 0))}</div>`;
            }
            if (kumite && !ippon) {
                zeit += w.zeit_abgelaufen
                    ? `<div class="zeit-abgelaufen">⏱ <strong>Kampfzeit abgelaufen</strong> <span class="klein">höhere Wertung gewinnt, bei Gleichstand ${w.saishiai ? '' : 'Saishiai oder '}Hantei</span>
                        <button data-aktion="zeit" data-wert="0">Zeit läuft noch</button>
                        ${gleichstand && !w.saishiai ? '<button data-aktion="saishiai" title="neuer Kampf über 2 Minuten, Strafen beginnen neu">Saishiai (Verlängerung)</button>' : ''}</div>`
                    : '<button class="gross-knopf neutral hantei-start" data-aktion="zeit" data-wert="1">⏱ Kampfzeit abgelaufen</button>';
            }
            hantei.innerHTML = zeit + (gleichstand && !w.entscheidung ? hanteiBlock(kr) : '');
            if (gleichstand && !w.entscheidung) {
                const f = flaggenStand('hantei', kr);
                if (f.a !== f.s) {
                    sieger = f.a > f.s ? 'aka' : 'shiro';
                    detail = `Hantei ${Math.max(f.a, f.s)} : ${Math.min(f.a, f.s)}${f.u ? ` · ${f.u} unentschieden` : ''}`;
                } else {
                    hinweis = f.erfasst
                        ? `Hantei ${f.a} : ${f.s} – weiterhin Gleichstand. Bitte erneut entscheiden lassen (Hantei ist Pflichtentscheid).`
                        : ''; // der Hantei-Block erklärt sich selbst – kein zweiter Hinweis darunter
                }
            }
        }
        for (const s of ['aka', 'shiro']) {
            const el = $('seite-' + s);
            if (el) { el.classList.toggle('gewinnt', sieger === s); }
        }
        // Ergebnisbanner und Senden-Knopf
        const banner = $('ergebnis');
        if (banner && e.typ === 'begegnung') {
            if (sieger) {
                banner.className = 'ergebnis-banner ' + sieger;
                banner.innerHTML = `Sieger: <strong>${esc(e[sieger].name)}</strong> (${sieger === 'aka' ? 'AKA' : 'SHIRO'})${detail ? ` · ${esc(detail)}` : ''}`
                    + (zeitSieg ? `<div class="banner-hinweis">${esc(zeitSieg)}</div>` : '');
                banner.hidden = false;
            } else if (hinweis) {
                banner.className = 'ergebnis-banner offen';
                banner.textContent = hinweis;
                banner.hidden = false;
            } else {
                banner.hidden = true;
            }
        }
        if (banner && e.typ === 'auftritt') {
            // Vortrag: nur der Hinweis bei ungültiger Wertung
            banner.className = 'ergebnis-banner offen';
            banner.textContent = hinweis;
            banner.hidden = !hinweis;
        }
        if (ungueltig && e.typ === 'begegnung') {
            sieger = null; // ungültige Wertung: kein Sieger, kein Senden
            const banner = $('ergebnis');
            if (banner) { banner.className = 'ergebnis-banner offen'; banner.textContent = ungueltig; banner.hidden = false; }
        }
        const senden = $('senden');
        if (senden && e.typ === 'begegnung') { senden.disabled = !sieger || !turnierLaeuft(); }
        if (senden && e.typ === 'auftritt') { senden.disabled = !turnierLaeuft() || (!w.entscheidung && (Boolean(ungueltig) || wertungFehlt)); }
        T.sieger = sieger;
        T.zeitSieg = zeitSieg;
    }

    // --- Eingabe ------------------------------------------------------------------

    function setze(pfad, wert) {
        const teile = pfad.split('.');
        let ziel = T.werte;
        for (let i = 0; i < teile.length - 1; i++) {
            const t = teile[i];
            if (ziel[t] === undefined) { ziel[t] = /^\d+$/.test(teile[i + 1]) ? [] : {}; }
            ziel = ziel[t];
        }
        ziel[teile[teile.length - 1]] = wert;
        T.dirty = true;
    }

    /**
     * Schnelleingabe der Kata-Wertung ohne Komma: „77“ → 7,7, „80“ → 8,0, „10“/„100“ → 10,0 –
     * je nachdem, was im erlaubten Bereich liegt. null = keine Schnelleingabe (z. B. „7,7“ oder „8“).
     */
    function schnellWertung(text, punkte) {
        if (!/^\d{2,3}$/.test(text)) { return null; }
        const min = punkte && punkte.min !== undefined ? Number(punkte.min) : 5;
        const max = punkte && punkte.max !== undefined ? Number(punkte.max) : 10;
        const n = parseInt(text, 10);
        const wert = n / 10 >= min && n / 10 <= max ? n / 10 : (n >= min && n <= max ? n : null);
        return wert === null ? null : wert.toFixed(1).replace('.', ',');
    }

    /** Kata-Wertung kürzen: höchstens eine Nachkommastelle („7,77“ → „7,7“), höchstens zwei Ziffern ohne Komma („777“ → „77“, „100“ bleibt). */
    function wertungKuerzen(text) {
        const komma = /^(\d{1,2})[.,](\d)\d+$/.exec(text);
        if (komma) { return `${komma[1]},${komma[2]}`; }
        if (/^\d{3,}$/.test(text)) { return text.startsWith('100') ? '100' : text.slice(0, 2); }
        return text;
    }

    document.addEventListener('input', (ev) => {
        const feld = ev.target.dataset && ev.target.dataset.feld;
        if (!feld) { return; }
        if (ev.target.classList.contains('kr')) {
            const gekuerzt = wertungKuerzen(ev.target.value);
            if (gekuerzt !== ev.target.value) { ev.target.value = gekuerzt; }
        }
        const schnell = ev.target.classList.contains('kr') ? schnellWertung(ev.target.value, T.aktuell && T.aktuell.punkte) : null;
        if (schnell !== null) {
            ev.target.value = schnell;
            // vollständig → gleich ins nächste Kampfrichter-Feld
            const felder = [...document.querySelectorAll('input.kr')];
            const naechstes = felder[felder.indexOf(ev.target) + 1];
            if (naechstes) { naechstes.focus(); naechstes.select(); }
        }
        setze(feld, ev.target.value);
        vorschau();
    });
    document.addEventListener('change', (ev) => {
        const feld = ev.target.dataset && ev.target.dataset.feld;
        if (feld) { setze(feld, ev.target.value); vorschau(); }
    });

    document.addEventListener('click', async (ev) => {
        const knopf = ev.target.closest('[data-aktion]');
        if (!knopf) { return; }
        const a = knopf.dataset.aktion;
        const feld = knopf.dataset.feld;
        if (a === 'plus' || a === 'minus') {
            // Flaggen: Aka + Shiro höchstens so viele wie Kampfrichter (Rest = unentschieden)
            const flagge = /^(flaggen|hantei)_(aka|shiro)$/.exec(feld);
            if (flagge && a === 'plus') {
                const f = flaggenStand(flagge[1], T.aktuell.kampfrichter);
                if (f.a + f.s >= T.aktuell.kampfrichter) { return; }
            }
            if (flagge) { T.werte[flagge[1] + '_erfasst'] = true; }
            const vorher = kumiteEntschieden();
            setze(feld, Math.max(0, (T.werte[feld] || 0) + (a === 'plus' ? 1 : -1)));
            karteRendern();
            ipponBestaetigen(vorher);
        } else if (a === 'strafe' || a === 'strafe-weg') {
            const seite = knopf.dataset.seite;
            const art = knopf.dataset.art;
            const st = strafen(seite);
            const vorher = kumiteEntschieden();
            if (a === 'strafe' && st[art] === 2) {
                const name = seite === 'aka' ? 'AKA' : 'SHIRO';
                const vor = STRAFARTEN.find(([x]) => x === art)[2];
                if (!await Wettkampf.frage(`${name} wird für diesen Kampf disqualifiziert, der Gegner gewinnt.`, { titel: `${vor}Hansoku für ${name}`, ja: `${vor}Hansoku geben`, art: 'gefahr', symbol: '🟥' })) { return; }
            }
            st[art] = Math.max(0, Math.min(3, st[art] + (a === 'strafe' ? 1 : -1)));
            T.werte[seite + '_strafen'] = st;
            T.dirty = true;
            karteRendern();
            ipponBestaetigen(vorher); // Hansoku entscheidet den Kampf
        } else if (a === 'uhr') {
            const e = T.aktuell;
            const x = uhrLesen(e);
            if (knopf.dataset.wert === 'start') {
                uhrTon(0); // Audio beim Tippen freischalten
                x.seit = Date.now();
            } else if (knopf.dataset.wert === 'stopp') {
                Object.assign(x, { rest: uhrRest(x), seit: null });
            } else {
                if (x.seit && !await Wettkampf.frage('Die Uhr läuft gerade.', { titel: 'Kampfuhr zurücksetzen?', ja: 'Zurücksetzen' })) { return; }
                Object.assign(x, { rest: uhrDauer(e), seit: null, ato: false, ende: false });
            }
            uhrSchreiben(e, x);
            karteRendern();
        } else if (a === 'saishiai') {
            if (!await Wettkampf.frage('Die Verlängerung ist ein neuer Kampf über 2 Minuten. Wertungen und Strafen beginnen bei null; der Stand des Hauptkampfs bleibt gespeichert.', { titel: 'Saishiai – Verlängerung', ja: 'Saishiai beginnen', symbol: '⏱' })) { return; }
            const haupt = {};
            for (const s of ['aka', 'shiro']) {
                Object.assign(haupt, { [s + '_ippon']: T.werte[s + '_ippon'] || 0, [s + '_wazaari']: T.werte[s + '_wazaari'] || 0, [s + '_strafen']: strafen(s) });
                Object.assign(T.werte, { [s + '_ippon']: 0, [s + '_wazaari']: 0, [s + '_strafen']: { ...KEINE_STRAFEN } });
            }
            Object.assign(T.werte, { saishiai: true, hauptkampf: haupt, zeit_abgelaufen: false });
            T.dirty = true;
            karteRendern();
        } else if (a === 'team-hansoku') {
            const x = T.werte.kaempfe[parseInt(knopf.dataset.index, 10)];
            x.hansoku = !x.hansoku || undefined;
            T.dirty = true;
            karteRendern();
        } else if (a === 'zeit') {
            T.werte.zeit_abgelaufen = knopf.dataset.wert === '1';
            T.dirty = true;
            karteRendern();
        } else if (a === 'zuruecksetzen') {
            await zuruecksetzen();
        } else if (a === 'setzen') {
            setze(feld, parseInt(knopf.dataset.wert, 10));
            karteRendern();
        } else if (a === 'team') {
            T.werte.kaempfe[parseInt(knopf.dataset.index, 10)].sieger = knopf.dataset.wert;
            T.dirty = true;
            karteRendern();
        } else if (a === 'kampflos') {
            const gleich = T.werte.entscheidung === knopf.dataset.entscheidung && T.werte.gegen === knopf.dataset.gegen;
            T.werte.entscheidung = gleich ? undefined : knopf.dataset.entscheidung;
            T.werte.gegen = gleich ? undefined : knopf.dataset.gegen;
            T.dirty = true;
            karteRendern();
        } else if (a === 'verwerfen') {
            T.dirty = false;
            T.korrektur = null;
            T.aktuell = null;
            await laden();
        } else if (a === 'gruppe-abschliessen') {
            if (!await Wettkampf.frage('Danach wird die nächste Gruppe geladen.', { titel: `Gruppe ${T.zustand.abschluss.klasse} abschließen?`, ja: 'Gruppe abschließen', symbol: '✅' })) { return; }
            const klasse = T.zustand.abschluss.klasse;
            await ausfuehren(() => befehl('/klasse/' + knopf.dataset.klasse + '/abschliessen', {}), `Gruppe ${klasse} abgeschlossen ✓`);
            if (!T.zustand.abschluss || T.zustand.abschluss.klasse !== klasse) {
                T.uebergang = { klasse }; // nur nach erfolgreichem Abschluss
                karteRendern();
            }
        } else if (a === 'uebergang-weiter') {
            const am = (T.uebergang && T.uebergang.am) || (T.zustand.uebergang && T.zustand.uebergang.am);
            try { if (am) { sessionStorage.setItem(uebergangSchluessel(), am); } } catch (e) { /* ohne Speicher: nur bis zum Neuladen */ }
            T.uebergang = null;
            karteRendern();
            // Ausruf abgeschlossen: ersten Kampf der neuen Gruppe gleich beginnen (fehlt jemand, fragt die Karte nach)
            const e = T.aktuell;
            if (knopf.dataset.starten === '1' && e && e.typ === 'begegnung' && e.status === 'bereit' && !fehltSeiten(e).length) {
                await ausfuehren(() => befehl('/begegnung/' + e.id + '/starten', {}), 'Kampf läuft – auf der Statusseite sichtbar.');
            }
        } else if (a === 'starten') {
            if (T.aktuell.aufruf && !T.aktuell.aufruf.fertig
                && !await Wettkampf.frage('Der Ausruf für diesen Kampf ist noch nicht abgeschlossen (Ansicht „Ausruf“).', { titel: 'Trotzdem jetzt beginnen?', ja: 'Kampf beginnen', symbol: '📣' })) { return; }
            await ausfuehren(() => befehl('/begegnung/' + T.aktuell.id + '/starten', {}), 'Kampf läuft – auf der Statusseite sichtbar.');
        } else if (a === 'fehlt-bestaetigen') {
            const e = T.aktuell;
            const daten = e.typ === 'auftritt'
                ? { entscheidung: 'kiken', version: e.version }
                : { entscheidung: 'kiken', gegen: knopf.dataset.gegen, version: e.version };
            await ausfuehren(() => befehl('/' + e.typ + '/' + e.id, daten), 'Kiken eingetragen – weiter mit dem nächsten Kampf.', true);
        } else if (a === 'doch-da') {
            const e = T.aktuell;
            await ausfuehren(async () => {
                await befehl('/ausruf/' + e.typ + '/' + e.id, { seite: knopf.dataset.seite, status: 'da' });
                if (e.typ === 'begegnung' && e.status === 'bereit' && fehltSeiten(e).length === 1) {
                    await befehl('/begegnung/' + e.id + '/starten', {});
                }
            }, 'Starter ist da – der Kampf kann beginnen.');
        } else if (a === 'ausruf-selbst') {
            T.ausrufSelbst = knopf.dataset.an === '1';
            ausrufStandRendern();
        } else if (a === 'ausruf-starter') {
            await ausfuehren(() => befehl('/ausruf/klasse/' + knopf.dataset.klasse + '/starter/' + knopf.dataset.start, { status: knopf.dataset.status || null }), 'Ausruf aktualisiert.');
        } else if (a === 'ausruf-gruppe-abschliessen') {
            await ausfuehren(() => befehl('/ausruf/klasse/' + knopf.dataset.klasse + '/abschliessen', {}), 'Ausruf der Gruppe abgeschlossen ✓');
            T.ausrufSelbst = false;
        } else if (a === 'ausruf-freigeben') {
            await ausfuehren(() => befehl('/klasse/' + knopf.dataset.klasse + '/ausruf-freigeben', { an: true }),
                'Ausruf gestartet – der Ausrufer sieht jetzt die Starter der Gruppe.');
        } else if (a === 'unterbrechen') {
            const an = knopf.dataset.an === '1';
            await ausfuehren(() => befehl('/begegnung/' + T.aktuell.id + '/unterbrechen', { an }),
                an ? 'Kampf unterbrochen – auf der Statusseite sichtbar.' : 'Kampf läuft weiter.');
        } else if (a === 'senden') {
            await senden();
        } else if (a === 'korrigieren') {
            await korrekturStarten(knopf.dataset.typ === 'auftritt' ? 'auftritt' : 'begegnung', parseInt(knopf.dataset.id, 10));
        }
    });

    /** @param {boolean} bestaetigt schon im Ippon-Fenster bestätigt – keine zweite Rückfrage */
    async function senden(bestaetigt = false) {
        const e = T.aktuell;
        if (!e || T.sendet) { return; }
        const daten = Object.assign({}, T.werte, { version: e.version });
        if (T.korrektur) { daten.leitung_pin = T.korrektur.pin; }
        // Flaggen: nicht vergebene Flaggen zählen als unentschieden (Hikiwake)
        if (e.system === 'flaggen' && !daten.kaempfe) {
            const f = flaggenStand('flaggen', e.kampfrichter);
            Object.assign(daten, { flaggen_aka: f.a, flaggen_shiro: f.s, flaggen_unentschieden: f.u });
        }
        if ($('hantei') && $('hantei').querySelector('.hantei-block')) {
            const f = flaggenStand('hantei', e.kampfrichter);
            Object.assign(daten, { hantei_aka: f.a, hantei_shiro: f.s, hantei_unentschieden: f.u });
        } else {
            delete daten.hantei_aka;
            delete daten.hantei_shiro;
        }
        delete daten.flaggen_erfasst;
        delete daten.hantei_erfasst;
        if (!daten.zeit_abgelaufen) { delete daten.zeit_abgelaufen; }
        const name = (s) => (s === 'aka' ? 'AKA ' + e.aka.name : 'SHIRO ' + e.shiro.name);
        const [titel, text] = e.typ === 'auftritt'
            ? (T.werte.entscheidung ? ['Kiken eintragen?', e.starter.name] : ['Wertung senden?', e.starter.name])
            : (T.sieger ? [`Sieger: ${T.sieger === 'aka' ? 'AKA' : 'SHIRO'}`, `${name(T.sieger)}${T.zeitSieg ? `\n\n${T.zeitSieg}` : ''}`] : ['Ergebnis senden?', '']);
        if (!bestaetigt && !await Wettkampf.frage(text, { titel, ja: 'Ergebnis senden', art: e.typ === 'begegnung' && T.sieger ? T.sieger : '' })) { return; }
        const pfad = (e.typ === 'auftritt' ? '/auftritt/' : '/begegnung/') + e.id;
        // dieselbe Befehls-ID für erneutes Senden desselben Eintrags: kam das erste Senden doch an, zählt es nicht doppelt
        const schluessel = `${e.typ}:${e.id}:${e.version}`;
        if (!T.befehl || T.befehl.schluessel !== schluessel) { T.befehl = { schluessel, id: Wettkampf.befehlsId() }; }
        const id = T.befehl.id;
        await ausfuehren(() => befehl(pfad, daten, id), 'Ergebnis übertragen ✓', true);
    }

    /** Löscht ein (fälschlich) gesendetes Ergebnis; der Kampf wird wieder offen. */
    async function zuruecksetzen() {
        const e = T.aktuell;
        if (!e || !T.korrektur || T.sendet) { return; }
        if (!await Wettkampf.frage('Der Kampf wird danach sofort auf dieser Tatami neu ausgetragen.', { titel: `Ergebnis von ${e.klasse} · Kampf ${e.nr} löschen?`, ja: 'Ergebnis löschen', art: 'gefahr' })) { return; }
        await ausfuehren(
            () => befehl('/begegnung/' + e.id + '/zuruecksetzen', { leitung_pin: T.korrektur.pin }),
            'Ergebnis gelöscht – der Kampf wird jetzt neu ausgetragen.',
            true,
        );
    }

    async function korrekturStarten(typ, id) {
        let pin = '';
        if (T.pinPflicht) {
            pin = await Wettkampf.dialog('Korrekturen nur mit Berechtigung der Wettkampfleitung.', { titel: 'Leitungs-PIN eingeben', ja: 'Weiter', symbol: '🔒', eingabe: { typ: 'password', inputmode: 'numeric' } });
            if (!pin) { return; }
        }
        try {
            const antwort = await fetch(url('/eintrag/' + typ + '/' + id), { credentials: 'same-origin', cache: 'no-store' });
            const eintrag = await antwort.json();
            if (!antwort.ok) { throw new Error(eintrag.fehler || 'Fehler'); }
            T.aktuell = eintrag;
            T.werte = startwerte(eintrag);
            T.korrektur = { pin };
            T.dirty = true;
            karteRendern();
            listenRendern();
            window.scrollTo(0, 0);
        } catch (fehler) {
            meldung(fehler.message, false);
        }
    }

    async function ausfuehren(aktion, erfolgText, abschliessen = false) {
        T.sendet = true;
        const knopf = $('senden');
        if (knopf) { knopf.disabled = true; }
        try {
            await aktion();
            meldung(erfolgText, true);
            T.befehl = null;
            if (abschliessen) {
                T.dirty = false;
                T.korrektur = null;
                T.aktuell = null;
            }
            await laden();
        } catch (fehler) {
            meldung(fehler.unklar || navigator.onLine === false
                ? 'Keine Verbindung zum Server – unklar, ob es angekommen ist. Bitte erneut senden, sobald das WLAN wieder da ist: doppelt eingetragen wird nichts.'
                : fehler.message, false);
        } finally {
            T.sendet = false;
            if (knopf) { knopf.disabled = false; }
        }
    }

    let meldungTimer = null;
    $('meldung').addEventListener('click', () => { $('meldung').hidden = true; });
    function meldung(text, ok) {
        const el = $('meldung');
        el.textContent = text;
        el.className = 'meldung ' + (ok ? 'ok' : 'fehler');
        el.hidden = false;
        clearTimeout(meldungTimer);
        if (ok) { meldungTimer = setTimeout(() => { el.hidden = true; }, 4000); }
    }

    // --- Start ---------------------------------------------------------------------

    const beobachter = new MutationObserver(() => {
        $('verbindung').textContent = body.classList.contains('offline') ? 'offline' : 'online';
        $('verbindung').className = 'verbindung ' + (body.classList.contains('offline') ? 'weg' : 'ok');
    });
    beobachter.observe(body, { attributes: true, attributeFilter: ['class'] });

    laden().catch((f) => meldung('Laden fehlgeschlagen: ' + f.message, false));
    Wettkampf.beobachten(url('/revision'), laden, 4000);
})();
