/* Ansicht „Ausruf“: Starter je Gruppe abhaken („da“/„fehlt“), dann den Ausruf der Gruppe abschließen. Benötigt app.js. */
'use strict';

(() => {
    const body = document.body;
    const api = body.dataset.api;
    const query = body.dataset.query || '';
    const url = (pfad) => api + pfad + query;
    /** Aktion mit eindeutiger Befehls-ID: bei WLAN-Aussetzern automatisch wiederholt, wirkt nur einmal. */
    const befehl = (pfad, daten) => Wettkampf.post(url(pfad), daten, { befehl: true });
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let zustand = null;
    let alleZeigen = false;

    async function laden() {
        const antwort = await fetch(url('/ausruf'), { cache: 'no-store', credentials: 'same-origin' });
        if (antwort.status === 401) { location.href = location.pathname.replace(/\/ausruf$/, '') + '?ansicht=ausruf'; return; }
        zustand = await antwort.json();
        // neuer Programmstand auf dem Server (Update): Seite neu laden
        if (zustand.programm && document.body.dataset.programm && zustand.programm !== document.body.dataset.programm) {
            location.reload();
            return;
        }
        darstellen();
        Wettkampf.nachrichten(zustand.nachrichten, (id) => url('/nachricht/' + id + '/bestaetigen'), document.body.dataset.leitung === '1');
    }

    /** Aufstellung als Zusatz zu AKA/SHIRO: Wo stellt sich der Starter auf (vom Kampfrichtertisch aus gesehen)? */
    const platz = (seite) => {
        const aka = (zustand && zustand.aka_seite) || 'rechts';
        const links = seite === 'aka' ? aka === 'links' : aka !== 'links';
        // geschütztes Leerzeichen: Pfeil und Wort bleiben auf schmalen Handys zusammen
        return links ? '◀ ' + Wettkampf.t('links') : Wettkampf.t('rechts') + ' ▶';
    };

    /** Seiten eines Eintrags: AKA immer oben, dann SHIRO – bzw. der Starter eines Vortrags. */
    function seiten(e) {
        return e.typ === 'auftritt'
            ? [['starter', Wettkampf.t('Starter'), e.starter]]
            : [['aka', 'AKA', e.aka], ['shiro', 'SHIRO', e.shiro]];
    }

    /** Marke AKA (rot) bzw. SHIRO (weiß) mit der Aufstellung links/rechts. */
    const seitenMarke = (s) => `<span class="farbmarke ${s}">${s === 'aka' ? 'AKA' : 'SHIRO'}</span><span class="platz-zusatz">${platz(s)}</span>`;

    /** Karte eines Kampfs/Vortrags; modus 'laufend' bzw. 'bereit' = nur Anzeige (ohne Knöpfe). */
    function karte(e, modus) {
        const istLaufend = modus === 'laufend';
        const nurAnzeige = istLaufend || modus === 'bereit';
        const a = e.aufruf || {};
        const fehlt = seiten(e).filter(([s]) => a[s] === 'fehlt').map(([, n]) => n);
        const vollstaendig = seiten(e).every(([s]) => a[s]);
        const zeilen = seiten(e).map(([s, kennung, p]) => `
            <div class="seite ${s}">
                <div class="person">${s === 'starter' ? `<span class="kennung">${esc(kennung)}</span>` : `<span class="kennung-zeile">${seitenMarke(s)}</span>`}
                    <span class="name">${esc(p.name)}</span><span class="verein">${esc(p.verein)}</span></div>
                ${nurAnzeige || a.fertig ? `<span class="stand ${a[s] || ''}">${esc(a[s] === 'da' ? Wettkampf.t('✓ da') : a[s] === 'fehlt' ? Wettkampf.t('✗ fehlt') : '–')}</span>` : `
                <div class="knoepfe">
                    <button class="da${a[s] === 'da' ? ' aktiv' : ''}" data-aktion="markieren" data-seite="${s}" data-status="${a[s] === 'da' ? '' : 'da'}">${esc(Wettkampf.t('✓ da'))}</button>
                    <button class="fehlt${a[s] === 'fehlt' ? ' aktiv' : ''}" data-aktion="markieren" data-seite="${s}" data-status="${a[s] === 'fehlt' ? '' : 'fehlt'}">${esc(Wettkampf.t('✗ fehlt'))}</button>
                </div>`}
            </div>`).join('');
        let fuss = '';
        if (istLaufend) {
            fuss = e.unterbrochen
                ? `<div class="hinweis laeuft">⏸ ${esc(Wettkampf.t('Kampf unterbrochen'))}</div>`
                : `<div class="hinweis laeuft">${esc(Wettkampf.t('läuft gerade auf der Tatami'))}</div>`;
        } else if (modus === 'bereit') {
            fuss = `<div class="hinweis bereit">${esc(zustand && zustand.laufend ? Wettkampf.t('kommt direkt danach – bitte bereit machen') : Wettkampf.t('als Nächstes auf der Tatami'))}${e.klasse ? ` · ${esc(e.klasse)}` : ''}</div>`;
        } else if (a.fertig) {
            fuss = `<div class="fuss"><span class="bereit">✓ ${esc(fehlt.length ? Wettkampf.t('aufgerufen – {wer} fehlt', { wer: fehlt.join(', ') }) : Wettkampf.t('aufgerufen – bereit'))}</span>
                <button class="leise" data-aktion="oeffnen">${esc(Wettkampf.t('wieder öffnen'))}</button></div>`;
        } else {
            fuss = `<button class="abschliessen" data-aktion="abschliessen"${vollstaendig ? '' : ' disabled'}>${esc(Wettkampf.t('Ausruf abschließen'))}</button>`;
        }
        return `<article class="kampf${a.fertig && !nurAnzeige ? ' fertig' : ''}${istLaufend ? ' laufend' : ''}${modus === 'bereit' ? ' bereit-machen' : ''}" data-typ="${e.typ}" data-id="${e.id}">
            <div class="kampf-kopf"><strong>${esc(e.typ === 'auftritt' ? Wettkampf.t('Vortrag {nr}', { nr: e.nr }) : Wettkampf.t('Kampf {nr}', { nr: e.nr }))}</strong> <span>${esc(Wettkampf.runde(e.runde))}</span></div>
            ${zeilen}${fuss}</article>`;
    }

    function darstellen() {
        const z = zustand;
        if (z.phase && z.phase !== 'laeuft') {
            $('inhalt').innerHTML = `<p class="leer">${esc(z.phase === 'abgeschlossen' ? Wettkampf.t('Das Turnier ist abgeschlossen.') : Wettkampf.t('Das Turnier ist noch nicht gestartet. Der Ausruf beginnt, sobald die Wettkampfleitung das Turnier startet.'))}</p>`;
            return;
        }
        const offen = z.naechste.filter((e) => !(e.aufruf && e.aufruf.fertig));
        // „laufend“ ist bei Kata auch der nächste Vortrag – erst ein begonnener Kampf/Vortrag zählt als laufend
        const laeuftSchon = (klasseId) => !!z.laufend && z.laufend.klasse_id === klasseId && (z.laufend.status === 'laeuft' || !!z.laufend.gruppe_begonnen);
        const echtLaufend = z.laufend && laeuftSchon(z.laufend.klasse_id) ? z.laufend : null;
        // Gruppe am Tisch abgeschlossen → neue Gruppe aufrufen (bis der erste Kampf der neuen Gruppe ausgerufen ist)
        const naechste = z.laufend || z.naechste[0];
        let hinweis = '';
        if (z.abschluss) {
            hinweis = `<div class="gruppen-hinweis">${Wettkampf.t('Gruppe <strong>{klasse}</strong> ist fertig – die Tischbesatzung schließt sie gerade ab. Danach folgt <strong>{naechste}</strong>.', { klasse: esc(z.abschluss.klasse), naechste: esc(naechste ? naechste.klasse : '–') })}</div>`;
        } else if (z.letzter_abschluss && naechste && !echtLaufend && !naechste.gruppe_aufgerufen && naechste.klasse !== z.letzter_abschluss.klasse
            // bis nach dem Abschluss ein Kampf der neuen Gruppe ausgerufen wurde, höchstens 30 Minuten
            && Date.now() - new Date(z.letzter_abschluss.am.replace(' ', 'T')).getTime() < 30 * 60 * 1000
            && !z.naechste.some((e) => e.klasse === naechste.klasse && e.aufruf && e.aufruf.am && e.aufruf.am > z.letzter_abschluss.am)) {
            hinweis = `<div class="gruppen-hinweis neu">📣 ${Wettkampf.t('Neue Gruppe <strong>{klasse}</strong> – {name}: bitte jetzt die Starter aufrufen.', { klasse: esc(naechste.klasse), name: esc(naechste.klasse_name) })}</div>`;
        }
        // Gruppen (Klassen) in Reihenfolge des Plans: offene zum Abhaken, abgeschlossene als Zusammenfassung
        const gruppen = [];
        // ein noch nicht begonnener „laufender“ Kata-Vortrag gehört in die Abhakliste seiner Gruppe
        for (const e of (echtLaufend ? z.naechste : [z.laufend, ...z.naechste]).filter(Boolean)) {
            let g = gruppen.find((x) => x.klasse_id === e.klasse_id);
            if (!g) { g = { klasse: e.klasse, klasse_id: e.klasse_id, name: e.klasse_name, aufgerufen: e.gruppe_aufgerufen, offen: [], fertig: [] }; gruppen.push(g); }
            (e.aufruf && e.aufruf.fertig ? g.fertig : g.offen).push(e);
        }
        // Namen nur für die laufende Gruppe, vom Tisch gestartete Gruppen und solche, an denen schon gearbeitet wird –
        // sonst würden Starter aufgerufen, die noch lange nicht dran sind
        const begonnen = (e) => e.ausruf_freigegeben || e.gruppe_aufgerufen
            || (e.aufruf && (e.aufruf.fertig || e.aufruf.aka || e.aufruf.shiro || e.aufruf.starter));
        const sichtbarGruppe = (klasseId) => laeuftSchon(klasseId)
            || [z.laufend, ...z.naechste].some((e) => e && e.klasse_id === klasseId && begonnen(e));
        const wartend = gruppen.filter((g) => !sichtbarGruppe(g.klasse_id));
        const offeneGruppen = gruppen.filter((g) => g.offen.length && sichtbarGruppe(g.klasse_id));
        const fertigeGruppen = gruppen.filter((g) => !g.offen.length && g.fertig.length && sichtbarGruppe(g.klasse_id));
        const sichtbar = alleZeigen ? offeneGruppen : offeneGruppen.slice(0, 3);
        const bereit = z.naechste[0];
        const gestartet = gruppen.find((g) => !laeuftSchon(g.klasse_id)
            && [...g.offen, ...g.fertig].some((e) => e.ausruf_freigegeben)
            && ![...g.offen, ...g.fertig].some((e) => e.aufruf && (e.aufruf.fertig || e.aufruf.aka || e.aufruf.shiro || e.aufruf.starter)));
        if (hinweis.includes('gruppen-hinweis neu') && naechste && !sichtbarGruppe(naechste.klasse_id)) {
            hinweis = ''; // Namen der neuen Gruppe erst nach dem Start durch die Tischbesatzung
        }
        if (!hinweis && gestartet) {
            hinweis = `<div class="gruppen-hinweis neu">📣 ${Wettkampf.t('Die Tischbesatzung hat den Ausruf für Gruppe <strong>{klasse}</strong> gestartet – bitte jetzt die Starter aufrufen.', { klasse: esc(gestartet.klasse) })}</div>`;
        }
        // Solange der Ausruf der aktuellen Gruppe läuft, steht die Abhakliste oben; „Jetzt auf der Tatami“ und
        // „Bereit machen“ erst, wenn der Ausruf der Gruppe abgeschlossen ist (auch wenn jemand fehlt)
        const aktuell = z.laufend || bereit;
        const ausrufLaeuft = !!aktuell && offeneGruppen.some((g) => g.klasse_id === aktuell.klasse_id);
        const kampfKarten = ausrufLaeuft
            ? `<p class="karten-folgen">${Wettkampf.t('„Jetzt auf der Tatami“ und „Bereit machen“ erscheinen, sobald der Ausruf der Gruppe <strong>{klasse}</strong> abgeschlossen ist.', { klasse: esc(aktuell.klasse) })}</p>`
            : `${z.laufend && sichtbarGruppe(z.laufend.klasse_id) ? `<h2>${esc(Wettkampf.t('Jetzt auf der Tatami'))}</h2>${karte(z.laufend, 'laufend')}` : ''}
               ${bereit && sichtbarGruppe(bereit.klasse_id) ? `<h2>${esc(z.laufend ? Wettkampf.t('Bereit machen') : Wettkampf.t('Als Nächstes'))}</h2>${karte(bereit, 'bereit')}` : ''}`;
        $('inhalt').innerHTML = `${hinweis}
            ${kampfKarten}
            <h2>${esc(Wettkampf.t('Aufrufen'))} <span class="zahl">${offeneGruppen.length}</span></h2>
            ${sichtbar.length ? sichtbar.map((g) => gruppeHtml(g, z)).join('') : `<p class="leer">${esc(wartend.length ? Wettkampf.t('Gerade nichts aufzurufen.') : Wettkampf.t('Nichts mehr aufzurufen.'))}</p>`}
            ${wartend.length ? `<div class="gruppe-wartet">⏳ ${Wettkampf.t('Nächste Gruppe <strong>{klasse}</strong> {name} – die Namen erscheinen, sobald die Tischbesatzung den Ausruf startet.', { klasse: esc(wartend[0].klasse), name: esc(wartend[0].name) })}${wartend.length > 1 ? ` <span class="leise">${esc(Wettkampf.t('(danach {n} weitere)', { n: wartend.length - 1 }))}</span>` : ''}</div>` : ''}
            ${offeneGruppen.length > 3 ? `<button class="mehr" data-aktion="alle">${esc(alleZeigen ? Wettkampf.t('weniger anzeigen') : Wettkampf.t('alle {n} Gruppen anzeigen', { n: offeneGruppen.length }))}</button>` : ''}
            ${fertigeGruppen.length ? `<h2>${esc(Wettkampf.t('Aufgerufen – bereit'))} <span class="zahl">${fertigeGruppen.length}</span></h2>
                ${fertigeGruppen.map((g) => {
                    const starter = starterDerGruppe(g.fertig);
                    const fehlt = starter.filter((x) => x.stand === 'fehlt');
                    return `<div class="gruppe-fertig" data-klasse="${g.klasse_id}"><span class="code">${esc(g.klasse)}</span>
                        <span>✓ ${esc(Wettkampf.t('{n} da', { n: starter.length - fehlt.length }))}${fehlt.length ? ` · <span class="fehlt-text">${esc(Wettkampf.t('fehlt:'))} ${fehlt.map((x) => esc(x.name)).join(', ')}</span>` : ''}</span>
                        <button class="leise" data-aktion="gruppe-oeffnen">${esc(Wettkampf.t('wieder öffnen'))}</button></div>`;
                }).join('')}` : ''}`;
    }

    /** Starter einer Gruppe – jeder genau einmal, mit seinem ersten offenen Kampf/Vortrag und dem Stand. */
    function starterDerGruppe(eintraege) {
        const liste = new Map();
        for (const e of eintraege) {
            const seiten = e.typ === 'auftritt' ? [['starter', e.starter]] : [['aka', e.aka], ['shiro', e.shiro]];
            for (const [s, p] of seiten) {
                const id = (e.starts || {})[s];
                if (!id || liste.has(id)) { continue; }
                liste.set(id, {
                    id, name: p.name, verein: p.verein, stand: (e.aufruf || {})[s] || null,
                    wo: e.typ === 'auftritt' ? esc(Wettkampf.t('Vortrag {nr}', { nr: e.nr })) : `${esc(Wettkampf.t('Kampf {nr}', { nr: e.nr }))} · ${seitenMarke(s)}`,
                });
            }
        }
        return [...liste.values()];
    }

    /** Abhakliste einer Gruppe: jeden Starter „da“/„fehlt“, dann den Ausruf der Gruppe abschließen. */
    function gruppeHtml(g, z) {
        const starter = starterDerGruppe(g.offen);
        const abgehakt = starter.filter((x) => x.stand).length;
        const aufruf = g.aufgerufen
            ? `<div class="gruppen-aufruf an">📣 ${esc(Wettkampf.t('Gruppe wird aufgerufen – Starter stehen auf der Tatami-Übersicht, bis sie „da“ sind'))} <button class="leise" data-aktion="gruppe" data-an="0">${esc(Wettkampf.t('beenden'))}</button></div>`
            : (z.konflikte || {})[g.klasse_id]
                ? `<div class="gruppen-aufruf gesperrt">⏳ ${esc(Wettkampf.t('Gruppe {klasse} kann noch nicht aufgerufen werden – noch im Wettkampf auf einer anderen Tatami:', { klasse: g.klasse }))}
                    <ul>${z.konflikte[g.klasse_id].map((c) => `<li><strong>${esc(c.name)}</strong> – ${esc(c.klasse)}, ${esc(c.tatami)}</li>`).join('')}</ul></div>`
                : `<button class="gruppen-aufruf" data-aktion="gruppe" data-an="1">📣 ${esc(Wettkampf.t('Gruppe {klasse} aufrufen', { klasse: g.klasse }))}</button>`;
        return `<section class="gruppe" data-klasse="${g.klasse_id}">
            <h3><span class="code">${esc(g.klasse)}</span> ${esc(g.name)}</h3>
            ${aufruf}
            <ul class="abhakliste">${starter.map((x) => `
                <li class="starter-zeile${x.stand ? ' ' + x.stand : ''}">
                    <div class="person"><span class="name">${esc(x.name)}</span><span class="verein">${esc(x.verein)}</span><span class="wo">${x.wo}</span></div>
                    <div class="knoepfe">
                        <button class="da${x.stand === 'da' ? ' aktiv' : ''}" data-aktion="starter" data-start="${x.id}" data-status="${x.stand === 'da' ? '' : 'da'}">${esc(Wettkampf.t('✓ da'))}</button>
                        <button class="fehlt${x.stand === 'fehlt' ? ' aktiv' : ''}" data-aktion="starter" data-start="${x.id}" data-status="${x.stand === 'fehlt' ? '' : 'fehlt'}">${esc(Wettkampf.t('✗ fehlt'))}</button>
                    </div>
                </li>`).join('')}</ul>
            <button class="abschliessen" data-aktion="gruppe-abschliessen"${abgehakt === starter.length ? '' : ' disabled'}>${esc(Wettkampf.t('Ausruf Gruppe {klasse} abschließen', { klasse: g.klasse }))}</button>
            <div class="fortschritt">${esc(Wettkampf.t('{n} von {gesamt} abgehakt', { n: abgehakt, gesamt: starter.length }))}</div>
        </section>`;
    }

    $('meldung').addEventListener('click', () => { $('meldung').hidden = true; });

    async function senden(pfad, daten) {
        try {
            await befehl(pfad, daten || {});
            await laden();
        } catch (fehler) {
            const m = $('meldung');
            m.textContent = fehler.unklar || navigator.onLine === false ? Wettkampf.t('Keine Verbindung – bitte erneut versuchen (doppelt zählt nichts).') : fehler.message;
            m.hidden = false;
            setTimeout(() => { m.hidden = true; }, 5000);
        }
    }

    document.addEventListener('click', (ev) => {
        const knopf = ev.target.closest('[data-aktion]');
        if (!knopf) { return; }
        const kampf = knopf.closest('[data-id]');
        const pfad = kampf ? `/ausruf/${kampf.dataset.typ}/${kampf.dataset.id}` : '';
        const gruppe = knopf.closest('[data-klasse]');
        const gpfad = gruppe ? `/ausruf/klasse/${gruppe.dataset.klasse}` : '';
        switch (knopf.dataset.aktion) {
            case 'starter': senden(`${gpfad}/starter/${knopf.dataset.start}`, { status: knopf.dataset.status }); break;
            case 'gruppe-abschliessen': senden(`${gpfad}/abschliessen`); break;
            case 'gruppe-oeffnen': senden(`${gpfad}/oeffnen`); break;
            case 'gruppe': senden(gpfad, { an: knopf.dataset.an === '1' }); break;
            case 'oeffnen': senden(pfad + '/oeffnen'); break;
            case 'alle': alleZeigen = !alleZeigen; darstellen(); break;
            default: break;
        }
    });

    new MutationObserver(() => {
        $('verbindung').textContent = body.classList.contains('offline') ? Wettkampf.t('offline') : Wettkampf.t('online');
        $('verbindung').className = 'verbindung ' + (body.classList.contains('offline') ? 'weg' : 'ok');
    }).observe(body, { attributes: true, attributeFilter: ['class'] });

    laden().catch((f) => { $('inhalt').innerHTML = `<p class="leer">${esc(Wettkampf.t('Laden fehlgeschlagen: {grund}', { grund: f.message }))}</p>`; });
    Wettkampf.beobachten(url('/revision'), laden, 4000);
})();
