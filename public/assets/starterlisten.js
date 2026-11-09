/* Aushang „Starterlisten & Wettkampfbäume“: Reiter, Filter, Namenssuche, Drucken, Hinweis auf neuen Listenstand.
   Daten aus dem JSON-Block #sl-daten: personen, revision, revisionUrl (null in der Offlinekopie). */
(() => {
    const datenBlock = document.getElementById('sl-daten');
    if (!datenBlock) { return; }
    const { personen, revision: bekannt, revisionUrl } = JSON.parse(datenBlock.textContent);
    const gruppen = [...document.querySelectorAll('.sl-gruppe')];
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Reiter
    document.querySelectorAll('[data-reiter]').forEach((knopf) => knopf.addEventListener('click', () => {
        document.querySelectorAll('[data-reiter]').forEach((k) => k.classList.toggle('aktiv', k === knopf));
        $('sl-gruppen').hidden = knopf.dataset.reiter !== 'gruppen';
        $('sl-namen').hidden = knopf.dataset.reiter !== 'namen';
        if (knopf.dataset.reiter === 'namen') { $('sl-namensuche').focus(); }
    }));

    // Filter: Suchtext, Gruppe, Tatami
    function filtern() {
        const text = $('sl-suche').value.trim().toLowerCase();
        const gruppe = $('sl-gruppe').value;
        const tatami = $('sl-tatami').value;
        let sichtbar = 0;
        let starts = 0;
        gruppen.forEach((g) => {
            const passt = (!text || g.dataset.text.includes(text))
                && (!gruppe || g.id === gruppe)
                && (!tatami || g.dataset.tatamis.includes(',' + tatami + ','));
            g.hidden = !passt;
            document.querySelector('.sl-chip[data-anker="' + g.id + '"]').classList.toggle('aus', !passt);
            if (passt) {
                sichtbar++;
                starts += parseInt(document.querySelector('.sl-chip[data-anker="' + g.id + '"] span').textContent, 10);
            }
        });
        $('sl-zusammenfassung').textContent = `${sichtbar} Gruppen · ${starts} Starts`;
        $('sl-keine').hidden = sichtbar > 0;
    }
    ['sl-suche', 'sl-gruppe', 'sl-tatami'].forEach((id) => $(id).addEventListener('input', filtern));

    // Index-Chips: Gruppe anzeigen (Filter lösen, falls sie ausgeblendet ist)
    document.querySelectorAll('.sl-chip').forEach((chip) => chip.addEventListener('click', () => {
        const ziel = $(chip.dataset.anker);
        if (ziel && ziel.hidden) { $('sl-suche').value = ''; $('sl-gruppe').value = ''; $('sl-tatami').value = ''; filtern(); }
    }));

    // Drucken: Auswahl (sichtbare Gruppen) bzw. eine einzelne Gruppe
    $('sl-auswahl-drucken').addEventListener('click', () => window.print());
    document.querySelectorAll('.sl-gruppe-drucken').forEach((knopf) => knopf.addEventListener('click', () => {
        const ziel = $(knopf.dataset.anker);
        document.body.classList.add('sl-einzeldruck');
        ziel.classList.add('sl-druckziel');
        window.print();
        document.body.classList.remove('sl-einzeldruck');
        ziel.classList.remove('sl-druckziel');
    }));

    // Namenssuche
    $('sl-namensuche').addEventListener('input', () => {
        const text = $('sl-namensuche').value.trim().toLowerCase();
        if (text.length < 2) { $('sl-treffer').innerHTML = '<tr><td colspan="4" class="leise">Bitte Suchbegriff eingeben.</td></tr>'; return; }
        const treffer = personen.filter((p) => (p.name + ' ' + p.verein).toLowerCase().includes(text)).slice(0, 200);
        $('sl-treffer').innerHTML = treffer.map((p) => `<tr><td><strong>${esc(p.name)}</strong></td><td>${esc(p.verein)}</td>
            <td><a href="#${esc(p.anker)}" data-zu="${esc(p.anker)}">${esc(p.code)}</a></td><td>${esc(p.tatami) || '–'}</td></tr>`).join('')
            || '<tr><td colspan="4" class="leise">Keine Treffer.</td></tr>';
    });
    $('sl-treffer').addEventListener('click', (ev) => {
        const link = ev.target.closest('[data-zu]');
        if (!link) { return; }
        document.querySelector('[data-reiter="gruppen"]').click();
        $('sl-suche').value = ''; $('sl-gruppe').value = ''; $('sl-tatami').value = ''; filtern();
    });

    // Neuer Listenstand: Hinweis statt automatischem Neuladen (Aushang springt nicht)
    if (revisionUrl) {
        setInterval(async () => {
            try {
                const antwort = await fetch(revisionUrl, { cache: 'no-store' });
                const { revision } = await antwort.json();
                if (revision !== bekannt) { $('sl-neu').hidden = false; }
                $('live-status').textContent = 'live';
            } catch (e) {
                $('live-status').textContent = 'keine Verbindung';
            }
        }, 15000);
    }
})();
