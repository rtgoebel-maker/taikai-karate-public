/*
 * Allgemeine Verhalten per data-Attribut – statt onclick="…" usw. im HTML, damit die Content-Security-Policy
 * keine eingebetteten Skripte erlauben muss (script-src 'self', OWASP A05). Auf allen Seitenarten geladen.
 *
 *   data-auswaehlen              Feldinhalt beim Fokus markieren (Links zum Kopieren)
 *   data-absolut                 relativen Link in value bzw. Text zur vollständigen Adresse machen
 *   data-drucken                 Klick öffnet den Druckdialog
 *   data-neu-laden               Klick lädt die Seite neu
 *   data-auto-absenden           Auswahl ändern schickt das Formular ab (Filter)
 *   data-setzen="feld" data-wert Klick setzt vor dem Absenden ein Formularfeld
 *   .blatt.einseitig             Druckblatt: zu großen Inhalt auf genau eine Seite verkleinern
 */
(() => {
    document.addEventListener('focusin', (ev) => {
        if (ev.target.matches && ev.target.matches('[data-auswaehlen]')) { ev.target.select(); }
    });

    document.addEventListener('click', (ev) => {
        const ziel = ev.target.closest && ev.target.closest('[data-drucken], [data-neu-laden], [data-setzen]');
        if (!ziel) { return; }
        if (ziel.matches('[data-drucken]')) { window.print(); }
        if (ziel.matches('[data-neu-laden]')) { location.reload(); }
        if (ziel.matches('[data-setzen]') && ziel.form) { ziel.form.elements[ziel.dataset.setzen].value = ziel.dataset.wert; }
    });

    document.addEventListener('change', (ev) => {
        if (ev.target.matches && ev.target.matches('[data-auto-absenden]') && ev.target.form) { ev.target.form.submit(); }
    });

    const einrichten = () => {
        document.querySelectorAll('[data-absolut]').forEach((el) => {
            if ('value' in el && el.tagName !== 'BUTTON') {
                el.value = new URL(el.value, location.href).href;
            } else {
                el.textContent = new URL(el.textContent, location.href).href;
            }
        });

        // Einseitige Blätter (Baum, Pool-Liste, Aushang) haben feste A4/A3-Größe: zu großen Inhalt verkleinern,
        // damit er auf genau eine Seite passt. Der Inhaltsbereich entspricht dem Druckbereich (@page-Ränder).
        document.querySelectorAll('.blatt.einseitig').forEach((blatt) => {
            const inhalt = document.createElement('div');
            inhalt.className = 'blatt-inhalt';
            while (blatt.firstChild) { inhalt.appendChild(blatt.firstChild); }
            blatt.appendChild(inhalt);
            const s = getComputedStyle(blatt);
            const hoehe = blatt.clientHeight - parseFloat(s.paddingTop) - parseFloat(s.paddingBottom);
            const breite = blatt.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
            const faktor = Math.min(1, hoehe / inhalt.scrollHeight, breite / inhalt.scrollWidth);
            if (faktor < 1) { inhalt.style.zoom = Math.floor(faktor * 100) / 100; }
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', einrichten);
    } else {
        einrichten();
    }
})();

/* Eingeklappten Bereich öffnen: Link mit data-aufklappen="id" bzw. Seitenanker auf ein <details> (z. B. Druckcenter → Urkunden gestalten). */
(() => {
    const oeffnen = (id) => {
        const ziel = id && document.getElementById(id);
        if (ziel && ziel.tagName === 'DETAILS') { ziel.open = true; }
    };
    document.addEventListener('click', (ereignis) => {
        const link = ereignis.target.closest('[data-aufklappen]');
        if (link) { oeffnen(link.dataset.aufklappen); }
    });
    document.addEventListener('DOMContentLoaded', () => oeffnen(decodeURIComponent(location.hash.slice(1))));
})();

/* Knopf mit data-einblenden="id": verborgenes Element einblenden, Knopf verschwindet (z. B. weitere Turniere auf „Hallen-Laptop“). */
document.addEventListener('click', (ereignis) => {
    const knopf = ereignis.target.closest('[data-einblenden]');
    if (!knopf) { return; }
    const ziel = document.getElementById(knopf.dataset.einblenden);
    if (ziel) { ziel.hidden = false; knopf.remove(); }
});

/* Suchfeld mit data-tabellenfilter="id": Zeilen der Tabelle (tr[data-suche]) nach dem Suchtext ein-/ausblenden (z. B. lange Kontenliste). */
document.addEventListener('input', (ereignis) => {
    const feld = ereignis.target.closest && ereignis.target.closest('[data-tabellenfilter]');
    if (!feld) { return; }
    const tabelle = document.getElementById(feld.dataset.tabellenfilter);
    if (!tabelle) { return; }
    const woerter = feld.value.toLowerCase().split(/\s+/).filter(Boolean);
    tabelle.querySelectorAll('tr[data-suche]').forEach((zeile) => {
        const text = zeile.dataset.suche || '';
        zeile.hidden = !woerter.every((w) => text.includes(w));
    });
});
