#!/usr/bin/env bash
# Sourced by install.sh. No actions are performed when sourced.
choose() {
  local target=$1 prompt=$2 value
  while true; do
    read -r -p "$prompt" value || die 'Input cancelled.'
    case "$value" in 1|2) printf -v "$target" '%s' "$value"; return;; esac
    echo 'Enter 1 or 2.'
  done
}
ask() {
  local target=$1 prompt=$2 fallback=$3 value
  read -r -p "$prompt [$fallback]: " value || die 'Input cancelled.'
  printf -v "$target" '%s' "${value:-$fallback}"
}
valid_url() {
  php -r '$u=$argv[1]; $p=parse_url($u); exit(filter_var($u,FILTER_VALIDATE_URL) && in_array($p["scheme"] ?? "",["http","https"],true) && !isset($p["user"]) && !isset($p["pass"]) && !isset($p["query"]) && !isset($p["fragment"]) && (($p["path"] ?? "") === "" || $p["path"] === "/") ? 0 : 1);' -- "$1"
}
collect_providers() {
  local selection action
  OLLAMA_ACTION=none LLAMA_ACTION=none
  OLLAMA_URL='' LLAMA_URL='' LLAMA_BINARY='' LLAMA_MODELS=''
  echo 'Provider: 1) Ollama  2) llama.cpp  3) both'
  while true; do
    read -r -p 'Selection: ' selection || die 'Input cancelled.'
    case "$selection" in 1) PROVIDERS=ollama; DEFAULT_PROVIDER=ollama; break;;
      2) PROVIDERS=llamacpp; DEFAULT_PROVIDER=llamacpp; break;;
      3) PROVIDERS=ollama,llamacpp; break;; esac
    echo 'Enter 1, 2 or 3.'
  done
  if [[ $selection == 3 ]]; then
    choose action 'Default: 1) Ollama  2) llama.cpp: '
    if [[ $action == 1 ]]; then DEFAULT_PROVIDER=ollama; else DEFAULT_PROVIDER=llamacpp; fi
  fi
  if [[ ,$PROVIDERS, == *,ollama,* ]]; then
    choose action 'Ollama: 1) existing server  2) install on this machine: '
    if [[ $action == 1 ]]; then
      OLLAMA_ACTION=existing
      ask OLLAMA_URL 'Ollama base URL (without a path)' 'http://127.0.0.1:11434'
    else
      OLLAMA_ACTION=install OLLAMA_URL=http://127.0.0.1:11434
      command -v ollama >/dev/null && die 'Ollama is already installed; select the existing server.'
      [[ -z $(systemctl list-unit-files ollama.service --no-legend) ]] || die 'The Ollama service already exists; select the existing server.'
      [[ -z $(ss -H -ltn 'sport = :11434') ]] || die 'Ollama port 11434 is already in use.'
      ((PORT != 11434)) || die 'The web application port conflicts with Ollama.'
    fi
  fi
  if [[ ,$PROVIDERS, == *,llamacpp,* ]]; then
    echo 'Mara starts llama.cpp directly using local GGUF models.'
    choose action 'llama.cpp: 1) existing local installation  2) new CPU installation: '
    if [[ $action == 1 ]]; then
      LLAMA_ACTION=existing
      ask LLAMA_URL 'llama.cpp base URL (127.0.0.1 and an available port)' 'http://127.0.0.1:8082'
      ask LLAMA_BINARY 'Absolute path to llama-server' '/usr/local/bin/llama-server'
      ask LLAMA_MODELS 'GGUF model directory' '/var/lib/mara-lab/models'
    else
      LLAMA_ACTION=install
      LLAMA_BUILD="/opt/mara-llama-$PORT"
      LLAMA_BINARY="$LLAMA_BUILD/build/bin/llama-server"
      LLAMA_MODELS="/var/lib/mara-llama-$PORT/models"
      ask LLAMA_URL 'llama.cpp base URL (127.0.0.1 and an available port)' 'http://127.0.0.1:8082'
      [[ ! -e $LLAMA_BUILD && ! -L $LLAMA_BUILD && ! -e $(dirname "$LLAMA_MODELS") && ! -L $(dirname "$LLAMA_MODELS") ]] || die 'The llama.cpp destination directory already exists.'
      echo 'Compilation may take longer on smaller machines; two build threads are used. Add models separately.'
    fi
  fi
}
validate_providers() {
  if [[ $OLLAMA_ACTION != none ]]; then
    valid_url "$OLLAMA_URL" || die 'Invalid Ollama base URL.'
    OLLAMA_URL=${OLLAMA_URL%/}
    if [[ $OLLAMA_ACTION == existing ]]; then
      curl --fail --silent --show-error --connect-timeout 5 --max-time 15 "$OLLAMA_URL/api/tags" | php -r '$d=json_decode(stream_get_contents(STDIN),true); exit(is_array($d) && isset($d["models"]) && is_array($d["models"]) ? 0 : 1);' || die 'Ollama is unreachable or did not return a model list. Start it and try again.'
    fi
  fi
  if [[ $LLAMA_ACTION != none ]]; then
    valid_url "$LLAMA_URL" || die 'Invalid llama.cpp base URL.'
    LLAMA_URL=${LLAMA_URL%/}
    [[ $LLAMA_URL =~ ^http://127\.0\.0\.1:([0-9]{1,5})$ ]] || die 'The direct llama.cpp URL must use http://127.0.0.1:PORT.'
    LLAMA_PORT=$((10#${BASH_REMATCH[1]}))
    ((LLAMA_PORT >= 1024 && LLAMA_PORT <= 65535 && LLAMA_PORT != PORT)) || die 'The llama.cpp port is invalid or conflicts with the web application.'
    [[ $OLLAMA_ACTION == none || $LLAMA_PORT != 11434 ]] || die 'The llama.cpp port conflicts with Ollama.'
    [[ $LLAMA_BINARY == /* && $LLAMA_MODELS == /* ]] || die 'The llama.cpp paths must be absolute.'
    if [[ $LLAMA_ACTION == existing ]]; then
      runuser -u www-data -- test -x "$LLAMA_BINARY" || die "www-data cannot execute: $LLAMA_BINARY. Check parent directory permissions as well."
      runuser -u www-data -- test -r "$LLAMA_MODELS" && runuser -u www-data -- test -x "$LLAMA_MODELS" || die "www-data cannot read the model directory: $LLAMA_MODELS"
      local model count=0
      while IFS= read -r -d '' model; do
        runuser -u www-data -- test -r "$model" || die "www-data cannot read: $model"
        count=$((count + 1))
      done < <(find "$LLAMA_MODELS" -maxdepth 1 -name '*.gguf' -type f -print0)
      ((count > 0)) || echo 'The model directory is empty. Add a GGUF model after installation.'
    else
      [[ -z $(ss -H -ltn "sport = :$LLAMA_PORT") ]] || die 'The llama.cpp port is already in use.'
    fi
  fi
}
install_providers() {
  if [[ $OLLAMA_ACTION == install ]]; then
    local script
    script=$(mktemp)
    if ! curl --fail --show-error --location --proto '=https' --proto-redir '=https' https://ollama.com/install.sh -o "$script"; then
      rm -f -- "$script"; die 'Cannot download the Ollama installer.'
    fi
    if ! sh "$script"; then rm -f -- "$script"; die 'Ollama installation failed.'; fi
    rm -f -- "$script"
    systemctl enable --now ollama
    local ready=0 attempt
    for attempt in {1..30}; do
      if curl --fail --silent --connect-timeout 2 --max-time 3 "$OLLAMA_URL/api/tags" >/dev/null; then ready=1; break; fi
      sleep 1
    done
    ((ready)) || die 'The installed Ollama server is not responding.'
  fi
  if [[ $LLAMA_ACTION == install ]]; then
    git clone --depth 1 https://github.com/ggml-org/llama.cpp.git "$LLAMA_BUILD"
    git -C "$LLAMA_BUILD" rev-parse HEAD > "$LLAMA_BUILD/MARA_BUILD_COMMIT"
    cmake -S "$LLAMA_BUILD" -B "$LLAMA_BUILD/build" -DCMAKE_BUILD_TYPE=Release -DGGML_CUDA=OFF -DGGML_BLAS=OFF
    cmake --build "$LLAMA_BUILD/build" --config Release --target llama-server -j 2
    # Make directories and shared libraries accessible to www-data.
    find "$LLAMA_BUILD" -type d -exec chmod o+rx {} +
    find "$LLAMA_BUILD/build" -type f -exec chmod o+r {} +
    chmod 0755 "$LLAMA_BINARY"
    install -d -m 0755 "$(dirname "$LLAMA_MODELS")" "$LLAMA_MODELS"
    runuser -u www-data -- "$LLAMA_BINARY" --version
  fi
}
