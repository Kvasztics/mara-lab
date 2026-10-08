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
