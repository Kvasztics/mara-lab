<?php
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$proposal = json_decode($memoryReview['proposal'], true);
$canReplace = ($proposal['action'] ?? '') === 'supersede' && $memoryReview['previous'] !== null;
?>
<div id="modal_memory_review" class="modal common-modal" role="dialog" aria-modal="true" aria-labelledby="memory-review-title">
    <div class="modal-content">
        <button type="button" class="close" aria-label="<?= $escape(LANG['MODAL_CLOSE']) ?>"
                onclick="modalClose('modal_memory_review')">&times;</button>
        <div class="content">
            <h2 id="memory-review-title"><?= $escape(LANG['MEMORY_REVIEW_TITLE']) ?></h2>
            <form method="post" action="<?= $escape(DIR_HOST . '/main/memoryreview') ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($memoryReviewToken) ?>">
                <input type="hidden" name="message_id" value="<?= (int)$memoryReview['message_id'] ?>">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,14rem),1fr));gap:1rem">
                    <div>
                        <strong><?= $escape(LANG['MEMORY_REVIEW_SOURCE']) ?></strong>
                        <p style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:12rem;overflow:auto"><?= $escape($memoryReview['source']) ?></p>
                    </div>
                    <div>
                        <label for="memory-review-content"><?= $escape(LANG['MEMORY_REVIEW_CONTENT']) ?></label>
                        <textarea id="memory-review-content" name="content" rows="6" maxlength="500" required style="width:100%"><?= $escape($proposal['text'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php if ($canReplace): ?>
                    <p><strong><?= $escape(LANG['MEMORY_REVIEW_PREVIOUS']) ?></strong> <?= $escape($memoryReview['previous']) ?></p>
                    <label for="memory-review-mode"><?= $escape(LANG['MEMORY_REVIEW_MODE']) ?></label>
                    <select id="memory-review-mode" name="mode">
                        <option value="supersede"><?= $escape(LANG['MEMORY_REVIEW_REPLACE']) ?></option>
                        <option value="add"><?= $escape(LANG['MEMORY_REVIEW_ADD']) ?></option>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="mode" value="add">
                <?php endif; ?>
                <label for="memory-review-kind"><?= $escape(LANG['MEMORY_KIND']) ?></label>
                <select id="memory-review-kind" name="kind">
                    <?php foreach (['context', 'profile', 'project'] as $kind): ?>
                        <option value="<?= $kind ?>" <?= ($proposal['kind'] ?? 'context') === $kind ? 'selected' : '' ?>>
                            <?= $escape(LANG['MEMORY_KIND_' . strtoupper($kind)]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="modal-actions">
                    <button class="btn" name="decision" value="dismiss" formnovalidate><?= $escape(LANG['MEMORY_REVIEW_DISMISS']) ?></button>
                    <button class="btn btn-primary" name="decision" value="approve"><?= $escape(LANG['MEMORY_REVIEW_APPROVE']) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
