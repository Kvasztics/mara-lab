#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

die() { echo "ERROR: $*" >&2; exit 1; }

((EUID == 0)) || die 'Run this installer as root.'
[[ $# -le 1 ]] || die 'Usage: services.sh [WEB_USER]'
WEB_USER=${1:-www-data}
[[ $WEB_USER =~ ^[a-z_][a-z0-9_-]*$ ]] || die 'Invalid web user.'
id "$WEB_USER" >/dev/null 2>&1 || die 'Web user does not exist.'

SOURCE_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
HELPER_SOURCE="$SOURCE_DIR/mara-service"
HELPER_TARGET=/usr/local/bin/mara-service
SUDOERS_TARGET=/etc/sudoers.d/mara

[[ -f $HELPER_SOURCE ]] || die 'Service helper source is missing.'
[[ -x /usr/bin/systemctl ]] || die 'systemctl is missing.'
command -v sudo >/dev/null || die 'Install sudo first.'
VISUDO=$(command -v visudo) || die 'visudo is missing.'
/bin/bash -n "$HELPER_SOURCE"
"$VISUDO" -c >/dev/null

for target in "$HELPER_TARGET" "$SUDOERS_TARGET"; do
    [[ ! -L $target ]] || die "Refusing symbolic link: $target"
    [[ ! -e $target || -f $target ]] || die "Not a regular file: $target"
done

RULE="$WEB_USER ALL=(root) NOPASSWD: $HELPER_TARGET *"

if [[ -f $SUDOERS_TARGET ]]; then
    EXISTING=$(sed '/^[[:space:]]*#/d; /^[[:space:]]*$/d' "$SUDOERS_TARGET")
    [[ $EXISTING == "$RULE" ]] ||
        die 'Existing sudoers.d/mara contains different rules; review it before replacing.'
fi

STAGED_RULE=$(mktemp)
trap 'rm -f -- "$STAGED_RULE"' EXIT
printf '%s\n' "$RULE" > "$STAGED_RULE"
chmod 0440 "$STAGED_RULE"
"$VISUDO" -cf "$STAGED_RULE" >/dev/null

BACKUP_DIR=$(mktemp -d /var/backups/mara-services-XXXXXXXX)
chmod 0700 "$BACKUP_DIR"

[[ ! -f $HELPER_TARGET ]] ||
    cp -a -- "$HELPER_TARGET" "$BACKUP_DIR/mara-service"
[[ ! -f $SUDOERS_TARGET ]] ||
    cp -a -- "$SUDOERS_TARGET" "$BACKUP_DIR/sudoers-mara"

install -d -o root -g root -m 0755 /usr/local/bin
install -d -o root -g root -m 0750 /etc/sudoers.d
install -o root -g root -m 0755 "$HELPER_SOURCE" "$HELPER_TARGET"
install -o root -g root -m 0440 "$STAGED_RULE" "$SUDOERS_TARGET"

if ! "$VISUDO" -c; then
    if [[ -f $BACKUP_DIR/sudoers-mara ]]; then
        cp -a -- "$BACKUP_DIR/sudoers-mara" "$SUDOERS_TARGET"
    else
        rm -f -- "$SUDOERS_TARGET"
    fi
    if [[ -f $BACKUP_DIR/mara-service ]]; then
        cp -a -- "$BACKUP_DIR/mara-service" "$HELPER_TARGET"
    else
        rm -f -- "$HELPER_TARGET"
    fi
    die "Validation failed; previous files restored. Backup: $BACKUP_DIR"
fi

echo "Service helper installed for $WEB_USER."
echo "Backup: $BACKUP_DIR"
echo 'No services were started, stopped or enabled.'
