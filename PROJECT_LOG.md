# 2026-10-05 — Small chat features
- Latest uploaded Chat(3), mChat(2), chat_ajax(2) inspected. Existing getChat already scopes ownership; no DB repository edit needed.
- Emotional Ball diagram visibility follows the front switch, with no state deletion and no disabled background reads.
- Added owned-conversation ShareGPT JSON export through the existing chat dropdown handler; works for inactive chats and refreshed sidebar HTML.
- Kept the persistent system message and text turns. Excluded tool/attachment/metrics fields, preserving Unicode and line breaks.
- Retained today's model-switch status errors.
- Pending separate fix: robust llama.cpp lifecycle/PID handling. Manual corrected PID confirmed switching works, but startup root cause remains unresolved.
