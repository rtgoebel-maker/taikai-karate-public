/* Stream-Einblendung: fragt alle 2 Sekunden den Stand der Tatami ab und zeigt den laufenden Kampf
   bzw. Vortrag oder – kurz nach dem Ende – das Ergebnis. Texte nur über textContent (kein HTML). */
((Wettkampf) => {
    const body = document.body;
    const quelle = body.dataset.quelle;
    const mitErgebnis = body.dataset.ergebnis === '1';
    const $ = (id) => document.getElementById(id);
    const band = $('band');
    const setze = (id, text) => { $(id).textContent = text || ''; };
    const zeile = (teile) => teile.filter(Boolean).join(' · ');
    /** Disziplin nur, wenn sie nicht schon im Klassennamen steht (z. B. „Jiyu-Kumite Jungen 12–14“). */
    const klasse = (x) => [x.klasse_name || x.klasse, x.disziplin && !(x.klasse_name || '').includes(x.disziplin) ? x.disziplin : ''];

    function zeigen(d) {
        const jetzt = d && d.jetzt;
        const ergebnis = mitErgebnis && d && d.ergebnis;
        band.className = 'band';
        if (jetzt) {
            const nummer = jetzt.typ === 'auftritt' ? Wettkampf.t('Vortrag {nr}', { nr: jetzt.nr }) : (jetzt.nr ? Wettkampf.t('Kampf {nr}', { nr: jetzt.nr }) : '');
            setze('info', zeile([...klasse(jetzt), jetzt.runde, nummer]));
            if (jetzt.typ === 'auftritt') {
                band.classList.add('zeigt-einzel');
                setze('starter-name', jetzt.starter && jetzt.starter.name);
                setze('starter-verein', jetzt.starter && jetzt.starter.verein);
            } else {
                band.classList.add('zeigt-paar');
                setze('aka-name', jetzt.aka && jetzt.aka.name);
                setze('aka-verein', jetzt.aka && jetzt.aka.verein);
                setze('shiro-name', jetzt.shiro && jetzt.shiro.name);
                setze('shiro-verein', jetzt.shiro && jetzt.shiro.verein);
            }
            band.classList.toggle('unterbrochen', !!jetzt.unterbrochen);
            band.hidden = false;
        } else if (ergebnis) {
            const person = ergebnis.typ === 'auftritt' ? ergebnis.starter : ergebnis.sieger;
            setze('info', zeile(klasse(ergebnis)));
            band.classList.add('zeigt-sieger');
            $('sieger').className = 'sieger' + (ergebnis.sieger_seite === 'aka' || ergebnis.sieger_seite === 'shiro' ? ' ' + ergebnis.sieger_seite : '');
            setze('sieger-titel', ergebnis.typ === 'auftritt' ? Wettkampf.t('Wertung') : Wettkampf.t('Sieger {seite}', { seite: ergebnis.sieger_seite === 'aka' ? 'AKA' : 'SHIRO' }));
            setze('sieger-name', person && person.name);
            setze('sieger-verein', person && person.verein);
            setze('sieger-text', ergebnis.text);
            band.hidden = false;
        } else {
            band.hidden = true;
        }
        if (d && d.tatami) { setze('tatami', d.tatami); }
    }

    async function abfragen() {
        try {
            const antwort = await fetch(quelle, { cache: 'no-store', credentials: 'same-origin' });
            if (antwort.ok) { zeigen(await antwort.json()); }
        } catch (e) { /* kurz kein Netz: letzten Stand stehen lassen */ }
        setTimeout(abfragen, 2000);
    }
    abfragen();
})(typeof Wettkampf !== 'undefined' ? Wettkampf : {
    // ohne app.js (z. B. Anmeldeformular, Einblendung, Offlinekopie): Katalog aus sprache/texte.js, sonst Deutsch
    t: (de, werte = {}) => ((window.WettkampfTexte && window.WettkampfTexte[de]) || de).replace(/\{(\w+)\}/g, (alles, name) => (name in werte ? String(werte[name]) : alles)),
});
