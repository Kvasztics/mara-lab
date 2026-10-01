<div class="message-row model">

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
