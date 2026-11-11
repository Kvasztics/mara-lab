# Mara Lab projektnapló

## 2026-10-05 — Naplónéző a Beállításokban

- Adminoknak elérhető Naplók fül a feltöltött settings, main, scripts és syntax fájlokra építve. Korlátozott olvasású LogViewer és `main/logread` végpont, kizáró kék kapcsolók, kézi és automatikus frissítés, másolás. A meglévő szintaxiskiemelő biztonságos naplókategóriát kapott, a fekete néző magassága 420 px. Az adatbázis és a szolgáltatók életciklusa változatlan maradt. A PHP-, JavaScript-, naplóolvasó- és kiemelőtesztek sikerültek. A helyi böngészőpróba ekkor még hátravolt.

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

## 2026-10-07 — Qwen képszerkesztés és időkorlátok

- A Qwen referenciaképes szerkesztése az SDAPI `txt2img` végpontját használja az `extra_images` és a `resize_before_vae=false` beállítással. A 992×992 és 1152×864 méret közvetlenül az INT8 szövegkódolóval is működött. A böngésző arányosan, középre igazítva vágja a referenciát. Qwennél a szerkesztés alapértéke 40 lépés, amely módosítható. A Forge megtartotta a zajtalanítási vezérlőjét.
- Az első szerkesztés 547,56 másodpercig futott, az Nginx viszont 300 másodpercnél lezárta a kérést. A helyi és a telepítőbeli `fastcgi_read_timeout` 630 másodperc lett. A képi API cURL-időkorlátja 600 másodperc, a PHP generálási és felskálázási korlátja 630 másodperc. A meglévő telepítéseknél a PHP location módosítása után `nginx -t` és újratöltés szükséges. A PHP-FPM `request_terminate_timeout` értékének hosszabbnak kell lennie a kérésnél, vagy kikapcsolva kell maradnia.
- A párhuzamos llama-server VRAM-használata miatt a Qwen prefix-gyorsítótára egyszer nem fért el. A Qwen gyorsítótár nélkül újrapróbálta a műveletet.
- Az időkorlát módosítása után a MaraImg szerkesztése és a galériamentés sikerült. A 20 lépéses nyakláncpróbában CFG 1 mellett 82, CFG 6 mellett 282 másodperc volt a futás. Mindkettő megőrizte az alanyt, a CFG 6 erősebb kontrasztot és nagyobb, világosabb gyöngyöket adott. A fényerő- és kontrasztszerkesztés további próbája is sikerült.

## 2026-10-08 — MaraImg, telepítő és képkeresés

- A hitelesítetlen képlap- és AJAX-kérések az engedélyezett `/image` útvonalat tárolják visszatérési célként. A MaraImg AJAX-fejlécet küld, 401-nél pedig generálási hiba helyett a belépőoldalra irányít. Másik lapon történő kijelentkezéssel és újrabelépéssel ellenőrizve.
- A galériában kiválasztott kép a galériafülön marad. A saját képek adatait hitelesített végpont és korlátozott PNG-olvasó adja vissza (`tEXt`, `zTXt`, `iTXt`). A közös párbeszédablak a promptokat és beállításokat szövegként mutatja, magyar és angol feliratokkal. A prompt átvétele visszaállítja a pozitív és negatív promptot, és megnyitja a generálási fület. A kép és a többi beállítás megmarad. A PHP-szintaxis, a metaadatok, az ablak és a prompt átvétele ellenőrizve.
- A README-be bekerült a keretrendszer nélküli PHP/JavaScript felépítés, az `update_emotional_state` és a MaraImg külön háttérszolgáltatásai. Ekkor a `search_web` még csak webes találatokat adott.
- Angolra fordult az `install.sh` és a `providers.sh` szövege, valamint a `setup.php` záróüzenete. A `services.sh` már angol volt. A README követte a módosítást. A Bash- és PHP-szintaxis ellenőrizve, friss telepítési próba ekkor még nem volt.
- A közvetlen SearXNG-próba öt képtalálatot adott. Kikerültek a devicons és lucide motorok. A hiányzó `STATUS_SEARCHIMAGES` feliratok pótlásával a képeszköz mindkét nyelven elindult. Aslaug rendezett kép- és forráslinkeket adott vissza. A `search_images` bekerült a README-be. Egy külön vizsgált gépújraindulás naplójában CPU Machine Check hibák voltak, az okuk ekkor nyitott maradt.

## 2026-10-08 — Karakterkártyák és Psyché

- A telepítési séma és a `20261008_character_cards.sql` migráció megkapta a nullable `models.card_data` mezőt. A migrációt a helyi adatbázisra phpMyAdminból alkalmaztuk. A modell beszúrása és frissítése kezeli az adatot, a közönséges szerkesztés megtartja. Új modellhez a `Models::save` külön kártya-JSON argumentumot fogad.
- A korlátozott V2/V3 JSON-kódoló ellenőrzi a verziót és a kötelező mezőtípusokat, és megőrzi az ismeretlen mezőket, objektumokat és bővítményeket. A PNG-olvasó és -író `chara` és `ccv3` blokkokat kezel, mindkettő meglétekor a V3-at választja, ellenőrzi a méreteket és a CRC-ket. Exportáláskor a képi blokkok megmaradnak, a független szöveges metaadatok kikerülnek. JSON/PNG oda-vissza próba és sérült fájl elutasítása ellenőrizve.
- A Tavern karakterleírása és rendszerpromptja a Mara megfelelő mezőibe kerül. A változatlan leírás eredeti tagolása megmarad. Exportálhatók a hordozható generálási paraméterek és opcionálisan a Psyché-memória. Az import nem kapcsol be eszközöket, és nem választ gépfüggő modellútvonalat. A mezőleképezés és az opcionális memória oda-vissza próbája sikerült.
- Az import CSRF-védett feltöltést és egyórás, munkamenethez kötött előnézetet kapott. A jogosultság-ellenőrzött JSON/PNG letöltés alapból kihagyja a memóriát. Az importált portréról nyilvános tárolás előtt lekerülnek a szöveges és kártyaadatok. Az importált modell és a Psyché egy tranzakcióban mentődik. Magyar és angol feliratok, valamint PHP- és INI-ellenőrzések készültek.
- A `CharacterCards` trait bekötötte az import/export útvonalakat. Az újmodell-szerkesztő betölti a munkamenet előnézetét és a kompatibilitási tájékoztatót. A modellkezelőben az Új modell mellett Import, a modellek törlése mellett Export gomb és közös stílusú JSON/PNG ablak jelent meg.
- Az exportált JSON elején lévő üres helyet és a csonka véget a kimeneti pufferek törlésével, illetve a letöltés utáni kilépéssel javítottuk. Az import előnézeti mezői kikerültek az űrlap `action` attribútumából, így helyreállt a mentési URL. A modellkezelő a közös görgethető konténerbe került. Böngészőből a JSON-import és a modell/Psyché mentése sikerült.
- Az exportablak a letöltési válasz után bezárul. Az import siker esetén a szerkesztőbe visz, hibánál az ablakban marad. Lokalizált hibákat, ismételt beküldés elleni védelmet és lejárt belépés kezelését kapott. A böngészős JSON/PNG import és az opcionális memória működött. Az új ablakviselkedés próbája ekkor még hátravolt.
- A régi Tavern V1 JSON/PNG kártyák importja V2-re alakít. Az extra mezők az `extensions.mara_lab_legacy_v1` alatt maradnak. A konverzió, az extra mezők oda-vissza próbája, a PNG-import, a hibás kártya elutasítása és később a böngészős V1-import is sikerült. A README dokumentálja a V1/V2/V3 formátumokat, az opcionális memóriát, a hordozható paramétereket, a megőrzött inaktív mezőket, valamint a CHARX és az eszközfájlok támogatásának hiányát.

## 2026-10-08 — Karakterköszöntés és beszélgetésműveletek

- Az új chat a feloldott nevekkel együtt, atomikusan mentheti a rendszerüzenetet és a karakter nyitóüzenetét, mesterséges felhasználói üzenet nélkül. Köszöntés nélküli karaktereknél megmaradt az üres chat. Sikertelen adatbázis-beszúrásnál a segédmetódusok nullát adnak vissza. A chatlista kezeli a csak köszöntést tartalmazó beszélgetést.
- A szerepjátékos nevek feloldása és az első üzenet kiolvasása bekerült a futásidejű kontextusba. A dialóguspéldák a szerkeszthető Psychétől és a mentett rendszerkontextustól külön maradnak a kártyaadatban. A modell szemléltető példaként kapja őket. A régi importált példautótagot a Mara felismeri a kártya átírása nélkül. A lorebook és a beszélgetés utáni instrukciók bekötése ekkor még nem készült el.
- Az új-chat AJAX a mentett köszöntés HTML-jét és a frissített chatcímeket küldi vissza. Kettős kattintás elleni védelem és AJAX-hitelesítés került bele. Az oldalsáv hivatkozása már nem tölti újra az oldalt. A PHP-szintaxis ellenőrizve, a karakterköszöntés megjelent.
- Karakterenként négy, korlátozott DRY-paraméter került a tárolásba és a Mara kártyabővítményébe, alapból kikapcsolva. Az Ollama-kérésekből a Mara kiszűri ezeket. A helyi llama.cpp-n a támogatás és a mintavételezők sorrendje igazolva. Magyar és angol súgó készült. A tokenablak 0–8192-es csúszkát és teljes kontextus kapcsolót kapott (`-1`).
- Saját beszélgetésben a felhasználói üzenet és válasza együtt, tranzakcióban törölhető. Újrageneráláskor a régi asszisztensválasz az új válasz sikeres mentéséig megmarad. Az újragenerálás a meglévő folyamatot használja, és nem duplikálja a felhasználói üzenetet. Közben megváltozott beszélgetés esetén a csere elutasításra kerül. Aktív beszélgetéshez kötött tulajdonos-ellenőrzés, CSRF-védett POST végpontok, lokalizált üzenetek, ikonok és a közös törlési megerősítés készültek. A README-ben a képes chat kész funkcióként szerepel, a videómegértés tervezett maradt.
- A licenc ekkor PolyForm Noncommercial 1.0.0 lett, külön engedéllyel a kereskedelmi használathoz. A harmadik fél összetevői megtartották saját licencüket.

## 2026-10-11 — llama.cpp modellváltás és licenc

- A helyi PID-fájl a `/tmp` alól a `var/run/llama.pid` útvonalra került. Közös fájlzár sorosítja a közvetlen szerver indítását és leállítását. SIGTERM előtt a Mara ellenőrzi a folyamatot, a PID-fájl törlésével megvárja a kilépést és a port felszabadulását. Érvényes PID nélküli foglalt portnál elutasítja az indítást.
- Szerver újrahasználatához egyeznie kell a modellnek, a projektornak, a kontextusméretnek, a párhuzamos slotoknak és a GPU-rétegeknek. Egy 86 másodperces 4B-betöltés után a helyi időkorlát 120 másodpercre nőtt. A modellváltás működött, a kontextusméret-váltás próbája ekkor még hátravolt.
- A PolyForm licencet az AGPL-3.0-only váltotta. Magán- és üzleti használat, valamint díjért történő terjesztés is lehetséges az AGPL feltételeivel. A README és a NOTICE frissült, a korábbi licencbejegyzés a naplóban megmaradt.

## 2026-10-16 — Szolgáltatói diagnosztika és Ollama tokenlimit

- A Mara `max_tokens` mezője az Ollama `options.num_predict` paraméterére kerül, a csak Marában használt opciók kiszűrődnek.
- A korlátozott `mara-service` segéddel admin Ollama-naplónéző készült. Az Ollama és a llama.cpp közös kommunikációs naplója rögzíti a kérésazonosítót, beállításokat, nyers választ, HTTP-státuszt, futásidőt és hibát. A rotáció 5 MiB-nál történik, három archívummal. A régi hibakereső kimenetet a közös napló váltotta.
- Mindkét szolgáltatónál ellenőriztük a sikeres UI-kérést és a nyers válasz naplózását. A hibaágak külön próbája ekkor még hátravolt.

## 2026-11-09 — Chatnavigáció, újrapróbálás és Psyché

- Javult a felhasználói értékelés csillagjelzése, a multimodális projektor pedig külön választómezőt kapott.
- Javult a chat kiválasztása és az új beszélgetés létrehozása utáni átirányítás.
- Az utolsó megválaszolatlan felhasználói üzenet újrapróbálható a meglévő üzenettel és beszélgetési kontextussal.
- A meglévő Psyché-bejegyzés szerkeszthető és újraaktiválható. A mentés ugyanazt a sort és a `prompt_modify` időbélyeget frissíti, változatlan szövegnél is. A memória változásait a külön `memory_modify` rögzíti.
- A legutóbbi asszisztensválasz szerkeszthető, amíg az a beszélgetés utolsó üzenete. A következő modellhívások a mentett változatot használják.

## 2026-11-10 — Eszközhívások, tartalék képgenerálás és llama.cpp-mintavételezés

- Védelem került az olyan hibás modellhívások ellen, amelyeknél az eszközjelölések és a későbbi modellkimenet belecsúszik egy függvény argumentumába.
- Az `mmproj` fájlok eltűntek az alapmodell legördülő listájából. A projektor külön továbbra is kiválasztható.
- A chat `generate_image` eszköze beállítható külső URL-sablont használhat, ha a helyi képgeneráló nem érhető el. Helyben Pollinationsszal, 512×512-es kéréssel próbáltuk. A képek a `public/genimages/user_{userid}` mappába kerülnek.
- A llama.cpp megkapta a `dynatemp_range` és `dynatemp_exponent` csúszkáit. A Mara a megadott értéket akkor küldi el, ha a futó szerver jelzi a paraméter támogatását. Üres mezőnél a szerver alapértéke érvényesül. A smoothing támogatása nem igazolódott, így az külön feladat maradt.
- A Temperature és a kontextusméret az első sorba, a két dinamikus csúszka együtt a következő sorba került. Mindkét megadott érték megjelent a kimenő llama.cpp-kérés naplójában.

## 2026-11-10 — Független RAG-embedding és helyi szolgáltatás

- A Tudástár saját embedding-szolgáltatót és modellt kapott. Ollama és külön, helyi llama.cpp embedding-végpont választható. A chat szolgáltatójának váltásakor ugyanaz az embedding-végpont és index használható.
- Az embeddingbeállítás módosítása újraindexeli a meglévő szövegrészleteket. Az új beállítások és az új index együtt lépnek életbe. Újraindexelési hiba esetén az előző beállítás és index megmarad.
- A helyi mentés és újraindexelés sikerült a Qwen3-Embedding-0.6B Q8_0 modellel, külön CPU-s llama.cpp folyamaton, a 8083-as porton.
- A helyi `mara-embedding.service` bekerült a meglévő szolgáltatáskezelő panelbe. A paneles leállítás és indítás a célzott sudoers-fájl `0440` jogosultságának beállítása után működött.

## 2026-11-11 — Tudástár-visszajelzés és hangválasztás

- A Tudástár Teszt gombja a mezőkben éppen megadott beállításokkal valódi embeddingkérést küld, és visszajelzi a vektor méretét vagy a hibát. A próba nem módosítja a mentett beállításokat és vektorokat.
- Mentés és teszt közben mozgó kék folyamatjelző jelenik meg. A kérés végéig mindkét gomb inaktív.
- A hangválasztóban az opciók felirata `név (szolgáltató)` formátumú.
- A Tudástár vezérlőit és a hangfeliratokat a felhasználó a helyi telepítésen ellenőrizte.

## 2026-11-11 — Chatcsatolmányok és streamelés

- A chatben dokumentum csatolható, küldés előtti szöveges előnézettel. A Mara közvetlenül olvassa a gyakori UTF-8 szöveg- és forrásfájlokat. A szöveges PDF, DOCX, PPTX és XLSX fájlokat a MarkItDown alakítja Markdownná. A feltöltés határa 8 MiB, a kinyert szövegé 16 KiB.
- A csatolmány szövege a felhasználói üzenet részeként mentődik, így újrapróbáláskor is elérhető. Mind a négy dokumentumkonvertert mintafájllal ellenőriztük. A helyi chatcsatolási próba sikerült. A szkennelt PDF-ek OCR-je és a natív hang- és videócsatolás a TODO-listán maradt.
- Az új chatüzenetekre az Ollama és a llama.cpp válasza menet közben megjelenhet. Sikeres befejezéskor a meglévő mentési folyamat a teljes választ tárolja. Megszakadáskor az addigi szöveg megjelölt előnézet marad, az eredeti felhasználói üzenet újrapróbálható.
- A modell adatlapján a hangválasztó és a Saját modell közé Stream kapcsoló került. A meglévő modelleknél alapból be van kapcsolva. A felhasználó helyben kipróbálta a streamelést és a kapcsolót.

## 2026-11-11 — ReaderLM-v2 webimport és Tudástár-fülek

- Az új tudásanyag űrlapján weboldal importálható. A Mara párbeszédablaka bekéri az URL-t, a ReaderLM-v2 eredménye pedig a szerkeszthető Tartalom mezőbe kerül. Mentéskor tárolódik a forrás URL-je és a letöltés eredeti UTC-időpontja a `source_fetched_at` mezőben. A meglévő `created_at` a tudásanyag mentési idejét rögzíti.
- Elkészült az igény szerint induló `mara-reader.service` a 8084-es porton. A Mara a HTML letöltése után indítja, megvárja a modell betöltését, majd feldolgozás után leállítja, hiba esetén is. Zár védi a párhuzamos használattól. Az automatikus leállítás helyben igazolva.
- A kitömörített HTML alapértelmezett letöltési korlátja 4 MiB-ra nőtt. Külön hibajelzést kapott a letöltés, a HTTP-válasz, a bemeneti méret, a Reader-időtúllépés és a kimeneti tokenlimit. Ha a tisztított HTML túl nagy, a Mara megpróbál megfelelő fő tartalmi blokkot választani.
- A Reader idő- és méretkorlátai, a kimeneti tokenlimit és a `system_prompt` külön `reader` beállításcsoportba kerültek. A Tudástár RAG, Embedding és Reader fület kapott. A két beállítási fül adminisztrátoroknak érhető el. A Reader fülön a prompt szerkeszthető, a korlátok csúszkával és számmezővel állíthatók.
- A hivatalos SQLite RETURNING dokumentáció importja sikerült. A szerkeszthető Markdown megtartotta az SQL-példát és mind a hét korlátozást. Aslaug első válasza webes eszközöket használt, és tévesen válaszolt a CTE-kérdésre. A következő válasz helyesen írta le a CTE-korlátot és az AFTER trigger módosítása előtti értékeket. A válasz szövege önmagában nem igazolja, mely RAG-részletek kerültek a modellhez.