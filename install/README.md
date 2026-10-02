# Installing Mara Lab

The installer is an early test version for **Debian 13**, **ARM64 or x86_64**, and **systemd**. It creates a new Mara Lab installation from the source directory containing the script.

## Quick start

From the repository root:

```bash
sudo bash install/install.sh --dry-run
sudo bash install/install.sh
```

`--dry-run` checks the platform, target conflicts, and base package status without changing the system. It does not test provider connectivity.

During installation, choose **Ollama**, **llama.cpp**, or **both**. For each provider, choose an existing installation or a new local installation. If using both, choose the default. Then enter the first administrator's name, email, and password.

The installer installs web dependencies, imports the database schema, saves the selected provider settings, generates `config/config.php`, configures nginx, and checks the login page.

| Default | Value |
| --- | --- |
| Application directory | `/var/www/mara-lab` |
| Web port | `8081` |
| Database and database user | `maralab` |
| Login page | `http://YOUR_SERVER:8081/auth/login` |

The database password is generated automatically. Use the administrator email and password entered during setup to log in.

## Provider choices

- **Existing Ollama:** enter the base URL, for example `http://127.0.0.1:11434`. The server must be reachable and respond to `/api/tags`. A remote Ollama server can also be used.
- **New Ollama:** runs the official Linux installer and enables `ollama.service`. Existing Ollama installations are not overwritten.
- **Existing llama.cpp:** enter a local `llama-server` executable, a GGUF model directory, and a URL such as `http://127.0.0.1:8082`. The web user, `www-data`, must be able to execute the binary and read the model directory and files, including access through their parent directories.
- **New llama.cpp:** builds a CPU `llama-server` with CMake using two build jobs. Sources and build output go under `/opt/mara-llama-WEBPORT`; GGUF files belong under `/var/lib/mara-llama-WEBPORT/models`. Mara starts the server when a model is selected.

**Backend installation does not download models.** Download an Ollama model separately, or place GGUF files in the configured llama.cpp directory. GPU setup is a separate task.

The web port and llama.cpp port must differ: for example, Mara on **8081** and llama.cpp on **8082**.

## Checks and separate test installations

On a system with PHP CLI and the existing backend dependencies installed, check provider settings without creating an application or database:

```bash
sudo bash install/install.sh --check-providers
```

To create a separate test application:

```bash
sudo bash install/install.sh \
  --dir /var/www/mara-lab-test \
  --database maralab_test \
  --port 8083
```

This uses a new application directory, database, and web port. System packages and any selected existing model backend are shared with other applications on the machine.

Optional `--with-audio-tools` installs ffmpeg and eSpeak NG. Other speech services and models need separate setup.

## Status and troubleshooting

Fresh application/database installation with an existing llama.cpp backend has been tested on a Raspberry Pi 4. New backend installation on a clean system remains to be validated. Installer prompts are currently in Hungarian; an English version is planned.

The installer refuses conflicting targets and does not upgrade existing installations. Logs are written to `/var/log/mara-lab-WEBPORT-install-TIMESTAMP.log`. Failed installations can leave packages, backend files, an application directory, or a partially created database behind; inspect the log and created resources before retrying. There is no complete automatic rollback.

For manual configuration, see [config/config_example.php](../config/config_example.php). Keep the real `config.php`, which contains database credentials, out of Git.