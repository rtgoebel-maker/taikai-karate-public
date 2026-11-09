/* Zeitplan: beendete Gruppen ein-/ausklappen und Karten per Ziehen verschieben.
   Adresse zum Verschieben aus data-verschieben am Container .plan. */
(() => {
    const zeigen = document.getElementById('beendete-zeigen');
    const plan = document.querySelector('.plan[data-verschieben]');
    if (!zeigen || !plan) { return; }

    // Beendete Gruppen: standardmäßig eingeklappt, Schalter für alle Spalten (merkt sich der Browser)
    const anwenden = () => document.querySelectorAll('.beendete-gruppen').forEach((d) => { d.open = zeigen.checked; });
    try { zeigen.checked = localStorage.getItem('plan-beendete-zeigen') === '1'; } catch (e) { /* ohne Speicher */ }
    anwenden();
    zeigen.addEventListener('change', () => {
        try { localStorage.setItem('plan-beendete-zeigen', zeigen.checked ? '1' : '0'); } catch (e) { /* ohne Speicher */ }
        anwenden();
    });

    const url = plan.dataset.verschieben;
    let gezogen = null;
    document.querySelectorAll('.plan-karte').forEach((karte) => {
        karte.addEventListener('dragstart', (e) => { gezogen = karte; karte.classList.add('ziehen'); e.dataTransfer.effectAllowed = 'move'; });
        karte.addEventListener('dragend', () => { karte.classList.remove('ziehen'); });
    });
    document.querySelectorAll('.plan-spalte').forEach((spalte) => {
        spalte.addEventListener('dragover', (e) => { e.preventDefault(); spalte.classList.add('ueber'); });
        spalte.addEventListener('dragleave', () => spalte.classList.remove('ueber'));
        spalte.addEventListener('drop', async (e) => {
            e.preventDefault();
            spalte.classList.remove('ueber');
            if (!gezogen) { return; }
            // Position: Index der ersten Karte, deren Mitte unterhalb des Mauszeigers liegt
            const karten = [...spalte.querySelectorAll('.plan-karte')].filter((k) => k !== gezogen);
            let position = karten.findIndex((k) => { const r = k.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
            if (position < 0) { position = karten.length; }
            try {
                await Wettkampf.post(url, { phase_id: gezogen.dataset.phase, tatami_id: spalte.dataset.tatami, position });
                location.reload();
            } catch (fehler) {
                Wettkampf.hinweis(fehler.message, { titel: 'Verschieben nicht möglich', art: 'gefahr' });
            }
        });
    });
})();
