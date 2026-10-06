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
