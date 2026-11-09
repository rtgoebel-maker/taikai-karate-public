/* Statusseite: bei Änderungen (neue Ergebnisse, nächster Kampf) neu laden; Scrollposition bleibt erhalten.
   Adresse der Revisionsabfrage aus data-beobachten am <body>. */
document.addEventListener('DOMContentLoaded', () => {
    const url = document.body.dataset.beobachten;
    if (!url) { return; }
    const y = sessionStorage.getItem('live-scroll');
    if (y) { window.scrollTo(0, parseInt(y, 10)); sessionStorage.removeItem('live-scroll'); }
    Wettkampf.beobachten(url, () => {
        sessionStorage.setItem('live-scroll', String(window.scrollY));
        location.reload();
    }, 5000);
    new MutationObserver(() => {
        const weg = document.body.classList.contains('offline');
        const status = document.getElementById('live-status');
        status.classList.toggle('weg', weg);
        status.querySelector('.live-text').textContent = weg ? 'keine Verbindung' : 'live';
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
});
