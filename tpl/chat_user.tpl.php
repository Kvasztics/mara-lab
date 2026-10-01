<div class="message-row user">

    <div class="message-body<?php echo !empty($image) ? ' message-with-image' : ''; ?>">

        <?php if (!empty($image)): ?>

            <div class="message-image">

                <img
                    src="<?php echo $image; ?>"
                    alt="Feltöltött kép">

            </div>

        <?php endif; ?>

        <div class="message-content-column">

            <div class="message-text"><?php echo $message; ?></div>

            <div class="message-actions">
                <button type="button" class="message-action" aria-label="Értékelés" onclick="getUserRating(<?php echo (int)$id; ?>)">
                    <?= $this->icon('star') ?>
                </button>
            </div>

        </div>

    </div>

</div>