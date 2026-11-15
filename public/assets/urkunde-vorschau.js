/* Druckcenter → Urkunden gestalten: Vorschau der Musterurkunde folgt Position, Größe und Texten sofort (ohne Speichern). */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const rahmen = document.querySelector('iframe[data-urkunde-vorschau]');
    if (!rahmen) { return; }
    const formular = rahmen.closest('form');
    let warten = null;
    const aktualisieren = () => {
        const adresse = new URL(rahmen.dataset.urkundeVorschau, location.href);
        adresse.searchParams.set('live', '1');
        formular.querySelectorAll('[data-urkunde-feld]').forEach((feld) => {
            if (feld.name) { adresse.searchParams.set(feld.name, feld.value); }
        });
        rahmen.src = adresse.pathname + adresse.search;
    };
    formular.addEventListener('input', (ereignis) => {
        if (!ereignis.target.matches('[data-urkunde-feld]')) { return; }
        clearTimeout(warten);
        warten = setTimeout(aktualisieren, 350);
    });
});
