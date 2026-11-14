#!/usr/bin/env bash
# Startet die Wettkampfsoftware unter Linux (oder macOS) mit dem integrierten PHP-Server –
# Gegenstück zu start-lokal.bat. Anleitung: docs/HALLE-LINUX.md
#
#   ./start-lokal.sh            Port 8080
#   PORT=8090 ./start-lokal.sh  anderer Port
#
# Startet zusätzlich im Hintergrund bin/sync.php (Hallen-Laptop → Online, Protokoll: data/sync.log).
# Strg+C beendet beides.
set -u
cd "$(dirname "$0")"

PHP="${PHP:-php}"
PORT="${PORT:-8080}"
# Unter Linux kann der integrierte Server Anfragen parallel bearbeiten: Tablets warten nicht aufeinander
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

if ! command -v "$PHP" >/dev/null 2>&1; then
    echo "PHP wurde nicht gefunden. Bitte PHP 8.3 oder neuer installieren (siehe docs/HALLE-LINUX.md), z. B.:"
    echo "    sudo apt install php-cli php-sqlite3 php-mbstring php-zip php-xml"
    exit 1
fi
if ! "$PHP" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
    echo "PHP $("$PHP" -r 'echo PHP_VERSION;') ist zu alt – benötigt wird 8.3 oder neuer (siehe docs/HALLE-LINUX.md)."
    exit 1
fi
fehlt=$("$PHP" -r 'foreach (["pdo_sqlite", "mbstring", "openssl", "zlib"] as $e) { if (!extension_loaded($e)) { echo "$e "; } }')
if [ -n "$fehlt" ]; then
    echo "Fehlende PHP-Erweiterungen: $fehlt"
    echo "    sudo apt install php-sqlite3 php-mbstring"
    exit 1
fi
if [ ! -f vendor/autoload.php ]; then
    echo "Der Ordner vendor fehlt. Bitte das fertige Paket mit vendor verwenden oder einmalig"
    echo "    composer install --no-dev"
    echo "im Programmordner ausführen (siehe README)."
    exit 1
fi
mkdir -p data

echo
echo " Wettkampfsoftware läuft auf http://localhost:$PORT"
echo " Tablets im selben WLAN verbinden sich mit einer dieser Adressen:"
# Linux: hostname -I, macOS: ipconfig getifaddr – nur echte IPv4-Adressen anzeigen
for ip in $(hostname -I 2>/dev/null || ipconfig getifaddr en0 2>/dev/null); do
    if [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then echo "     http://$ip:$PORT/tisch"; fi
done
echo
echo " Übertragung an Online (nur bei übernommenem Turnier): Protokoll in data/sync.log"
echo " Strg+C beendet Server und Übertragung."
echo

# Hallen-Laptop: Stand übernommener Turniere im Hintergrund an Online senden (eigener Prozess, damit eine
# langsame Verbindung nie die Tablets aufhält). Ohne übernommenes Turnier tut er nichts.
"$PHP" bin/sync.php >>data/sync.log 2>&1 &
SYNC_PID=$!
trap 'kill "$SYNC_PID" 2>/dev/null' EXIT INT TERM

"$PHP" -S "0.0.0.0:$PORT" -t public public/router.php
