/* Systemeinstellungen: Vorschau von Hauptfarbe und heller Fläche (12 % Farbe auf Weiß) sofort anwenden. */
document.addEventListener('DOMContentLoaded', () => {
    const eigene = document.querySelector('input[name="farbe_eigene"]');
    if (!eigene) { return; }
    const hell = (hex) => '#' + [1, 3, 5].map((i) => Math.round(parseInt(hex.substr(i, 2), 16) * .12 + 255 * .88).toString(16).padStart(2, '0')).join('');
    const anwenden = () => {
        const wahl = document.querySelector('input[name="farbschema"]:checked');
        const farbe = wahl.value === 'eigene' ? eigene.value : wahl.dataset.farbe;
        document.documentElement.style.setProperty('--primaer', farbe);
        document.documentElement.style.setProperty('--primaer-hell', hell(farbe));
    };
    document.querySelectorAll('input[name="farbschema"]').forEach((r) => r.addEventListener('change', anwenden));
    eigene.addEventListener('input', () => { document.querySelector('input[name="farbschema"][value="eigene"]').checked = true; anwenden(); });
});

/* E-Mail-Versand: öffentliche Adresse aus der aufgerufenen Seite übernehmen (Basis-Pfad aus data-adresse-uebernehmen). */
document.addEventListener('click', (ereignis) => {
    const knopf = ereignis.target.closest('[data-adresse-uebernehmen]');
    if (!knopf) { return; }
    const feld = knopf.closest('label').querySelector('input[name="url"]');
    feld.value = (location.origin + knopf.dataset.adresseUebernehmen).replace(/\/+$/, '');
});
