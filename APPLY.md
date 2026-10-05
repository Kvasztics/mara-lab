# Settings log viewer
Merge core/, tpl/, public/ into /var/www/html/maralab, overwriting corresponding files. Refresh with Ctrl+F5.
No database migration. Only administrators see the Logs tab; the read endpoint also checks the administrator role.
Sources: llama.cpp (settings llamacpp.log_file, default /tmp/mara-llama.log), Emotional Ball (var/log/emotional-ball.log), Nginx errors (/var/log/nginx/error.log).
The PHP worker must already have read access. This patch grants no filesystem privileges. Missing/unreadable files display a message. Nginx logs may require a separate administrator-managed permission arrangement; do not make logs world readable.
Reads last 200 lines within a 256 KiB maximum. Auto refresh every 5 seconds only while the tab and document are visible; requests are cancelled when leaving. Source selection is exclusive. Copy works on HTTP via a textarea fallback.
syntax.js gains a log mode. Untrusted log lines are rendered as text nodes, including HTML-looking payloads. Existing language parsers are unchanged.
Log sources are registered in core/LogViewer.php. No user-supplied file paths or shell commands. The read-only response is private/no-store.
Validation: PHP lint (3 files), JavaScript syntax (2 files), executable PHP tail tests and JavaScript highlighting/text-safety tests passed. Actual browser deployment still needs local testing.
