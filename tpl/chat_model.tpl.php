<div class="message-row model" data-message-id="<?= (int)($id ?? 0) ?>">

    <div class="message-body<?php echo !empty($image) ? ' message-with-image' : ''; ?>">

        <?php if (!empty($image)): ?>

            <div class="message-image">

                <img
                    src="<?php echo $image; ?>"
                    alt="Generált kép">

            </div>

        <?php endif; ?>

        <div class="message-content-column">

            <div class="model-message-content"><?php echo $message; ?></div>

            <div class="message-actions">
                <button type="button" class="message-action" disabled
                        data-message-action="regenerate"
                        data-csrf="<?= htmlspecialchars((string)($_SESSION['chat_turn_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars(LANG['CHAT_TURN_REGENERATE'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars(LANG['CHAT_TURN_REGENERATE'], ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M20 7v5h-5M4 17v-5h5"/>
                        <path d="M6.1 7a7 7 0 0 1 11.5-1L20 9M4 15l2.4 3A7 7 0 0 0 17.9 17"/>
                    </svg>
                </button>
                <button type="button" class="message-action" disabled
                        data-message-action="delete-turn"
                        data-csrf="<?= htmlspecialchars((string)($_SESSION['chat_turn_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-confirm="<?= htmlspecialchars(LANG['CHAT_TURN_DELETE_CONFIRM'], ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars(LANG['CHAT_TURN_DELETE'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars(LANG['CHAT_TURN_DELETE'], ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>
                    </svg>
                </button>
                <button
                    type="button"
                    class="message-action"
                    data-message-action="speak"
                    data-voice-text="<?php echo htmlspecialchars((string)($voice_text ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="Meghallgatás">
                    <?= $this->icon('speaker') ?>
                </button>

                <button type="button" class="message-action" aria-label="Értékelés">
                    <?= $this->icon('star') ?>
                </button>
            </div>

        </div>

    </div>

</div>
