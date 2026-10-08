2026-10-05: Added admin-only Settings Logs tab using current uploaded settings/main/scripts/syntax files. Added bounded LogViewer and main/logread endpoint. Added exclusive blue switches, refresh, auto refresh and copy. Added safe log category to existing syntax highlighter, black 420px viewer. No DB or provider lifecycle changes. PHP/JS validation and reader/highlighter tests passed; local browser test pending.

## 2026-10-06 — MaraImg kezelőfelület
- Lépésszám- és CFG-csúszkák élő értékkijelzéssel.
- Fix képméretek; Forge-nál 1, 2, 4 vagy 6 kép kérhető.
- Több kép mentése és megjelenítése; hat bélyegkép fér el egy sorban.
- Valódi százalékos folyamatjelző Forge és Qwen alatt.
- Forge-generálás megszakítható; Qwennél a gomb a befejezésig letiltva.
- Közös feladatzár a chat tool és a MaraImg számára; megszakítás előtt felhasználó- és feladatazonosító-ellenőrzés.
- Új feliratok magyarul és angolul.
- Ellenőrizve: PHP-szintaxis, Forge kétképes generálás és megszakítás, Qwen folyamatjelzés és gomb-visszaállítás.
- Következő: Forge képből kép mód és felskálázás.

## 2026-10-06 — MaraImg galéria és Forge képműveletek
- Galériaképek törlése piros × gombbal, közös megerősítő modal használatával.
- Törléskor CSRF-ellenőrzés és saját felhasználói mappára korlátozott fájlkezelés.
- Közös modal-template és CSS: tpl/modals.tpl.php, public/assets/css/modals.css.
- Forge képből kép kapcsoló és denoising strength csúszka.
- Képbetöltés a nagy képmezőre kattintva vagy a galériából.
- Közös bemeneti képellenőrzés: PNG/JPEG/WebP, legfeljebb 10 MB, 8192 pixel oldalhossz és 32 megapixel.
- Forge felskálázó-választó és 2×/4× nagyítás; az eredmény új galériaképként mentődik.
- Kép nélküli képből kép generálás és felskálázás nem indítható.
- Ellenőrizve: törlés megerősítése és megszakítása, képből kép feltöltött és galériaképpel, Lanczos 2× felskálázás.
- Az AI-felskálázók külön tesztelése még hátravan.


### 2026-10-07 — Qwen editing integration and request timeouts
- Added Qwen reference-image editing through SDAPI txt2img with extra_images and resize_before_vae=false.
- Initial editing sizes: 992x992 and 1152x864, tested directly against the backend with the INT8 text encoder.
- Browser prepares the reference with proportional center cropping; editing defaults to 40 steps. Forge retains its denoising control.
- First MaraImg edit completed on the backend in 547.56 seconds, but Nginx terminated the web request after 300 seconds.
- Updated the local MaraLab Nginx fastcgi_read_timeout to 630 seconds and the installer template to 630s.
- Image API cURL timeout: 600 seconds; PHP generation/upscale time limit: 630 seconds.
- Existing deployments must update their MaraLab Nginx PHP location, run nginx -t, then reload Nginx.
- PHP-FPM request_terminate_timeout must be disabled or exceed the application request duration.
- Concurrent llama-server VRAM use caused prefix-cache allocation failure; Qwen retried without caching.
- MaraImg end-to-end editing verification is still pending after the timeout fix.

### 2026-10-07 — MaraImg Qwen editing verified
- Confirmed successful reference-image editing and gallery storage from MaraImg.
- Confirmed successful web operation after increasing the local Nginx timeout.
- Necklace edit at 20 steps: CFG 1 completed in 82 seconds; CFG 6 in 282 seconds.
- Both preserved the subject; CFG 6 produced stronger contrast and larger, brighter beads.
- Further brightness/contrast editing tested successfully by the user.
- Current editing sizes remain 992x992 and 1152x864; references use proportional center cropping.
- The UI selects 40 steps when Qwen editing is enabled; the user can adjust this.


### 2026-10-08 — MaraImg login return verified
- Unauthenticated image page and image AJAX requests store /image as the login return destination.
- Successful login uses an allowlisted return path.
- MaraImg sends the AJAX header and redirects HTTP 401 responses to login without displaying a generation error.
- Verified by logging out in another tab, refreshing the gallery, and logging back in: returned to MaraImg.
- Removed automatic switching to the generation tab when selecting a gallery image; verification pending.

### 2026-10-08 — MaraImg image details
- Verified gallery image selection stays on the gallery tab.
- Added Hungarian and English labels for image metadata and prompt reuse; implementation in progress.

### 2026-10-08 — MaraImg image metadata verified
- Added an authenticated metadata endpoint restricted to the user's own gallery images.
- Added a bounded PNG metadata reader supporting tEXt, zTXt and iTXt.
- Gallery info buttons open a dialog using the shared modal styles and HU/EN labels.
- Prompts and generation settings are displayed as text.
- “Use prompt” restores positive and negative prompts and opens the generation tab.
- Existing generation settings and image files remain unchanged.
- PHP syntax checks passed; metadata reading, dialog display and prompt reuse verified.

### 2026-10-08 — README feature updates
- Documented the framework-free PHP/JavaScript implementation.
- Added update_emotional_state to the tool list.
- Added MaraImg capabilities and separate backend requirements.
- Image search remains planned: the current search_web tool returns web results only.

### 2026-10-08 — English installer
- Translated install.sh and providers.sh prompts, messages and comments into English.
- Translated the setup.php completion message; services.sh was already English.
- Updated README installer language and planned work.
- Individual Bash syntax checks and setup.php PHP syntax check passed.
- Translation changes have not yet been tested with a fresh installation.

### 2026-10-08 — Image search verification
- Direct SearXNG test returned five image results.
- Excluded devicons and lucide icon engines.
- Fixed missing STATUS_SEARCHIMAGES labels in both language files; missing labels had prevented tool execution.
- Chat test with Aslaug successfully returned an organized image-link and source list.
- Added search_images to the README tool list.
- An unexpected workstation restart was investigated separately: boot logs contained CPU Machine Check hardware errors; cause remains unresolved.

### 2026-10-08 — Character card storage preparation
- Added nullable models.card_data to the installation schema.
- Added migration 20261008_character_cards.sql for existing installations.
- The migration has not yet been applied to the running database.

### 2026-10-08 — Character card database storage
- Applied the card_data migration to the local database via phpMyAdmin.
- Model insert/update now support card_data; ordinary edits preserve existing card data.
- PHP syntax check passed; card import/export is still being implemented.

### 2026-10-08 — Character card JSON codec
- Added bounded V2/V3 JSON decoding and encoding.
- Preserves unknown fields and JSON objects, including extensions.
- Validates required field types and card versions.
- PHP syntax check passed; functional verification follows.

### 2026-10-08 — Character card PNG codec
- Added PNG card reading/writing using chara (V2) and ccv3 (V3).
- Prefers V3 when both chunks exist; checks sizes, dimensions and CRCs.
- Export removes unrelated text metadata while preserving image chunks.
- JSON round-trip checks passed; PNG functional verification follows.

### 2026-10-08 — Character card field mapping
- Added Tavern-to-Mara character text and system prompt mapping.
- Preserves original split fields when character text remains unchanged.
- Exports portable generation parameters and optional Psyché memory.
- Imported cards do not enable tools or select machine-specific model paths.
- PNG round-trip and corrupt-file rejection checks passed.
- Greeting, lorebook and post-history runtime integration is not implemented yet.

### 2026-10-08 — Character card model save support
- Models::save accepts card JSON as a separate argument for new models.
- Existing model edits retain stored card data.
- Character mapping and optional-memory round-trip checks passed.
- PHP syntax check passed.

### 2026-10-08 — Character card controller preparation
- Added CSRF-protected upload staging and a one-hour, session-owned import preview.
- Added access-checked JSON/PNG downloads with memory excluded by default.
- Imported portraits strip text/card metadata before public storage.
- Imported model and Psyché saves use one database transaction.
- Added HU/EN labels and validated both INI files.
- PHP syntax checks passed; controller/UI wiring and integration tests remain.

### 2026-10-08 — Character card route wiring
- Connected import/export methods through the CharacterCards controller trait.
- New-model editor loads session-owned import previews and shows the compatibility notice.
- Model saves now use the import-aware transaction helper.
- PHP syntax checks passed; management-page buttons follow.

### 2026-10-08 — Character card management UI
- Added Import beside New model and Export beside each model delete button.
- Added shared-style import/export dialogs with PNG/JSON selection and optional memory.
- PHP template syntax checks passed; browser integration testing follows.

### 2026-10-08 — Character download output isolation
- Browser JSON export contained leading whitespace and a truncated ending.
- Export now clears output buffers before download headers and exits after the body.
- PHP syntax check passed; download/reimport verification follows.

### 2026-10-08 — Import editor form fix
- Moved import preview fields outside the form action attribute.
- Restored the model save URL; PHP syntax check passed.

### 2026-10-08 — Model management scrolling
- Wrapped the model management page in the existing scrollable app-content container.
- Browser JSON import and model/Psyché save checks passed.
- PHP template syntax check passed.

### 2026-10-08 — Character card dialog behavior
- Export closes its dialog after receiving the download response.
- Import closes on success and navigates to the editor; failures remain in the dialog.
- Added inline translated errors, duplicate-submit prevention and expired-login handling.
- JSON/PNG browser import and optional-memory checks passed.
- PHP syntax checks passed; updated dialog behavior needs browser verification.

### 2026-10-08 — Character greeting persistence
- Added atomic system + assistant greeting conversation creation without a fake user turn.
- Chat/message INSERT helpers now return zero on execution failure.
- Existing chat list supports greeting-only conversations.
- PHP syntax check passed; greeting/context wiring follows.

### 2026-10-08 — Separate roleplay context fields
- Added character-name resolution and first-message extraction.
- Dialogue examples remain in card data, separately from the editable Psyché.
- Runtime examples are explicitly labeled as illustrative, not real conversation history.
- Recognizes the previous imported example suffix without rewriting stored cards.
- PHP syntax checks passed; chat wiring follows.

### 2026-10-08 — Character greeting and runtime context
- Added explicit chat start with a persisted, name-resolved character greeting.
- Characters without a greeting retain the existing empty-chat behavior.
- Runtime system context resolves roleplay names and includes separate illustrative examples.
- Saved system context excludes dialogue examples; existing imported suffixes are recognized.
- PHP syntax check passed; AJAX/UI wiring follows.

### 2026-10-08 — Greeting chat UI
- New-chat AJAX returns persisted greeting HTML and updated chat titles.
- Added duplicate-click prevention and AJAX authentication handling.
- Prevented the sidebar new-chat link from also reloading the page.
- PHP syntax checks passed; browser greeting/send checks follow.

### 2026-10-08 — Legacy Tavern V1 import
- Added V1 JSON/PNG import with automatic conversion to V2.
- Preserves legacy extra fields under extensions.mara_lab_legacy_v1.
- V1 conversion, extra-field round-trip, PNG import and invalid-card checks passed.
- Updated HU/EN supported-format messages.
- Character greeting displayed successfully; browser V1 verification follows.

### 2026-10-08 — Character card README documentation
- Documented V1/V2/V3 JSON/PNG import, export, optional memory and portable parameters.
- Documented roleplay placeholders, persisted opening messages and separate dialogue examples.
- Explicitly listed preserved but inactive fields and unsupported CHARX/assets.
- Added reply regeneration, turn deletion and DRY controls to planned work.
- Browser legacy V1 import verified successfully.

## 2026-10-08 — Noncommercial license

- Added the official PolyForm Noncommercial 1.0.0 license and copyright notice.
- Documented noncommercial use and separate permission for commercial use.
- Clarified that third-party components retain their own licenses.

## 2026-10-08 — DRY sampling: parameter storage

- Added bounded per-character DRY settings, disabled by default.
- Included DRY settings in the Mara character-card extension.
- Removed DRY options from requests to Ollama.
- Confirmed DRY support and sampler ordering on the local llama.cpp server.
- Added four DRY controls with Hungarian and English help text.
- Changed the DRY token window to a 0–8192 slider with a full-context switch (-1).

## 2026-10-08 — Conversation turn actions

- Added owned-conversation turn lookup and transactional pair deletion.
- Added replacement of the latest assistant response without duplicating the user message.
- Reject changes if the conversation changed while regeneration was running.
- Keep the previous response until a successful replacement is committed.
- Reused the chat generation pipeline for regeneration without inserting a user message.
- Added active-conversation ownership checks and localized action messages.
- Added CSRF-protected POST endpoints and reply action icons.
- Reused chat busy state and the common confirmation modal for deletion.
- Updated README with completed reply regeneration, pair deletion and llama.cpp DRY controls.
- Corrected README: vision chat is available; video understanding remains planned.
