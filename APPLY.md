# Emotional Ball chart — incremental update

Apply after the Emotional Ball tool/state migration already deployed today.
Only four production files change:
- core/ajax/chat_ajax.php (based on the latest chat_ajax(1).php upload)
- tpl/chat.tpl.php (based on today's Emotional Ball version)
- public/assets/js/chat.js (based on today's Emotional Ball version)
- public/assets/css/basic.css (based on the latest basic(3).css upload)

No new database migration or model dependency is required.
The display reads the logged-in user's active model state from the database. It never invokes inference. It renders eight independent 0–10 values with 8px colored axis dots and a radar polygon. Hover/focus shows the emotion name and value; native SVG titles provide tooltips. The chart is visible when the tool switch is off, showing the last stored state. The switch still controls only tool execution. A missing state starts at zero; database errors show an unavailable message. Data loads on page entry, model change and successful message completion. A request version guard ignores stale responses from earlier models.

Copy the four production files into the Git checkout first:
/home/platon/mara-lab-installer/mara-lab-git

Then deploy to the Pi test installation:

```bash
php -l /home/platon/mara-lab-installer/mara-lab-git/core/ajax/chat_ajax.php
php -l /home/platon/mara-lab-installer/mara-lab-git/tpl/chat.tpl.php
for file in core/ajax/chat_ajax.php tpl/chat.tpl.php public/assets/js/chat.js public/assets/css/basic.css; do
    sudo install -o root -g www-data -m 0644 "/home/platon/mara-lab-installer/mara-lab-git/$file" "/var/www/mara-lab-test/$file" || break
done
```

Refresh with Ctrl+F5. Check Hugi's saved values, hover/focus the dots, then send a message with Emotional Ball enabled. Verify chart updates after the reply; disabling the switch retains the stored diagram. Switch characters and check the diagram switches too.

Validation performed locally: PHP lint of both changed PHP files; Node syntax check; executable DOM checks of all eight axes, hover/focus labels, out-of-order response handling and invalid-state clearing. Live Pi database/browser verification remains to be done.
