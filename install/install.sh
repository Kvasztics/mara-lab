#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

SOURCE=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
DEST=/var/www/mara-lab
PORT=8081
DB=maralab
DRY=0
EXTRAS=0
CHECK_PROVIDERS=0
while (($#)); do
  case "$1" in
    --dry-run) DRY=1; shift ;;
    --check-providers) CHECK_PROVIDERS=1; shift ;;
    --with-audio-tools) EXTRAS=1; shift ;;
    --dir|--port|--database)
      (($# >= 2)) || { echo 'Hiányzó argumentum.' >&2; exit 1; }
      case "$1" in --dir) DEST=$2;; --port) PORT=$2;; --database) DB=$2;; esac
      shift 2 ;;
    --help) echo 'Használat: sudo bash install/install.sh [--dry-run] [--check-providers] [--dir /var/www/mara-lab] [--port 8081] [--database maralab] [--with-audio-tools]'; exit 0 ;;
    *) echo "Ismeretlen kapcsoló: $1" >&2; exit 1 ;;
  esac
done
die() { echo "HIBA: $*" >&2; exit 1; }
. "$SOURCE/install/providers.sh"
[[ $DEST =~ ^/var/www/[a-zA-Z0-9_-]+$ ]] || die 'A cél egy új /var/www/ALMAPPANÉV legyen.'
[[ $DB =~ ^[a-z][a-z0-9_]{0,31}$ ]] || die 'Érvénytelen adatbázisnév.'
[[ $PORT =~ ^[0-9]{1,5}$ ]] || die 'Érvénytelen port.'
PORT=$((10#$PORT))
((PORT >= 1024 && PORT <= 65535)) || die 'A port 1024–65535 közötti legyen.'
[[ -r /etc/os-release ]] || die 'Nem azonosítható rendszer.'
. /etc/os-release
[[ $ID == debian && $VERSION_ID == 13 ]] || die 'Az első változat csak Debian 13-at támogat.'
ARCH=$(uname -m)
[[ $ARCH == aarch64 || $ARCH == x86_64 ]] || die "Nem támogatott architektúra: $ARCH"
SITE="mara-lab-$PORT"
if ((CHECK_PROVIDERS == 0)); then
[[ ! -e $DEST && ! -L $DEST ]] || die "A cél már létezik: $DEST"
[[ ! -e /etc/nginx/sites-available/$SITE && ! -L /etc/nginx/sites-enabled/$SITE ]] || die 'Az Nginx-beállítás már létezik.'
if command -v ss >/dev/null; then
  [[ -z $(ss -H -ltn "sport = :$PORT") ]] || die 'A választott port foglalt.'
fi
fi
PACKAGES=(sudo nginx mariadb-server php-fpm php-cli php-mysql php-curl php-mbstring php-xml git ca-certificates curl)
((EXTRAS == 0)) || PACKAGES+=(ffmpeg espeak-ng)
echo "Rendszer: Debian $VERSION_ID / $ARCH"
echo "Cél: $DEST | port: $PORT | adatbázis: $DB"
echo "Csomagok: ${PACKAGES[*]}"
if ((DRY)); then
  if command -v dpkg-query >/dev/null; then
    for package in "${PACKAGES[@]}"; do
      state=$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)
      echo "$package: ${state:-hiányzik}"
    done
  fi
  if ((EUID == 0)) && command -v mariadb >/dev/null && systemctl is-active --quiet mariadb; then
    [[ -z $(mariadb -N -e "SHOW DATABASES LIKE '$DB';") ]] || die 'Az adatbázis már létezik.'
  else
    echo 'Az adatbázis ütközésvizsgálata a tényleges telepítéskor történik.'
  fi
  echo 'Próbaüzem kész: nem történt módosítás.'
  exit 0
fi
((EUID == 0)) || die 'A tényleges telepítést sudo-val indítsd.'
[[ -t 0 ]] || die 'Interaktív terminálból indítsd.'
collect_providers
if ((CHECK_PROVIDERS)); then
  command -v php >/dev/null || die 'Az ellenőrzéshez PHP CLI szükséges.'
  validate_providers
  echo "Providerellenőrzés kész: $PROVIDERS | alapértelmezett: $DEFAULT_PROVIDER. Nem történt módosítás."
  exit 0
fi
if [[ $LLAMA_ACTION == install ]]; then PACKAGES+=(build-essential cmake); fi
if [[ $OLLAMA_ACTION == install ]]; then PACKAGES+=(zstd); fi
read -r -p 'Első felhasználó neve: ' ADMIN_NAME
read -r -p 'Belépési e-mail: ' ADMIN_EMAIL
read -r -s -p 'Belépési jelszó (legalább 8 karakter): ' ADMIN_PASS; echo
read -r -s -p 'Jelszó ismét: ' ADMIN_CONFIRM; echo
[[ -n $ADMIN_NAME && ${#ADMIN_NAME} -le 32 ]] || die 'A név 1–32 karakter legyen.'
[[ $ADMIN_EMAIL == *@*.* && ${#ADMIN_EMAIL} -le 64 ]] || die 'Érvénytelen e-mail.'
[[ ${#ADMIN_PASS} -ge 8 && $ADMIN_PASS == "$ADMIN_CONFIRM" ]] || die 'Rövid vagy eltérő jelszó.'
unset ADMIN_CONFIRM
LOG="/var/log/$SITE-install-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1
trap 'echo "A telepítés megállt a(z) $LINENO. sorban. Napló: $LOG. A már létrehozott új fájlokat/adatbázist ellenőrizd újrafuttatás előtt."' ERR
apt-get update
apt-get install -y "${PACKAGES[@]}"
systemctl enable --now mariadb php8.4-fpm nginx
[[ -S /run/php/php8.4-fpm.sock ]] || die 'A PHP 8.4 FPM socket hiányzik.'
# Minden ütközést ellenőrzünk az alkalmazás másolása előtt.
[[ -z $(mariadb -N -e "SHOW DATABASES LIKE '$DB';") ]] || die 'Az adatbázis már létezik.'
[[ -z $(mariadb -N -e "SELECT User FROM mysql.user WHERE User='$DB';") ]] || die 'Az adatbázis-felhasználó neve már foglalt.'
nginx -t
validate_providers
install_providers
bash "$SOURCE/install/services.sh" www-data
install -d -m 0755 "$DEST"
tar -C "$SOURCE" --exclude=.git --exclude=config/config.php -cf - . | tar -C "$DEST" -xf -
printf '%s\0%s\0%s\0%s\0%s\0%s\0%s\0%s\0%s' "$ADMIN_NAME" "$ADMIN_EMAIL" "$ADMIN_PASS" "$PROVIDERS" "$DEFAULT_PROVIDER" "$OLLAMA_URL" "$LLAMA_URL" "$LLAMA_BINARY" "$LLAMA_MODELS" | php "$DEST/install/setup.php" "$DEST" "$DB"
unset ADMIN_PASS
chown -R root:www-data "$DEST"
find "$DEST" -type d -exec chmod 0755 {} +
find "$DEST" -type f -exec chmod 0644 {} +
chmod 0640 "$DEST/config/config.php"
install -d -o www-data -g www-data -m 0750 "$DEST/var/log" "$DEST/var/run"
install -d -o www-data -g www-data -m 0755 "$DEST/public/genimages"
chown -R www-data:www-data "$DEST/public/assets/img/models"
cat > "/etc/nginx/sites-available/$SITE" <<EOF
server {
    listen $PORT;
    listen [::]:$PORT;
    server_name _;
    root $DEST/public;
    index index.php;
    client_max_body_size 32m;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /index.php {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 630s;
    }
    location ~ \.php\$ { return 404; }
    location ~ /\. { deny all; }
}
EOF
ln -s "/etc/nginx/sites-available/$SITE" "/etc/nginx/sites-enabled/$SITE"
if ! nginx -t; then
  rm -- "/etc/nginx/sites-enabled/$SITE"
  die 'Az új Nginx-konfiguráció hibás; nem töltöttük be.'
fi
systemctl reload nginx
CODE=$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$PORT/auth/login")
[[ $CODE == 200 ]] || die "A belépési oldal ellenőrzése sikertelen: HTTP $CODE"
echo "Kész: http://$(hostname).local:$PORT/auth/login"
echo "Belépés: $ADMIN_EMAIL | napló: $LOG"
echo "Providerek: $PROVIDERS | alapértelmezett: $DEFAULT_PROVIDER"
echo 'A szolgáltatás telepítése nem tölt le modellt. Ollamához tölts le egyet; llama.cpp-hez helyezz GGUF fájlt a megadott modellmappába.'
