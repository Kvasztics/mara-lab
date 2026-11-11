# Mara Lab project log

## 2026-10-05 — Settings log viewer

Added admin-only Settings Logs tab using current uploaded settings/main/scripts/syntax files. Added bounded LogViewer and main/logread endpoint. Added exclusive blue switches, refresh, auto refresh and copy. Added safe log category to existing syntax highlighter, black 420px viewer. No DB or provider lifecycle changes. PHP/JS validation and reader/highlighter tests passed; local browser test pending.

## 2026-10-06 — MaraImg interface
- Added step count and CFG sliders with live value displays.
- Added fixed image sizes. Forge can generate 1, 2, 4 or 6 images.
- Added saving and display for multiple images, with six thumbnails per row.
- Added percentage progress for Forge and Qwen.
- Forge jobs can be canceled. The cancel button stays disabled for Qwen until completion.
- Added a shared job lock for the chat tool and MaraImg, with user and job ID checks before cancellation.
- Added Hungarian and English labels.
- Verified PHP syntax, two-image Forge generation and cancellation, and Qwen progress and button reset.
- Next at the time: Forge image-to-image and upscaling.

## 2026-10-06 — MaraImg gallery and Forge image operations
- Added gallery deletion via a red × button and the shared confirmation dialog.
- Deletion checks CSRF and restricts file operations to the user's own directory.
- Shared dialog files: `tpl/modals.tpl.php` and `public/assets/css/modals.css`.
- Added a Forge image-to-image toggle and denoising strength slider.
- Input images can be selected through the large image area or from the gallery.
- Common image validation allows PNG, JPEG and WebP up to 10 MB, 8192 pixels per side and 32 megapixels.
- Added a Forge upscaler selector with 2× and 4× options. The result is saved as a new gallery image.
- Image-to-image generation and upscaling require an input image.
- Verified deletion confirmation and cancellation, image-to-image with uploads and gallery images, and Lanczos 2× upscaling.
- AI upscaler tests were still pending.

## 2026-10-07 — Qwen editing integration and request timeouts
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

## 2026-10-07 — MaraImg Qwen editing verified
- Confirmed successful reference-image editing and gallery storage from MaraImg.
- Confirmed successful web operation after increasing the local Nginx timeout.
- Necklace edit at 20 steps: CFG 1 completed in 82 seconds; CFG 6 in 282 seconds.
- Both preserved the subject; CFG 6 produced stronger contrast and larger, brighter beads.
- Further brightness/contrast editing tested successfully by the user.
- Current editing sizes remain 992x992 and 1152x864; references use proportional center cropping.
- The UI selects 40 steps when Qwen editing is enabled; the user can adjust this.


## 2026-10-08 — MaraImg login return verified
- Unauthenticated image page and image AJAX requests store /image as the login return destination.
- Successful login uses an allowlisted return path.
- MaraImg sends the AJAX header and redirects HTTP 401 responses to login without displaying a generation error.
- Verified by logging out in another tab, refreshing the gallery, and logging back in: returned to MaraImg.
- Removed automatic switching to the generation tab when selecting a gallery image; verification pending.

## 2026-10-08 — MaraImg image details
- Verified gallery image selection stays on the gallery tab.
- Added Hungarian and English labels for image metadata and prompt reuse; implementation in progress.

## 2026-10-08 — MaraImg image metadata verified
- Added an authenticated metadata endpoint restricted to the user's own gallery images.
- Added a bounded PNG metadata reader supporting tEXt, zTXt and iTXt.
- Gallery info buttons open a dialog using the shared modal styles and HU/EN labels.
- Prompts and generation settings are displayed as text.
- “Use prompt” restores positive and negative prompts and opens the generation tab.
- Existing generation settings and image files remain unchanged.
- PHP syntax checks passed; metadata reading, dialog display and prompt reuse verified.

## 2026-10-08 — README feature updates
- Documented the framework-free PHP/JavaScript implementation.
- Added update_emotional_state to the tool list.
- Added MaraImg capabilities and separate backend requirements.
- Image search remains planned: the current search_web tool returns web results only.

## 2026-10-08 — English installer
- Translated install.sh and providers.sh prompts, messages and comments into English.
- Translated the setup.php completion message; services.sh was already English.
- Updated README installer language and planned work.
- Individual Bash syntax checks and setup.php PHP syntax check passed.
- Translation changes have not yet been tested with a fresh installation.

## 2026-10-08 — Image search verification
- Direct SearXNG test returned five image results.
- Excluded devicons and lucide icon engines.
- Fixed missing STATUS_SEARCHIMAGES labels in both language files; missing labels had prevented tool execution.
- Chat test with Aslaug successfully returned an organized image-link and source list.
- Added search_images to the README tool list.
- An unexpected workstation restart was investigated separately: boot logs contained CPU Machine Check hardware errors; cause remains unresolved.

## 2026-10-08 — Character card storage preparation
- Added nullable models.card_data to the installation schema.
- Added migration 20261008_character_cards.sql for existing installations.
- The migration has not yet been applied to the running database.

## 2026-10-08 — Character card database storage
- Applied the card_data migration to the local database via phpMyAdmin.
- Model insert/update now support card_data; ordinary edits preserve existing card data.
- PHP syntax check passed; card import/export is still being implemented.

## 2026-10-08 — Character card JSON codec
- Added bounded V2/V3 JSON decoding and encoding.
- Preserves unknown fields and JSON objects, including extensions.
- Validates required field types and card versions.
- PHP syntax check passed; functional verification follows.

## 2026-10-08 — Character card PNG codec
- Added PNG card reading/writing using chara (V2) and ccv3 (V3).
- Prefers V3 when both chunks exist; checks sizes, dimensions and CRCs.
- Export removes unrelated text metadata while preserving image chunks.
- JSON round-trip checks passed; PNG functional verification follows.

## 2026-10-08 — Character card field mapping
- Added Tavern-to-Mara character text and system prompt mapping.
- Preserves original split fields when character text remains unchanged.
- Exports portable generation parameters and optional Psyché memory.
- Imported cards do not enable tools or select machine-specific model paths.
- PNG round-trip and corrupt-file rejection checks passed.
- Greeting, lorebook and post-history runtime integration is not implemented yet.

## 2026-10-08 — Character card model save support
- Models::save accepts card JSON as a separate argument for new models.
- Existing model edits retain stored card data.
- Character mapping and optional-memory round-trip checks passed.
- PHP syntax check passed.

## 2026-10-08 — Character card controller preparation
- Added CSRF-protected upload staging and a one-hour, session-owned import preview.
- Added access-checked JSON/PNG downloads with memory excluded by default.
- Imported portraits strip text/card metadata before public storage.
- Imported model and Psyché saves use one database transaction.
- Added HU/EN labels and validated both INI files.
- PHP syntax checks passed; controller/UI wiring and integration tests remain.

## 2026-10-08 — Character card route wiring
- Connected import/export methods through the CharacterCards controller trait.
- New-model editor loads session-owned import previews and shows the compatibility notice.
- Model saves now use the import-aware transaction helper.
- PHP syntax checks passed; management-page buttons follow.

## 2026-10-08 — Character card management UI
- Added Import beside New model and Export beside each model delete button.
- Added shared-style import/export dialogs with PNG/JSON selection and optional memory.
- PHP template syntax checks passed; browser integration testing follows.

## 2026-10-08 — Character download output isolation
- Browser JSON export contained leading whitespace and a truncated ending.
- Export now clears output buffers before download headers and exits after the body.
- PHP syntax check passed; download/reimport verification follows.

## 2026-10-08 — Import editor form fix
- Moved import preview fields outside the form action attribute.
- Restored the model save URL; PHP syntax check passed.

## 2026-10-08 — Model management scrolling
- Wrapped the model management page in the existing scrollable app-content container.
- Browser JSON import and model/Psyché save checks passed.
- PHP template syntax check passed.

## 2026-10-08 — Character card dialog behavior
- Export closes its dialog after receiving the download response.
- Import closes on success and navigates to the editor; failures remain in the dialog.
- Added inline translated errors, duplicate-submit prevention and expired-login handling.
- JSON/PNG browser import and optional-memory checks passed.
- PHP syntax checks passed; updated dialog behavior needs browser verification.

## 2026-10-08 — Character greeting persistence
- Added atomic system + assistant greeting conversation creation without a fake user turn.
- Chat/message INSERT helpers now return zero on execution failure.
- Existing chat list supports greeting-only conversations.
- PHP syntax check passed; greeting/context wiring follows.

## 2026-10-08 — Separate roleplay context fields
- Added character-name resolution and first-message extraction.
- Dialogue examples remain in card data, separately from the editable Psyché.
- Runtime examples are explicitly labeled as illustrative, not real conversation history.
- Recognizes the previous imported example suffix without rewriting stored cards.
- PHP syntax checks passed; chat wiring follows.

## 2026-10-08 — Character greeting and runtime context
- Added explicit chat start with a persisted, name-resolved character greeting.
- Characters without a greeting retain the existing empty-chat behavior.
- Runtime system context resolves roleplay names and includes separate illustrative examples.
- Saved system context excludes dialogue examples; existing imported suffixes are recognized.
- PHP syntax check passed; AJAX/UI wiring follows.

## 2026-10-08 — Greeting chat UI
- New-chat AJAX returns persisted greeting HTML and updated chat titles.
- Added duplicate-click prevention and AJAX authentication handling.
- Prevented the sidebar new-chat link from also reloading the page.
- PHP syntax checks passed; browser greeting/send checks follow.

## 2026-10-08 — Legacy Tavern V1 import
- Added V1 JSON/PNG import with automatic conversion to V2.
- Preserves legacy extra fields under extensions.mara_lab_legacy_v1.
- V1 conversion, extra-field round-trip, PNG import and invalid-card checks passed.
- Updated HU/EN supported-format messages.
- Character greeting displayed successfully; browser V1 verification follows.

## 2026-10-08 — Character card README documentation
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

## 2026-10-11 — llama.cpp model switching
- Migrated the existing installation's PID setting from /tmp to var/run/llama.pid.
- Serialized direct server start/stop operations with a shared file lock.
- Validate the tracked process before sending SIGTERM; wait for process exit and port release before removing its PID.
- Refuse a new launch when the port is occupied without a valid tracked process.
- Reuse a server only when model, projector, context size, parallel slots and GPU layers match.
- Increased this installation's startup wait to 120 seconds after a logged 86-second 4B load.
- Cross-model switching tested successfully; context-size switching verification pending.

## 2026-10-11 — Switch to AGPL-3.0-only

- Replaced PolyForm Noncommercial 1.0.0 with the official GNU AGPL v3 text.
- Allowed personal and commercial use, including redistribution for a fee,
  subject to AGPL notices and corresponding-source obligations.
- Updated README and NOTICE; retained the previous license entry as history.


## 2026-10-16 — Provider diagnostics and Ollama token limit

- Mapped Mara's max_tokens setting to Ollama's options.num_predict.
- Removed Mara-only settings from Ollama's sampling options.
- Added an admin-only Ollama journal viewer through the restricted mara-service helper.
- Added shared model communication logging for Ollama and llama.cpp, with request IDs, settings, raw responses, HTTP status, duration and errors.
- Added log rotation at 5 MiB with three retained archives.
- Replaced legacy provider debug output with the shared log.
- Verified successful requests and raw response logging with both providers through the UI.
- Error logging is implemented; failure paths have not yet been explicitly tested.

## 2026-11-09 — Chat navigation, retry and Psyché

- Updated the user-rating star and added a separate multimodal projector selector.
- Fixed chat selection and the redirect after creating a new conversation.
- Added retry for the last unanswered user turn, using the existing message and conversation context.
- Made existing Psyché entries editable and reactivatable. Saving updates the same row and its `prompt_modify` timestamp, including saves without text changes. Memory changes have their own `memory_modify` timestamp.
- Added editing for the latest assistant reply when it is still the final message in the conversation. Subsequent model requests use the saved edit.

## 2026-11-10 — Tool calls, image fallback and llama.cpp sampling

- Guarded against malformed model tool calls where tool markup and subsequent output become part of one function argument.
- Hid `mmproj` files from the base-model dropdown. The dedicated projector selector remains available.
- Added a configurable external image URL template as a fallback for the chat `generate_image` tool when the local image service cannot be reached. The local installation tested Pollinations with a 512×512 request. Images are saved under `public/genimages/user_{userid}`.
- Added `dynatemp_range` and `dynatemp_exponent` sliders for llama.cpp. Explicit values are forwarded when advertised by the running server. Blank fields use server defaults. Smoothing options were left pending because support was unverified.
- Put Temperature and context size on the first row and both dynamic-temperature sliders together below. Both explicit dynamic-temperature values were verified in the outgoing llama.cpp request log.

## 2026-11-10 — Independent RAG embeddings and local service

- Added a separate embedding provider and model in the Knowledge Base. Ollama and a dedicated local llama.cpp embedding endpoint are supported. Chat provider switches can reuse the same embedding endpoint and index.
- Changing the embedding configuration reindexes existing chunks. The new settings and index are committed together. Errors during reindexing preserve the prior settings and index.
- Verified saving and reindexing locally with Qwen3-Embedding-0.6B Q8_0 served by a separate CPU llama.cpp process on port 8083.
- Set up the local `mara-embedding.service` and added it to the existing service panel. Verified stop and start from the panel after correcting the scoped sudoers file mode to `0440`.

## 2026-11-11 — Knowledge Base feedback and voice selection

- Added a Knowledge Base Test button that sends a real embedding request with the settings currently entered and reports the vector dimension or the error. The test makes no changes to saved settings or vectors.
- Added an indeterminate blue progress bar while saving or testing and disabled both buttons while a request is pending.
- Added the provider name to voice dropdown labels in the form `name (provider)`.
- The user verified the Knowledge Base controls and the voice labels on the local installation.

## 2026-11-11 — Chat attachments and streaming

- Added chat attachments with a text preview before sending. Common UTF-8 text and source files are read directly. MarkItDown converts text-based PDF, DOCX, PPTX and XLSX to Markdown. The upload limit is 8 MiB and the extracted-text limit is 16 KiB.
- Saved attachment text as part of the user message so it remains available when retrying. Verified all four document converters with sample files. The local chat attachment test succeeded. OCR for scanned PDFs and native audio/video attachments remain on the TODO list.
- Added streaming for new chat messages with Ollama and llama.cpp. The UI shows partial output during generation. On success the normal save path stores the complete reply. Interrupted output remains a marked preview and the original user message can be retried.
- Added a per-model Stream switch between the voice selector and the private-model switch. Streaming defaults to enabled for existing models. The user verified streaming and the switch on the local installation.

## 2026-11-11 — ReaderLM-v2 web import and Knowledge Base tabs

- Added webpage import from the new Knowledge Base entry form. The Mara modal accepts a URL and the ReaderLM-v2 output appears in the editable content field. Saving stores the source URL and the original download timestamp in `source_fetched_at` (UTC). The existing `created_at` records when the entry was saved.
- Added the on-demand `mara-reader.service` on port 8084. Mara starts it after downloading HTML, waits for model readiness and stops it after processing, including on errors. A lock prevents overlapping Reader jobs. Automatic shutdown was verified locally.
- Increased the default decompressed HTML download cap to 4 MiB. Added separate download, HTTP, input size, Reader timeout and output-token diagnostics. When cleaned HTML exceeds the Reader input limit, Mara can select a suitable main content container.
- Moved Reader timeouts, size limits, the output-token limit and `system_prompt` into the `reader` settings group. Split the Knowledge Base into RAG, Embedding and Reader tabs. The two settings tabs are available to administrators. The Reader tab provides prompt editing, sliders and numeric inputs.
- Verified import with the official SQLite RETURNING documentation. The editable Markdown retained the SQL example and all seven limitations. The initial Aslaug chat used web tools and gave an incorrect CTE answer. A follow-up correctly explained the CTE restriction and the values returned before AFTER-trigger changes. The chat response alone does not prove which RAG chunks were retrieved.