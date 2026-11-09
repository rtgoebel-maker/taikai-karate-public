@echo off
rem Startet die Wettkampfsoftware mit dem integrierten PHP-Server.
rem Im Browser: http://localhost:8080  ·  Tablets im WLAN: http://<IP-dieses-Rechners>:8080/tisch
setlocal
cd /d "%~dp0"

set PHP=php
where php >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.*") do set "PHP=%%D\php.exe"
)
"%PHP%" -v >nul 2>nul
if errorlevel 1 (
    echo PHP wurde nicht gefunden. Bitte PHP 8.3 oder neuer installieren, z. B.:
    echo     winget install PHP.PHP.8.3
    pause
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo Der Ordner vendor fehlt. Bitte das fertige Paket mit vendor verwenden oder einmalig
    echo     composer install --no-dev
    echo im Programmordner ausfuehren ^(siehe README^).
    pause
    exit /b 1
)

echo.
echo  Wettkampfsoftware laeuft auf http://localhost:8080
echo  Tablets im selben WLAN verbinden sich mit einer dieser Adressen:
for /f "tokens=2 delims=:" %%A in ('ipconfig ^| findstr /c:"IPv4"') do for /f "tokens=*" %%B in ("%%A") do echo     http://%%B:8080/tisch
echo.
echo  Fenster schliessen oder Strg+C beendet den Server.
echo.
start "" http://localhost:8080
rem Hallen-Laptop: Stand uebernommener Turniere im Hintergrund an Online senden (eigenes Fenster,
rem damit eine langsame Verbindung nie die Tablets aufhaelt). Ohne uebernommenes Turnier tut es nichts.
start "Wettkampf Sync" /min "%PHP%" bin\sync.php
"%PHP%" -S 0.0.0.0:8080 -t public public/router.php
