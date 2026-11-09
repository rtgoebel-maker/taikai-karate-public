#!/usr/bin/env bash
# Aktualisiert die Software auf einem Hallen-Rechner (Linux) auf den neuesten getesteten Stand
# aus dem Branch "halle". Einrichtung und Ablauf: docs/HALLE-LINUX.md, Kap. 8.
#
#   ./update-halle.sh            prüfen und aktualisieren
#   ./update-halle.sh --pruefen  nur anzeigen, ob es eine neue Version gibt
#
# Der Ordner data/ (Datenbank, Kennung dieses Rechners) bleibt unverändert; die Datenbank wird beim
# nächsten Start automatisch angepasst. Solange dieser Rechner ein Turnier führt, wird nicht aktualisiert.
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"
NUR_PRUEFEN=0
[ "${1:-}" = "--pruefen" ] && NUR_PRUEFEN=1

if [ ! -d .git ]; then
    echo "Dieser Programmordner ist kein Git-Klon des Branches „halle“ – bitte per Paket (ZIP) aktualisieren,"
    echo "siehe docs/HALLE-LINUX.md, Kap. 8."
    exit 1
fi

alt="$(cat VERSION 2>/dev/null || echo unbekannt)"
echo "Installiert: $alt"
git fetch --quiet origin halle
neu="$(git show origin/halle:VERSION 2>/dev/null || echo unbekannt)"
echo "Verfügbar:   $neu"
if [ "$alt" = "$neu" ]; then
    echo "Die Software ist aktuell."
    exit 0
fi
[ "$NUR_PRUEFEN" = 1 ] && exit 0

# Nicht aktualisieren, solange dieser Rechner ein Turnier führt (laufende Übergabe von Online)
fuehrt="$("$PHP" -r 'require "vendor/autoload.php"; $app = Wettkampf\App::erstellen(getcwd());
    echo (int) $app->db->wert("SELECT COUNT(*) FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id WHERE t.fuehrung_instanz = ?", [$app->instanz()->id()]);')"
if [ "$fuehrt" != "0" ]; then
    echo
    echo "Dieser Rechner führt gerade ein Turnier von Online (Übergabe läuft)."
    echo "Bitte zuerst an Online zurückgeben (Turnier → Einstellungen → Hallen-Laptop), dann aktualisieren."
    exit 1
fi

echo
echo "Aktualisiere $alt → $neu …"
git reset --quiet --hard origin/halle
echo "Fertig: $(cat VERSION)"
if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet wettkampf 2>/dev/null; then
    echo "Dienste neu starten:  sudo systemctl restart wettkampf wettkampf-sync"
    echo "(Benutzer, Ordner oder Port geändert? Dann: sudo ./dienste-einrichten.sh)"
else
    echo "Falls die Software gerade läuft: mit Strg+C beenden und ./start-lokal.sh neu starten."
fi
