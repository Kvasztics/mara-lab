# Mara-Lab telepítő – első tesztváltozat

Célrendszer: Debian 13, ARM64 vagy x86_64, systemd. Friss, külön Mara-Lab telepítést készít. A csomagban található forráskódot másolja; nem tölti le automatikusan a GitHub legújabb változatát.

## Próbaüzem a Pi-n

Az archívum kibontása után, a `mara-lab` mappában:

```bash
sudo bash install/install.sh --dry-run
```

Ez nem telepít csomagot, nem ír fájlt vagy adatbázist. Kiírja a rendszeradatokat, a csomagállapotokat és a tervezett célokat. Futó MariaDB és root jogosultság esetén ellenőrzi az adatbázisnév ütközését is.

## Telepítés

```bash
sudo bash install/install.sh
```

Megkérdezi az első admin nevét, e-mailjét és jelszavát. Az adatbázis jelszavát véletlenszerűen generálja, és a `config/config.php` fájlba írja. A jelszavakat nem naplózza. Az első felhasználó jelszavát PHP `password_hash()`-sal tárolja.

Alapértékek: `/var/www/mara-lab`, 8081-es port, `maralab` adatbázis és adatbázis-felhasználó. Felülírhatók:

```bash
sudo bash install/install.sh --dir /var/www/mara-lab-test --port 8083 --database maralab_test
```

A célmappa, port, Nginx-konfignév, adatbázis és adatbázis-felhasználó ütközése esetén megáll. Újrafuttatáskor meglévő telepítést nem frissít. A meglévő Nginx site-okat nem írja át. A csomagkezelő a közös rendszerfüggőségeket telepíti/frissíti, a szükséges szolgáltatásokat elindítja és engedélyezi, az Nginxet a végén újratölti.

Függőségek: nginx, mariadb-server, php-fpm, php-cli, php-mysql, php-curl, php-mbstring, php-xml, git, ca-certificates, curl. A `--with-audio-tools` az ffmpeg és espeak-ng csomagokat is telepíti; AI-modelleket nem tölt le.

A belépési oldal: `http://PI_IP:8081/auth/login`, illetve működő helyi névfeloldásnál `http://mara-srv-01.local:8081/auth/login`.

## Kezdőbeállítások

Az SQL csak a kilenc tábla struktúráját és a settings alapértékeit tartalmazza. A meglévő beszélgetések, modellek és felhasználók nem kerülnek át. Ollama/llama.cpp és TTS kezdetben nincs engedélyezve. A böngészős STT szerepel a beállításokban; távoli böngészőből a mikrofon használatához megfelelő biztonságos webes kapcsolat kellhet, a HTTP-s próba a webes alap ellenőrzésére szolgál.

A külső AI-programok és modellek útvonalai üresek. A helyi llama.cpp alapbeállítás CPU (`gpu_layers=0`), eSpeak hang `hu`. A PID és napló a telepítési mappán belüli, www-data számára írható `var/run` és `var/log` alá kerül. A következő lépés az AI-szolgáltatások telepítése vagy meglévő szerverek beállítása.

## Hibakezelés és ellenőrzés

A telepítő hiba esetén megáll és naplót ad: `/var/log/mara-lab-PORT-install-IDŐPONT.log`. Nem végez teljes automatikus visszavonást: csomagok, új mappa és részben létrehozott adatbázis megmaradhatnak. Újrapróbálás előtt ezeket ellenőrizni kell. Nginx konfigurációellenőrzési hibánál az új site linkjét eltávolítja, és nem tölti be.

Még Pi-n ellenőrizendő: csomagtelepítés, SQL-import MariaDB-be, első belépés, modell nélküli UI és a meglévő Mara párhuzamos működése. A telepítő a végén automatikusan ellenőrzi a belépési oldal HTTP 200 válaszát.

## Provider választás
A telepítő legalább egy providert kér: Ollama, llama.cpp vagy mindkettő. Mindkettőnél választható az alapértelmezett, és külön-külön a meglévő/új telepítés.

- Meglévő Ollama: futó helyi vagy távoli szerver alap URL-je (pl. http://127.0.0.1:11434), /api/tags ellenőrzéssel.
- Új Ollama: https://ollama.com/install.sh hivatalos telepítő, majd ollama.service indítás. Már telepített Ollamát nem ír felül.
- Meglévő llama.cpp: helyi direct mód; http://127.0.0.1:PORT, llama-server teljes útvonala és GGUF modellmappa. A www-data számára olvasható/futtatható legyen, a szülőmappák is legyenek átjárhatók. Nem módosítjuk automatikusan a meglévő fájlok jogosultságait.
- Új llama.cpp: CPU fordítás /opt/mara-llama-PORT alatt, két szálon; modellmappa /var/lib/mara-llama-PORT/models. A Mara indítja a szervert a kiválasztott modellel; nincs külön llama systemd service. A build commitja MARA_BUILD_COMMIT fájlba kerül.
- A szolgáltatás telepítése nem tölt le modellt; Ollamához külön pull, llama.cpp-hez külön GGUF fájl szükséges. GPU gyorsítás telepítése külön feladat.

Csak a provider-adatok ellenőrzése a már telepített Pi-n:
```bash
sudo bash install/install.sh --check-providers
```
Ez interaktív, és nem hoz létre oldalt vagy adatbázist. A --dry-run továbbra is csak az alapcsomagokat és célütközéseket vizsgálja.

Szintaxis ellenőrzés telepítés előtt:
```bash
bash -n install/install.sh
bash -n install/providers.sh
php -l install/setup.php
```

A telepítési ágak még teljes Pi-próbát igényelnek. Továbbra is Debian 13 a támogatott rendszer. Az upstream telepítő/forrás a futtatáskor aktuális verziót tölti le. Sikertelen telepítés után a napló alapján ellenőrizd az új könyvtárakat, DB-t és szolgáltatásokat újrafuttatás előtt; teljes automatikus visszavonás nincs.
