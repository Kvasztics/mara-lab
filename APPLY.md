# Emotional Ball visibility and ShareGPT export — 2026-10-05

Copy core/, tpl/ and public/ into /var/www/html/maralab, merging and overwriting files.
Five production files:
- core/Chat.php (latest Chat(3).php)
- core/ajax/chat_ajax.php (latest chat_ajax(2).php)
- core/ShareGptExport.php (new)
- tpl/chat.tpl.php (last delivered radar template)
- public/assets/js/chat.js (last delivered model-switch diagnostics retained)

No SQL migration is required; mChat.php remains unchanged.

Emotional Ball: chart starts hidden with the unchecked switch. Enabling shows and reads the stored state. Disabling hides the whole panel and invalidates pending reads. Model changes and completed replies refresh it when enabled. Hiding never changes DB state.

Conversation menu: Exportálás (ShareGPT JSON) is inserted when a three-dot menu opens, including after sidebar refresh. The selected conversation downloads as mara-chat-ID-sharegpt.json. Export only permits conversations owned by the signed-in user, even for admins; the chat need not be active. It does not change the active model/chat.

Dataset shape: array containing one object with id and conversations. Turns use from=system/human/gpt and value=text. Full persisted messages are read by ascending ID; empty and unsupported/tool roles are skipped. Stored system text is retained. Runtime-only instructions, tool calls/results, ratings, metrics and image data/links in attachment fields are excluded. Visible message content is preserved exactly, including Unicode/newlines. This is a text-only starting point for curation; it does not recreate historical runtime psyche/memory/tool prompts and does not ensure a completed alternating training conversation. Review the downloaded dataset before training.

Validation: PHP lint, Node syntax, isolated export ownership/format checks. Actual browser download/toggle verification remains on the workstation.

Check after copying:
1. Ctrl+F5; Emotional Ball off: no diagram. On: stored diagram returns. Off: entire diagram panel disappears.
2. Three dots on any chat → Exportálás (ShareGPT JSON). Open the downloaded JSON.
3. Check system/human/gpt roles, accents/newlines and chronological content.

Previously deployed note truncation and model-switch diagnostics are retained in their respective files; this package does not replace EmotionalState.php or ProviderManager.php.
