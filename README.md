# Mara Lab

**A local AI playground for roleplay, persistent character memory, and exploring model behavior.**

Mara Lab is an experimental environment for exploring how AI models respond when given distinct roles, traits, and a configurable **Psyché**—a character profile with personality, persistent memories, and a recorded reason for each revision. It was created to observe their behavior, reactions, and capabilities in depth, and to explore how these change with different configurations and interactions.

Roleplay is both a creative use case and a way to explore model behavior. Build characters, place them in different scenarios, and observe how their responses, role consistency, and persistent memories evolve across conversations.

Mara Lab is under active development. It supports exploratory observation and experimentation; it is not a validated scientific benchmark.

## What you can explore

- **Roles and personalities:** configure system prompts and Psyché profiles, with a history of character revisions.
- **Memory across conversations:** an enabled `update_memory` tool lets the model initiate updates to its current Psyché memory, preserving information it considers useful for future conversations. Memory can be reviewed in the character settings.
- **The model's view of the user:** the `rate_user` tool records model-generated assessments of engagement, trust, affinity, curiosity, frustration, and respect. These are outputs to study, not objective measurements of the person.
- **Image prompts as an observation record:** retain image-generation prompts for later examination of how the model translates conversation into visual instructions.
- **Generation settings and measurements:** adjust sampling and context settings, and inspect token counts, generation time, and speed.

The model can update its memory through an enabled tool. Its full personality profile remains editable through the interface; autonomous rewriting of the entire Psyché is not part of the current toolset.

## Features

### Model providers

Mara Lab currently supports **Ollama** and **llama.cpp**. Use one or both, choose a default provider, and create Mara characters around available base models. Further provider support is planned.

A Mara model configuration combines a base model with its prompts, character profile, generation settings, and enabled tools. Multiple characters can therefore explore different behavior using the same base model.

### Built-in and custom tools

| Tool | Purpose |
| --- | --- |
| `search_web` | Search through a configured web-search service. |
| `visit_webpage` | Retrieve web-page content for the model. |
| `generate_image` | Generate images through a configured image backend, retaining the prompts for later inspection. |
| `rate_user` | Record the model's assessment of the user's interaction. |
| `update_memory` | Update persistent memory for the current Psyché. |

Tools can be enabled per model. Custom tools can be added by implementing the [tool interface](core/tools/ToolInterface.php) and following the existing registration and execution conventions in [core/tools](core/tools). Tool use depends on the selected model's capabilities and configuration. Web search and image generation require separately configured services.

### RAG knowledge management

Create and edit knowledge sources, assign them to models, and configure retrieval similarity and result limits. Shared and personal knowledge sources allow characters to work with relevant reference material. Retrieval requires a configured embedding provider and model.

### Voice interaction

- **Text to speech:** integrations for XTTS, Piper, and eSpeak NG.
- **Speech to text:** browser speech recognition and a Whisper integration.

Voice features require the corresponding dependencies, services, and models where applicable. Browser microphone access also depends on browser support, permissions, and a suitable secure connection.

### Interface

English and Hungarian UI translations, character portraits, conversation management, and personal or public model configurations.

## Getting started

The current installer targets **Debian 13**, with **ARM64 or x86_64** and systemd. It installs the web application with nginx, PHP 8.4, and MariaDB, creates the database and initial administrator, and asks which model providers to configure.

```bash
git clone https://github.com/Kvasztics/mara-lab.git
cd mara-lab
sudo bash install/install.sh --dry-run
sudo bash install/install.sh
```

By default, the application is installed in `/var/www/mara-lab`, uses database `maralab`, and is served on port **8081**. After installation, open:

```text
http://YOUR_SERVER:8081/auth/login
```

Use the administrator credentials entered during installation. In Settings, review the provider configuration and select your UI language. Then create your first Mara model and character.

**Installing a backend does not download an AI model.** Existing Ollama servers must be running during setup. Existing llama.cpp installations require a local executable and a readable GGUF model directory. New llama.cpp installation currently builds a CPU backend.

See the [installation guide](install/README.md) for provider choices, alternate ports and paths, and troubleshooting. The current installer prompts are in Hungarian; an English installer is planned.

## Current status and next steps

A fresh Mara application and database have been tested on a Raspberry Pi 4 with an existing llama.cpp backend. The English interface has also been checked in the installed application. Installation of new Ollama and llama.cpp backends on a clean system still needs end-to-end validation.

Planned work includes:

- **Vision support:** bringing image-understanding functionality from the earlier Mara application into Mara Lab.
- **Video understanding:** exploring supported models for viewing and interpreting video content.
- Additional providers and tools.
- An English installer and broader installation testing.

Available capabilities depend on the chosen base model, hardware, and external services. The installer supports new installations; it does not upgrade an existing Mara installation.

## Development

Mara Lab uses PHP, JavaScript, and MariaDB. Application code lives in `core/`, database access in `database/`, templates in `tpl/`, and public assets in `public/`.

Runtime settings are stored in the database. The supported provider allowlist and database credentials are configured in `config/config.php`; [config/config_example.php](config/config_example.php) provides a template for manual configuration. The installer generates the real configuration automatically.

Ideas for experiments, new tools, and reproducible bug reports are welcome through [GitHub Issues](https://github.com/Kvasztics/mara-lab/issues). When reporting unexpected behavior, include the provider, base model, relevant settings, and reproduction steps.