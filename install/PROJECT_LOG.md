# Project Log – 2026-10-01

- Forrás alapja: GitHub `Kvasztics/mara-lab`, `1ae38a4` commit.
- Pi: Debian 13 trixie, aarch64, PHP 8.4.24. Nginx, MariaDB, PHP-FPM és PHP MySQL/cURL/mbstring telepítve és aktív.
- Meglévő Mara: `/var/www/mara/public`, 80-as port, `/run/php/php8.4-fpm.sock`. Új próba: `/var/www/mara-lab`, 8081, `maralab`; port és adatbázis szabad a felhasználó ellenőrzése alapján.
- PROVIDERS konstans marad: támogatott szolgáltatók listája. Az adatbázis ezek engedélyezett részhalmazát tárolja.
- A feltöltött SQL-struktúra és settings export alapján készített telepítési séma; személyes útvonalak törölve, AI-szolgáltatások kezdetben kikapcsolva.
- PHP XML függőség azonosítva a DOMDocument használata alapján.
- Első CLI telepítő: rendszer- és ütközésvizsgálat, dry-run, csomagtelepítés, egyedi DB-felhasználó, admin belépés, külön Nginx site, korlátozott fájljogosultságok, napló és HTTP ellenőrzés.
- Helyi ellenőrzés: Bash szintaxis, help és nem támogatott rendszer elutasítása. A tesztkörnyezet Ubuntu 24.04, így tényleges telepítést nem futtattunk.
- PHP/MariaDB tesztkörnyezet telepítése a konténer jogosultságkorlátai miatt nem sikerült; PHP lint és SQL-import még a Pi-n ellenőrizendő.

- Pi próbaüzem és telepítés sikeres; SQL-import, admin létrehozás, Nginx és HTTP 200 ellenőrzés sikeres. Felhasználó belépett.
- Üres modellállapot: newmodel 500 a hiányzó session model_id miatt. A core/main.php hat chat_titles hívása most üres listát ad aktív modell nélkül. Pi ellenőrzés következik.

### 2026-10-01 — llama.cpp metrics
- Pi chat confirmed working after setting binary and increasing request timeout to 600 seconds on Pi.
- Token counts display, but generation duration and speed were zero: renderer expects meta.eval_duration in nanoseconds.
- LlamaCppProvider now maps timings.predicted_ms to meta.eval_duration (milliseconds × 1,000,000). Pi syntax and live response validation pending.

## 2026-10-02 – Provider választás a telepítőben
- Ollama / llama.cpp / mindkettő, külön meglévő vagy új telepítés; mindkettőnél választható alapértelmezett.
- system.providers, system.provider, system.multi_provider és a kiválasztott provider adatai mentve a DB-be.
- Ollama: hivatalos Linux telepítő; meglévő backend esetén /api/tags ellenőrzés.
- llama.cpp: helyi direct mód, CPU CMake fordítás két szálon, commit azonosító a buildmappában; bináris és GGUF mappa elérhetőség www-data-ként ellenőrizve. Nincs automatikus modellletöltés.
- --check-providers: csak provider kérdések és ellenőrzések, nincs telepítés / DB-módosítás.
- Helyi ellenőrzés: bash -n mindkét shell fájlon; izolált menü-, alapértelmezés-, port-, útvonal- és jogosultsághiba próbák sikeresek. Beküldött settings seed egyezik.
- PHP CLI nincs a helyi tesztkörnyezetben: php -l és teljes DB-/backendtelepítés még Pi-n ellenőrizendő.
- Tegnapi Pi mérés élőben sikeres: 76 token / 23.97 másodperc / 3.2 token/s. cURL timeout 600 másodperc.
