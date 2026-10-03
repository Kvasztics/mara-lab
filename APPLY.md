# Mara-Lab: persist llama.cpp capabilities

Apply after mara-lab-vision-fixes.zip. This package updates four production files based on the latest uploaded Models/mModels files and the previous provider/JS patch. No SQL migration or config change is required.

Copy core/, database/ and public/ into:
/home/platon/mara-lab-installer/mara-lab-git

The optional tests/ directory contains a standalone regression test with fake database objects; it does not access the real database.

## Pi checks and deployment

```bash
cd /home/platon/mara-lab-installer/mara-lab-git
php -l core/Models.php
php -l database/mModels.php
php -l core/provider/LlamaCppProvider.php
```

If you copied tests/, also run:

```bash
php tests/capability_cache.php
```

After the checks pass:

```bash
sudo tar -C /var/www/mara-lab-test -czf /home/platon/mara-capabilities-backup-$(date +%Y%m%d-%H%M%S).tar.gz core/Models.php database/mModels.php core/provider/LlamaCppProvider.php public/assets/js/model.js
for file in core/Models.php database/mModels.php core/provider/LlamaCppProvider.php public/assets/js/model.js; do
    sudo install -o root -g www-data -m 0644 "$file" "/var/www/mara-lab-test/$file"
done
```

Press Ctrl+F5 in the browser. Select/load Hugi through Mara, then reopen the model editor/list. The first successful prepare discovers capabilities. Later prepares reuse stored capability data; normal server readiness checks still occur.

In phpMyAdmin, inspect the row's modelinfo JSON: capabilities_known should be true, with vision/tools/thinking values and _capability_source. No manual SQL edit is needed. Save the model editor and check these facts remain saved.

Changing provider, base model or mmproj invalidates the cache. When changing a projector, restart the existing server before loading to ensure the new projector is actually loaded (the existing direct launcher reuses a server with the same base model).

Thinking is now saved in parameters.think as well as the existing thinking column. Legacy models normalize the column into the parameter, preserving explicit saved choices. To disable an existing model, turn off Thinking and save.

Runtime facts describe what the server/template reports, not a benchmark of tool reliability. Incomplete or failed retrieval remains unknown and can be retried. Cache write failure does not block chat and is logged. Static trained context metadata is preserved rather than replaced by the server's shorter runtime context.

Local verification: PHP syntax parser and Node syntax check passed. JS behavior test confirms that matching cache skips retrieval and base/projector changes fetch fresh info. The PHP regression test and real server/database integration still need to run on the Pi.
