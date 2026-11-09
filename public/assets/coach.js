/* Coaching-Portal: „Meine Starter live“ aktualisiert sich bei Änderungen; Drucken der Startgeld-Übersicht.
   Als eigene Datei (keine Inline-Skripte, CSP). */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const live = document.getElementById('coach-live');
    if (live && window.Wettkampf) {
        Wettkampf.beobachten(live.dataset.revisionUrl, () => {
            sessionStorage.setItem('coach-scroll', String(window.scrollY));
            location.reload();
        }, 10000);
        const y = sessionStorage.getItem('coach-scroll');
        if (y) { window.scrollTo(0, parseInt(y, 10)); sessionStorage.removeItem('coach-scroll'); }
    }
    document.querySelectorAll('[data-drucken]').forEach((knopf) => knopf.addEventListener('click', () => window.print()));
});
