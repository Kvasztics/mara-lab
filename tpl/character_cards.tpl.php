<?php
$cardEscape = static fn(string $text): string =>
    htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<div id="modal_character_import" class="modal common-modal"
     role="dialog" aria-modal="true" aria-labelledby="character-import-title">
  <div class="modal-content">
    <button type="button" class="close"
            aria-label="<?= $cardEscape(LANG['MODAL_CLOSE']) ?>"
            onclick="modalClose('modal_character_import');">&times;</button>
    <div class="content">
      <h2 id="character-import-title"><?= $cardEscape(LANG['CARD_IMPORT']) ?></h2>
      <form id="character-import-form"
            data-error="<?= $cardEscape(LANG['CARD_ERROR']) ?>"
            method="post" enctype="multipart/form-data"
            action="<?= $cardEscape(DIR_HOST . '/main/importcard') ?>">
        <input type="hidden" name="card_csrf_token"
               value="<?= $cardEscape($card_csrf_token) ?>">
        <div class="character-card-field">
          <label for="character-card-file"><?= $cardEscape(LANG['CARD_FILE']) ?></label>
          <input type="file" id="character-card-file" name="character_card"
                 accept=".json,.png,application/json,image/png" required>
        </div>
        <p><?= $cardEscape(LANG['CARD_UPLOAD_LIMIT']) ?></p>
        <p data-card-status role="status" aria-live="polite" hidden></p>
        <div class="modal-actions">
          <button type="button" onclick="modalClose('modal_character_import');">
            <?= $cardEscape(LANG['CANCEL']) ?>
          </button>
          <button type="submit"><?= $cardEscape(LANG['CARD_IMPORT']) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="modal_character_export" class="modal common-modal"
     role="dialog" aria-modal="true" aria-labelledby="character-export-title">
  <div class="modal-content">
    <button type="button" class="close"
            aria-label="<?= $cardEscape(LANG['MODAL_CLOSE']) ?>"
            onclick="modalClose('modal_character_export');">&times;</button>
    <div class="content">
      <h2 id="character-export-title"><?= $cardEscape(LANG['CARD_EXPORT']) ?></h2>
      <form id="character-export-form" method="get"
            data-error="<?= $cardEscape(LANG['CARD_EXPORT_ERROR']) ?>">
        <div class="character-card-field">
          <label for="character-export-format"><?= $cardEscape(LANG['CARD_FORMAT']) ?></label>
          <select id="character-export-format" name="format">
            <option value="png">PNG</option>
            <option value="json">JSON</option>
          </select>
        </div>
        <label class="character-card-memory">
          <input type="checkbox" name="memory" value="1">
          <?= $cardEscape(LANG['CARD_INCLUDE_MEMORY']) ?>
        </label>
        <p data-card-status role="status" aria-live="polite" hidden></p>
        <div class="modal-actions">
          <button type="button" onclick="modalClose('modal_character_export');">
            <?= $cardEscape(LANG['CANCEL']) ?>
          </button>
          <button type="submit"><?= $cardEscape(LANG['CARD_EXPORT']) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
