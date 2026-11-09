#!/usr/bin/env bash
# Richtet die Wettkampfsoftware unter Linux als Dienste ein (systemd): Server für Leitung und Tablets
# sowie die Übertragung an Online. Danach läuft beides nach jedem Einschalten von selbst und startet
# nach einem Absturz neu. Anleitung: docs/HALLE-LINUX.md, Kap. 6.
#
#   sudo ./dienste-einrichten.sh             einrichten und starten
#   ./dienste-einrichten.sh --anzeigen       nur zeigen, was angelegt würde (ohne sudo)
#   sudo ./dienste-einrichten.sh --entfernen Dienste stoppen und entfernen (Programm und Daten bleiben)
#
# Benutzer: der, der sudo aufruft (nicht root). Anderer Benutzer: sudo BENUTZER=name ./dienste-einrichten.sh
#   sudo ./dienste-einrichten.sh --ohne-firewall   Firewall nicht anfassen (sonst: Port aus privaten Netzen freigeben)
#
# Port: Standard 80 (Tablets ohne „:8080“ in der Adresse), anderer: sudo PORT=8080 ./dienste-einrichten.sh
# Parallele Anfragen: PHP_CLI_SERVER_WORKERS=8. HTTPS/443 kann der integrierte PHP-Server nicht (docs/HALLE-LINUX.md Kap. 5).
set -euo pipefail
cd "$(dirname "$0")"
ORDNER="$(pwd)"
MODUS=einrichten
MIT_FIREWALL=1
for arg in "$@"; do
    case "$arg" in
        --ohne-firewall) MIT_FIREWALL=0 ;;
        *) MODUS="$arg" ;;
    esac
done
PORT="${PORT:-80}"
WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
BENUTZER="${BENUTZER:-${SUDO_USER:-$(id -un)}}"
SERVER=wettkampf
SYNC=wettkampf-sync
ZIEL=/etc/systemd/system

fehler() { echo "Fehler: $*" >&2; exit 1; }

# --- Firewall: Port nur aus privaten Netzen (Hallen-WLAN, Heimnetz) freigeben, nicht aus dem Internet ----------
# Alle privaten Bereiche statt nur des aktuellen Netzes: Eingerichtet wird oft zu Hause (z. B. 192.168.178.x),
# in der Halle vergibt der eigene Router andere Adressen (z. B. 192.168.1.x).
NETZE="10.0.0.0/8 172.16.0.0/12 192.168.0.0/16"
KENNUNG="Wettkampfsoftware"

firewall_art() {
    if command -v ufw >/dev/null 2>&1 && LC_ALL=C ufw status 2>/dev/null | grep -q "^Status: active"; then
        echo ufw
    elif command -v firewall-cmd >/dev/null 2>&1 && firewall-cmd --state >/dev/null 2>&1; then
        echo firewalld
    else
        echo keine
    fi
}

firewall_oeffnen() {
    case "$(firewall_art)" in
        ufw)
            for netz in $NETZE; do ufw allow from "$netz" to any port "$PORT" proto tcp comment "$KENNUNG" >/dev/null; done
            echo "  ✓ Firewall (ufw): Port $PORT aus privaten Netzen freigegeben" ;;
        firewalld)
            for netz in $NETZE; do
                firewall-cmd --permanent --add-rich-rule="rule family=ipv4 source address=$netz port port=$PORT protocol=tcp accept" >/dev/null
            done
            firewall-cmd --reload >/dev/null
            echo "  ✓ Firewall (firewalld): Port $PORT aus privaten Netzen freigegeben" ;;
        *)
            echo "  – keine aktive Firewall gefunden (ufw/firewalld) – Port $PORT ist erreichbar, nichts zu tun" ;;
    esac
}

firewall_schliessen() {
    case "$(firewall_art)" in
        ufw)
            # eigene Regeln (Kennung im Kommentar) von hinten löschen, damit die Nummern stimmen
            for nr in $(ufw status numbered | grep "$KENNUNG" | sed -E 's/^\[ *([0-9]+)\].*/\1/' | sort -rn); do
                ufw --force delete "$nr" >/dev/null
            done
            echo "Firewall-Regeln (ufw) der Wettkampfsoftware entfernt." ;;
        firewalld)
            for regel in $(firewall-cmd --permanent --list-rich-rules | grep -o 'port port="[0-9]*"' | grep -o '[0-9]*' | sort -u); do
                for netz in $NETZE; do
                    firewall-cmd --permanent --remove-rich-rule="rule family=ipv4 source address=$netz port port=$regel protocol=tcp accept" >/dev/null 2>&1 || true
                done
            done
            firewall-cmd --reload >/dev/null
            echo "Firewall-Regeln (firewalld) der Wettkampfsoftware entfernt." ;;
    esac
}

case "$MODUS" in
    einrichten|--anzeigen|--entfernen) ;;
    *) fehler "unbekannte Option „$MODUS“ (erlaubt: --anzeigen, --entfernen, --ohne-firewall)" ;;
esac

if [ "$MODUS" = "--entfernen" ]; then
    [ "$(id -u)" -eq 0 ] || fehler "bitte mit sudo aufrufen: sudo ./dienste-einrichten.sh --entfernen"
    systemctl disable --now "$SERVER" "$SYNC" 2>/dev/null || true
    rm -f "$ZIEL/$SERVER.service" "$ZIEL/$SYNC.service"
    [ "$MIT_FIREWALL" = 1 ] && firewall_schliessen
    systemctl daemon-reload
    echo "Dienste $SERVER und $SYNC entfernt. Programm und Daten in $ORDNER sind unverändert."
    exit 0
fi

# --- Prüfungen ------------------------------------------------------------------------------
[ "$BENUTZER" != "root" ] || fehler "die Software nicht als root betreiben. Als normaler Benutzer anmelden und
    sudo ./dienste-einrichten.sh
aufrufen (oder sudo BENUTZER=name ./dienste-einrichten.sh)."
id "$BENUTZER" >/dev/null 2>&1 || fehler "Benutzer „$BENUTZER“ gibt es nicht."
PHP="${PHP:-$(command -v php || true)}"
[ -n "$PHP" ] || fehler "PHP nicht gefunden – siehe docs/HALLE-LINUX.md Kap. 2 (sudo apt install php-cli php-sqlite3 php-mbstring php-zip)."
"$PHP" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || fehler "PHP $("$PHP" -r 'echo PHP_VERSION;') ist zu alt, benötigt wird 8.3 oder neuer."
fehlt=$("$PHP" -r 'foreach (["pdo_sqlite", "mbstring", "openssl", "zlib"] as $e) { if (!extension_loaded($e)) { echo "$e "; } }')
[ -z "$fehlt" ] || fehler "fehlende PHP-Erweiterungen: $fehlt(sudo apt install php-sqlite3 php-mbstring)"
[ -f vendor/autoload.php ] || fehler "der Ordner vendor fehlt – vollständiges Hallen-Paket verwenden (docs/HALLE-LINUX.md Kap. 3)."
[[ "$PORT" =~ ^[0-9]+$ ]] && [ "$PORT" -ge 1 ] && [ "$PORT" -le 65535 ] || fehler "PORT muss eine Zahl zwischen 1 und 65535 sein."
[ "$PORT" != "443" ] || fehler "Port 443 steht für HTTPS – das kann der integrierte PHP-Server nicht (docs/HALLE-LINUX.md Kap. 5). Bitte 80 verwenden."
# Ports unter 1024 darf sonst nur root öffnen: dem Dienst genau dieses eine Recht geben, er läuft weiter als $BENUTZER
RECHT=""
if [ "$PORT" -lt 1024 ]; then
    RECHT=$'AmbientCapabilities=CAP_NET_BIND_SERVICE\nCapabilityBoundingSet=CAP_NET_BIND_SERVICE'
fi
ADRESSE=$([ "$PORT" = "80" ] && echo "" || echo ":$PORT")

# --- Dienste ---------------------------------------------------------------------------------
dienst_server() {
    cat <<EOF
[Unit]
Description=Wettkampfsoftware (Server für Leitung und Tablets)
After=network.target

[Service]
User=$BENUTZER
WorkingDirectory=$ORDNER
Environment=PHP_CLI_SERVER_WORKERS=$WORKERS
ExecStart=$PHP -S 0.0.0.0:$PORT -t public public/router.php
$RECHT
Restart=always
RestartSec=3
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=full

[Install]
WantedBy=multi-user.target
EOF
}

dienst_sync() {
    cat <<EOF
[Unit]
Description=Wettkampfsoftware – Übertragung Hallen-Laptop an Online
After=network.target

[Service]
User=$BENUTZER
WorkingDirectory=$ORDNER
ExecStart=$PHP bin/sync.php
Restart=always
RestartSec=10
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=full

[Install]
WantedBy=multi-user.target
EOF
}

if [ "$MODUS" = "--anzeigen" ]; then
    echo "# $ZIEL/$SERVER.service"; dienst_server; echo
    echo "# $ZIEL/$SYNC.service"; dienst_sync
    echo
    if [ "$MIT_FIREWALL" = 1 ]; then
        echo "# Firewall: $(firewall_art) – Port $PORT aus $NETZE freigeben (nicht aus dem Internet)"
    else
        echo "# Firewall: wird nicht angefasst (--ohne-firewall)"
    fi
    exit 0
fi

[ "$(id -u)" -eq 0 ] || fehler "bitte mit sudo aufrufen: sudo ./dienste-einrichten.sh"
command -v systemctl >/dev/null 2>&1 || fehler "systemd (systemctl) nicht gefunden – dann ./start-lokal.sh verwenden."

# Port schon belegt (z. B. ./start-lokal.sh oder ein installierter Apache/nginx)? Der eigene Dienst zählt nicht.
if ! systemctl is-active --quiet "$SERVER" && command -v ss >/dev/null 2>&1 && ss -Hltn "sport = :$PORT" | grep -q .; then
    fehler "Port $PORT ist belegt (läuft ./start-lokal.sh oder ein anderer Webserver, z. B. Apache? systemctl status apache2) – beenden oder anderen Port wählen: sudo PORT=8080 ./dienste-einrichten.sh"
fi

# Daten gehören dem Benutzer des Dienstes, nur er darf sie lesen (Geburtsdaten)
mkdir -p data
chown -R "$BENUTZER": data
chmod 700 data
if [ "$(stat -c %U "$ORDNER")" != "$BENUTZER" ]; then
    echo "Hinweis: Der Programmordner gehört nicht $BENUTZER – für ./update-halle.sh ggf.: sudo chown -R $BENUTZER: \"$ORDNER\""
fi

dienst_server > "$ZIEL/$SERVER.service"
dienst_sync > "$ZIEL/$SYNC.service"
systemctl daemon-reload
systemctl enable --now "$SERVER" "$SYNC" >/dev/null 2>&1
systemctl restart "$SERVER" "$SYNC"
[ "$MIT_FIREWALL" = 1 ] && firewall_oeffnen
sleep 2

echo
for d in "$SERVER" "$SYNC"; do
    if systemctl is-active --quiet "$d"; then echo "  ✓ $d läuft"; else echo "  ✗ $d läuft NICHT – Ursache: journalctl -u $d -n 30"; fi
done
echo
echo "Wettkampfsoftware unter http://localhost$ADRESSE (Benutzer $BENUTZER, Ordner $ORDNER, Port $PORT)."
echo "Tablets im selben WLAN:"
for ip in $(hostname -I 2>/dev/null); do
    if [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then echo "     http://$ip$ADRESSE/tisch"; fi
done
echo
echo "Startet ab jetzt nach jedem Einschalten von selbst. Nützlich:"
echo "  systemctl status $SERVER $SYNC                 # läuft?"
echo "  journalctl -u $SYNC -f                         # Protokoll der Übertragung"
echo "  sudo systemctl restart $SERVER $SYNC           # nach einem Update"
echo "  sudo ./dienste-einrichten.sh --entfernen        # Dienste wieder entfernen"
