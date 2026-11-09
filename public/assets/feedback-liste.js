/* Feedback-Liste (Administration): Status und Notiz direkt speichern. Adresse aus data-speichern der Tabelle. */
document.addEventListener('DOMContentLoaded', () => {
    const tabelle = document.querySelector('[data-speichern]');
    if (!tabelle) { return; }
    const speichern = async (zeile, daten) => {
        const anzeige = zeile.querySelector('.fb-gespeichert');
        try {
            await Wettkampf.post(tabelle.dataset.speichern + '/' + zeile.dataset.feedback, daten);
            anzeige.textContent = 'gespeichert ✓';
        } catch (e) {
            anzeige.textContent = e.message;
        }
    };
    tabelle.querySelectorAll('tr[data-feedback]').forEach((zeile) => {
        zeile.querySelector('.fb-status').addEventListener('change', (ev) => speichern(zeile, { status: ev.target.value }));
        const notiz = zeile.querySelector('.fb-notiz');
        let alt = notiz.value;
        notiz.addEventListener('blur', () => { if (notiz.value !== alt) { alt = notiz.value; speichern(zeile, { notiz: notiz.value }); } });
    });
});
