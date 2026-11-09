/* Online-Anmeldung: Teilnehmerzeilen und Teams hinzufügen/entfernen, Team-Auswahl und Startgeld live aktualisieren. */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const link = document.getElementById('bearbeitungslink');
    if (link) { link.value = new URL(link.value, location.href).href; }

    const formular = document.getElementById('anmeldeformular');
    if (!formular) { return; }
    const tabelle = document.querySelector('#teilnehmer tbody');
    const teams = document.getElementById('teams');
    const preise = JSON.parse(formular.dataset.preise || '{}');
    const POSTEN = [['preis_kata', 'Kata Einzel'], ['preis_kumite', 'Kumite Einzel'], ['preis_kata_team', 'Kata Team'], ['preis_kumite_team', 'Kumite Team']];
    const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const euro = (cent) => (cent / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    const feld = (zeile, name) => zeile.querySelector(`[name$="[${name}]"]`);

    /** Zeilen mit Inhalt (leere Zeilen werden beim Absenden ignoriert). */
    const zeilen = () => [...tabelle.rows].filter((z) => ['vorname', 'nachname', 'geburtsdatum'].some((n) => feld(z, n).value.trim() !== ''));

    function nummerieren() {
        [...tabelle.rows].forEach((z, i) => { z.querySelector('.nr').textContent = i + 1; });
    }

    function teilnehmerNeu() {
        const vorlage = tabelle.rows[0];
        const schluessel = Math.max(0, ...[...tabelle.rows].map((z) => parseInt(z.dataset.schluessel, 10))) + 1;
        const zeile = vorlage.cloneNode(true);
        zeile.dataset.schluessel = schluessel;
        zeile.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/^teilnehmer\[\d+\]/, `teilnehmer[${schluessel}]`);
            if (el.type === 'checkbox') { el.checked = false; } else { el.value = ''; }
        });
        tabelle.appendChild(zeile);
        nummerieren();
        feld(zeile, 'vorname').focus();
    }

    /** Auswahllisten der Teams an die aktuellen Teilnehmer anpassen (Auswahl bleibt erhalten). */
    function mitgliederAktualisieren() {
        const namen = zeilen().map((z) => [z.dataset.schluessel, `${feld(z, 'vorname').value} ${feld(z, 'nachname').value}`.trim()]);
        teams.querySelectorAll('select[data-mitglied]').forEach((auswahl) => {
            const wert = auswahl.value;
            const leer = auswahl.options[0].textContent;
            auswahl.innerHTML = `<option value="">${esc(leer)}</option>`
                + namen.map(([k, n]) => `<option value="${esc(k)}"${k === wert ? ' selected' : ''}>${esc(n)}</option>`).join('');
        });
    }

    function teamNeu() {
        const nr = Math.max(-1, ...[...teams.querySelectorAll('[data-team] [name$="[name]"]')].map((el) => parseInt(/teams\[(\d+)\]/.exec(el.name)[1], 10))) + 1;
        teams.insertAdjacentHTML('beforeend', document.getElementById('team-vorlage').innerHTML.replaceAll('__I__', nr));
        mitgliederAktualisieren();
        preisBerechnen();
    }

    function preisBerechnen() {
        const anzahl = {
            preis_kata: zeilen().filter((z) => feld(z, 'kata').checked).length,
            preis_kumite: zeilen().filter((z) => feld(z, 'kumite').value !== '').length,
            preis_kata_team: [...teams.querySelectorAll('[name$="[disziplin]"]')].filter((s) => s.value === 'kata_team').length,
            preis_kumite_team: [...teams.querySelectorAll('[name$="[disziplin]"]')].filter((s) => s.value === 'kumite_team').length,
        };
        const posten = POSTEN.filter(([k]) => anzahl[k] > 0).map(([k, text]) => ({ text, anzahl: anzahl[k], einzel: preise[k] || 0, summe: anzahl[k] * (preise[k] || 0) }));
        const summe = posten.reduce((s, p) => s + p.summe, 0);
        [...tabelle.rows].forEach((z) => {
            z.querySelector('[data-betrag]').textContent = euro((feld(z, 'kata').checked ? preise.preis_kata || 0 : 0) + (feld(z, 'kumite').value !== '' ? preise.preis_kumite || 0 : 0));
        });
        teams.querySelectorAll('[data-team]').forEach((t) => {
            const d = t.querySelector('[name$="[disziplin]"]').value;
            t.querySelector('[data-team-preis]').textContent = d ? `· Startgeld ${euro(preise['preis_' + d] || 0)}` : '';
        });
        document.getElementById('preis').innerHTML = `<table class="tabelle preis-tabelle">
            <thead><tr><th>Posten</th><th class="rechts">Anzahl</th><th class="rechts">Einzelpreis</th><th class="rechts">Summe</th></tr></thead>
            <tbody>${posten.length ? posten.map((p) => `<tr><td>${p.text}</td><td class="rechts">${p.anzahl}</td><td class="rechts">${euro(p.einzel)}</td><td class="rechts">${euro(p.summe)}</td></tr>`).join('')
                : '<tr><td colspan="4" class="leise">Noch keine Starts eingetragen.</td></tr>'}</tbody>
            <tfoot><tr><th colspan="3">Gesamtpreis</th><th class="rechts">${euro(summe)}</th></tr></tfoot></table>`;
    }

    formular.addEventListener('click', (ev) => {
        const knopf = ev.target.closest('[data-aktion]');
        if (!knopf) { return; }
        const aktion = knopf.dataset.aktion;
        if (aktion === 'teilnehmer-neu') {
            teilnehmerNeu();
        } else if (aktion === 'zeile-weg') {
            const zeile = knopf.closest('tr');
            if (tabelle.rows.length > 1) { zeile.remove(); } else { zeile.querySelectorAll('[name]').forEach((el) => { if (el.type === 'checkbox') { el.checked = false; } else { el.value = ''; } }); }
            nummerieren();
        } else if (aktion === 'team-neu') {
            teamNeu();
            return;
        } else if (aktion === 'team-weg') {
            knopf.closest('[data-team]').remove();
        }
        mitgliederAktualisieren();
        preisBerechnen();
    });
    formular.addEventListener('input', (ev) => {
        if (ev.target.closest('#teilnehmer') && /\[(vorname|nachname|geburtsdatum)\]$/.test(ev.target.name)) { mitgliederAktualisieren(); }
        preisBerechnen();
    });
    formular.addEventListener('change', preisBerechnen);
    mitgliederAktualisieren();
    preisBerechnen();
});
