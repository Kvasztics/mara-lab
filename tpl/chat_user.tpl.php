<div class="message-row user" data-message-id="<?= (int)$id ?>">

    <div class="message-body<?php echo !empty($image) ? ' message-with-image' : ''; ?>">

        <?php if (!empty($image)): ?>

            <div class="message-image">

                <img
                    src="<?php echo htmlspecialchars((string)$image, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Feltöltött kép">

            </div>

        <?php endif; ?>

        <div class="message-content-column">

            <div class="message-text"><?php echo htmlspecialchars((string)$message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>

            <div class="message-actions">
                <button type="button" class="message-action" hidden disabled
                        data-message-action="retry"
                        data-csrf="<?= htmlspecialchars((string)($_SESSION['chat_turn_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars(LANG['CHAT_TURN_RETRY'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars(LANG['CHAT_TURN_RETRY'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $this->icon('regenerate') ?>
                </button>
                <button type="button"
                        class="message-action message-rating<?= !empty($has_user_rating) ? ' has-rating' : '' ?>"
                        aria-label="<?= htmlspecialchars(LANG['CHAT_USER_RATING'], ENT_QUOTES, 'UTF-8') ?>"
                        onclick="getUserRating(<?= (int)$id ?>)">
                    <?= $this->icon('star') ?>
                </button>
            </div>

        </div>

    </div>

</div>
