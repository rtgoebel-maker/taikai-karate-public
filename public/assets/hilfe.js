/* Hilfe: Abschnitte beim Tippen filtern. */
(() => {
    const suche = document.getElementById('hilfe-suche');
    if (!suche) { return; }
    const abschnitte = [...document.querySelectorAll('.hilfe-abschnitt')];
    suche.addEventListener('input', () => {
        const woerter = suche.value.toLowerCase().split(/\s+/).filter(Boolean);
        let treffer = 0;
        abschnitte.forEach((a) => {
            const passt = woerter.every((w) => a.textContent.toLowerCase().includes(w));
            a.hidden = !passt;
            document.querySelector(`[data-anker="${a.id}"]`).hidden = !passt;
            treffer += passt ? 1 : 0;
        });
        document.getElementById('hilfe-nichts').hidden = treffer > 0;
    });
})();
