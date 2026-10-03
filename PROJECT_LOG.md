2026-10-03 — llama.cpp capability persistence
- Latest Models(5).php and mModels(2).php used.
- After successful prepare, query server once and persist facts into modelinfo.
- Cached source includes provider, basemodel and mmproj; changed source invalidates facts.
- Editor initialization/save preserves database runtime facts.
- Conditional metadata-only SQL prevents writing facts onto a concurrently changed model source.
- Added saved parameters.think and legacy thinking-column fallback; earlier assumption about storage corrected.
- Local PHP parser, JS syntax/cache tests passed. Pi PHP regression and live verification pending.
