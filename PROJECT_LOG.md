2026-10-04 — Emotional Ball v1
- User chose eight emotions with integer intensities 0–10; small colored dots/tooltips and spider chart deferred.
- Fresh uploaded Chat, chat template/JS and model template used.
- Added update_emotional_state tool and default-off chat switch between Test and Rate User.
- Tool/instruction enabled only with front switch + model Tools + selected tool; server also rejects unoffered calls.
- system.emotional_ball instruction stored in DB via repeatable migration.
- State scoped to user/model, carried across their conversations; current state injected only while enabled.
- Strict validation; at most one successful update per response; private JSON-lines log with previous/new state/note.
- PHP lint and executed validation/gating tests via PHP WASM passed; JS syntax passed.
- Pi migration and live model trial pending. Display and installer integration deferred.

## 2026-10-04 — Emotional Ball display
- Added an authenticated read endpoint using only User::id() and the active session model, never client-supplied user/model identifiers.
- Added SVG radar chart, eight 8px colored axis dots, native tooltips and keyboard-focus value labels.
- Reads existing state at page load, model switch and successful reply. No extra model call, no chart dependency, no database migration.
- Old asynchronous reads cannot overwrite a newer character's display. Read failures clear stale scores and show unavailable status.
- Latest supplied chat_ajax and basic.css retained, with scoped additions only.
- Local PHP/JS syntax and DOM interaction/race checks passed. Pi/browser integration still requires user testing.
