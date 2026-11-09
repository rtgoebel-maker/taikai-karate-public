/* Teams: Suche und Disziplinfilter (Suchtext bleibt in der Adresse ?q=…). */
(() => {
    const suche = document.getElementById('team-suche');
    if (!suche) { return; }
    const disziplin = document.getElementById('team-disziplin');
    const karten = [...document.querySelectorAll('[data-team]')];
    const filtern = () => {
        const woerter = suche.value.toLowerCase().split(/\s+/).filter(Boolean);
        let treffer = 0;
        karten.forEach((k) => {
            const passt = (!disziplin.value || k.dataset.disziplin === disziplin.value) && woerter.every((w) => k.dataset.suche.includes(w));
            k.hidden = !passt;
            treffer += passt ? 1 : 0;
        });
        document.getElementById('team-treffer').textContent = treffer === karten.length ? `${karten.length} Teams` : `${treffer} von ${karten.length} Teams`;
        document.getElementById('team-keine').hidden = treffer > 0;
        history.replaceState(null, '', suche.value ? `?q=${encodeURIComponent(suche.value)}` : location.pathname);
    };
    suche.value = new URLSearchParams(location.search).get('q') || '';
    suche.addEventListener('input', filtern);
    disziplin.addEventListener('change', filtern);
    filtern();
})();
