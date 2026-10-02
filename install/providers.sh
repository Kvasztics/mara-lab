#!/usr/bin/env bash
# Sourced by install.sh. No actions are performed when sourced.
choose() {
  local target=$1 prompt=$2 value
  while true; do
    read -r -p "$prompt" value || die 'Megszakított adatbevitel.'
    case "$value" in 1|2) printf -v "$target" '%s' "$value"; return;; esac
    echo '1 vagy 2 legyen.'
  done
}
ask() {
  local target=$1 prompt=$2 fallback=$3 value
  read -r -p "$prompt [$fallback]: " value || die 'Megszakított adatbevitel.'
  printf -v "$target" '%s' "${value:-$fallback}"
}
valid_url() {
  php -r '$u=$argv[1]; $p=parse_url($u); exit(filter_var($u,FILTER_VALIDATE_URL) && in_array($p["scheme"] ?? "",["http","https"],true) && !isset($p["user"]) && !isset($p["pass"]) && !isset($p["query"]) && !isset($p["fragment"]) && (($p["path"] ?? "") === "" || $p["path"] === "/") ? 0 : 1);' -- "$1"
}
collect_providers() {
  local selection action
  OLLAMA_ACTION=none LLAMA_ACTION=none
  OLLAMA_URL='' LLAMA_URL='' LLAMA_BINARY='' LLAMA_MODELS=''
  echo 'Provider: 1) Ollama  2) llama.cpp  3) mindkettő'
  while true; do
    read -r -p 'Választás: ' selection || die 'Megszakított adatbevitel.'
    case "$selection" in 1) PROVIDERS=ollama; DEFAULT_PROVIDER=ollama; break;;
      2) PROVIDERS=llamacpp; DEFAULT_PROVIDER=llamacpp; break;;
      3) PROVIDERS=ollama,llamacpp; break;; esac
    echo '1, 2 vagy 3 legyen.'
  done
  if [[ $selection == 3 ]]; then
    choose action 'Alapértelmezett: 1) Ollama  2) llama.cpp: '
    if [[ $action == 1 ]]; then DEFAULT_PROVIDER=ollama; else DEFAULT_PROVIDER=llamacpp; fi
  fi
  if [[ ,$PROVIDERS, == *,ollama,* ]]; then
    choose action 'Ollama: 1) meglévő szerver  2) telepítés erre a gépre: '
    if [[ $action == 1 ]]; then
      OLLAMA_ACTION=existing
      ask OLLAMA_URL 'Ollama alap URL (útvonal nélkül)' 'http://127.0.0.1:11434'
    else
      OLLAMA_ACTION=install OLLAMA_URL=http://127.0.0.1:11434
      command -v ollama >/dev/null && die 'Ollama már telepítve van; válaszd a meglévő szervert.'
      [[ -z $(systemctl list-unit-files ollama.service --no-legend) ]] || die 'Ollama service már létezik; válaszd a meglévő szervert.'
      [[ -z $(ss -H -ltn 'sport = :11434') ]] || die 'Az Ollama 11434-es portja foglalt.'
      ((PORT != 11434)) || die 'A weboldal portja ütközik az Ollamával.'
    fi
  fi
  if [[ ,$PROVIDERS, == *,llamacpp,* ]]; then
    echo 'A llama.cpp-t a Mara közvetlenül indítja a helyi GGUF modellekkel.'
    choose action 'llama.cpp: 1) meglévő helyi telepítés  2) új CPU-telepítés: '
    if [[ $action == 1 ]]; then
      LLAMA_ACTION=existing
      ask LLAMA_URL 'llama.cpp alap URL (127.0.0.1 és szabad port)' 'http://127.0.0.1:8082'
      ask LLAMA_BINARY 'llama-server teljes útvonala' '/usr/local/bin/llama-server'
      ask LLAMA_MODELS 'GGUF modellek mappája' '/var/lib/mara-lab/models'
    else
      LLAMA_ACTION=install
      LLAMA_BUILD="/opt/mara-llama-$PORT"
      LLAMA_BINARY="$LLAMA_BUILD/build/bin/llama-server"
      LLAMA_MODELS="/var/lib/mara-llama-$PORT/models"
      ask LLAMA_URL 'llama.cpp alap URL (127.0.0.1 és szabad port)' 'http://127.0.0.1:8082'
      [[ ! -e $LLAMA_BUILD && ! -L $LLAMA_BUILD && ! -e $(dirname "$LLAMA_MODELS") && ! -L $(dirname "$LLAMA_MODELS") ]] || die 'A llama.cpp célmappája már létezik.'
      echo 'A fordítás kis gépen hosszabb lehet; két fordítószálat használunk. Modellt külön kell hozzáadni.'
    fi
  fi
}
validate_providers() {
  if [[ $OLLAMA_ACTION != none ]]; then
    valid_url "$OLLAMA_URL" || die 'Érvénytelen Ollama alap URL.'
    OLLAMA_URL=${OLLAMA_URL%/}
    if [[ $OLLAMA_ACTION == existing ]]; then
      curl --fail --silent --show-error --connect-timeout 5 --max-time 15 "$OLLAMA_URL/api/tags" | php -r '$d=json_decode(stream_get_contents(STDIN),true); exit(is_array($d) && isset($d["models"]) && is_array($d["models"]) ? 0 : 1);' || die 'Az Ollama nem elérhető, vagy nem ad modell-listát. Indítsd el, majd próbáld újra.'
    fi
  fi
  if [[ $LLAMA_ACTION != none ]]; then
    valid_url "$LLAMA_URL" || die 'Érvénytelen llama.cpp alap URL.'
    LLAMA_URL=${LLAMA_URL%/}
    [[ $LLAMA_URL =~ ^http://127\.0\.0\.1:([0-9]{1,5})$ ]] || die 'A közvetlen llama.cpp cím http://127.0.0.1:PORT legyen.'
    LLAMA_PORT=$((10#${BASH_REMATCH[1]}))
    ((LLAMA_PORT >= 1024 && LLAMA_PORT <= 65535 && LLAMA_PORT != PORT)) || die 'Érvénytelen vagy a weboldallal ütköző llama.cpp port.'
    [[ $OLLAMA_ACTION == none || $LLAMA_PORT != 11434 ]] || die 'A llama.cpp portja ütközik az Ollamával.'
    [[ $LLAMA_BINARY == /* && $LLAMA_MODELS == /* ]] || die 'A llama.cpp útvonalak abszolútak legyenek.'
    if [[ $LLAMA_ACTION == existing ]]; then
      runuser -u www-data -- test -x "$LLAMA_BINARY" || die "A www-data nem tudja futtatni: $LLAMA_BINARY. Ellenőrizd a szülőmappák jogosultságát is."
      runuser -u www-data -- test -r "$LLAMA_MODELS" && runuser -u www-data -- test -x "$LLAMA_MODELS" || die "A www-data nem tudja olvasni a modellmappát: $LLAMA_MODELS"
      local model count=0
      while IFS= read -r -d '' model; do
        runuser -u www-data -- test -r "$model" || die "A www-data nem tudja olvasni: $model"
        count=$((count + 1))
      done < <(find "$LLAMA_MODELS" -maxdepth 1 -name '*.gguf' -type f -print0)
      ((count > 0)) || echo 'A modellmappa üres. Telepítés után adj hozzá GGUF modellt.'
    else
      [[ -z $(ss -H -ltn "sport = :$LLAMA_PORT") ]] || die 'A llama.cpp portja foglalt.'
    fi
  fi
}
install_providers() {
  if [[ $OLLAMA_ACTION == install ]]; then
    local script
    script=$(mktemp)
    if ! curl --fail --show-error --location --proto '=https' --proto-redir '=https' https://ollama.com/install.sh -o "$script"; then
      rm -f -- "$script"; die 'Az Ollama telepítő nem tölthető le.'
    fi
    if ! sh "$script"; then rm -f -- "$script"; die 'Az Ollama telepítése sikertelen.'; fi
    rm -f -- "$script"
    systemctl enable --now ollama
    local ready=0 attempt
    for attempt in {1..30}; do
      if curl --fail --silent --connect-timeout 2 --max-time 3 "$OLLAMA_URL/api/tags" >/dev/null; then ready=1; break; fi
      sleep 1
    done
    ((ready)) || die 'A telepített Ollama nem válaszol.'
  fi
  if [[ $LLAMA_ACTION == install ]]; then
    git clone --depth 1 https://github.com/ggml-org/llama.cpp.git "$LLAMA_BUILD"
    git -C "$LLAMA_BUILD" rev-parse HEAD > "$LLAMA_BUILD/MARA_BUILD_COMMIT"
    cmake -S "$LLAMA_BUILD" -B "$LLAMA_BUILD/build" -DCMAKE_BUILD_TYPE=Release -DGGML_CUDA=OFF -DGGML_BLAS=OFF
    cmake --build "$LLAMA_BUILD/build" --config Release --target llama-server -j 2
    # A könyvtárak és a dinamikus függőségek is elérhetők a www-data számára.
    find "$LLAMA_BUILD" -type d -exec chmod o+rx {} +
    find "$LLAMA_BUILD/build" -type f -exec chmod o+r {} +
    chmod 0755 "$LLAMA_BINARY"
    install -d -m 0755 "$(dirname "$LLAMA_MODELS")" "$LLAMA_MODELS"
    runuser -u www-data -- "$LLAMA_BINARY" --version
  fi
}
