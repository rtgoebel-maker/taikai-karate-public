/* Ergebnis einer Begegnung (Wettkampfleitung): Flaggen-Unentschieden = Kampfrichter minus Aka- und Shiro-Flaggen.
   Anzahl der Kampfrichter aus data-kampfrichter des Formulars #ergebnis. */
(() => {
    const formular = document.getElementById('ergebnis');
    if (!formular) { return; }
    const kampfrichter = parseInt(formular.dataset.kampfrichter || '0', 10);
    document.querySelectorAll('[data-flaggen]').forEach((feld) => {
        feld.addEventListener('input', () => {
            const art = feld.dataset.flaggen;
            const werte = [...document.querySelectorAll('[data-flaggen="' + art + '"]')].map((f) => parseInt(f.value || '0', 10));
            const rest = kampfrichter - werte.reduce((a, b) => a + b, 0);
            document.querySelector('[name="' + art + '_unentschieden"]').value = Math.max(0, rest);
            feld.setCustomValidity(rest < 0 ? 'Mehr Flaggen als Kampfrichter' : '');
        });
    });
})();
