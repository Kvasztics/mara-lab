<?php
$modalEscape = static fn(string $value): string =>
    htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $modalEscape(DIR_CSS . '/modals.css') ?>">

<div id="modal_pconfirm" class="modal common-modal"
     role="dialog" aria-modal="true" aria-labelledby="pconfirm_title">
  <div class="modal-content">
    <button type="button" class="close"
            aria-label="<?= $modalEscape(LANG['MODAL_CLOSE']) ?>"
            onclick="modalClose('modal_pconfirm');">&times;</button>
    <div class="content">
      <p id="pconfirm_title"></p>
      <div class="modal-actions">
        <button type="button" class="btn btn-primary"
                onclick="modalClose('modal_pconfirm');">
          <?= $modalEscape(LANG['CANCEL']) ?>
        </button>
        <button type="button" id="pconfirm_ok" class="btn btn-primary">
          <?= $modalEscape(LANG['MODAL_OK']) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="modal_palert" class="modal common-modal"
     role="dialog" aria-modal="true" aria-labelledby="palert_title">
  <div class="modal-content">
    <button type="button" class="close"
            aria-label="<?= $modalEscape(LANG['MODAL_CLOSE']) ?>"
            onclick="modalClose('modal_palert');">&times;</button>
    <div class="content">
      <p id="palert_title"></p>
      <div class="modal-actions">
        <button type="button" class="btn btn-primary"
                onclick="modalClose('modal_palert');">
          <?= $modalEscape(LANG['MODAL_OK']) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
if (typeof window.modalOpen !== 'function') {
    window.modalOpen = function (id) {
        const modal = document.getElementById(id);
        modal.style.visibility = 'visible';
        modal.style.opacity = 1;
        return false;
    };
}
if (typeof window.modalClose !== 'function') {
    window.modalClose = function (id) {
        const modal = document.getElementById(id);
        modal.style.visibility = 'hidden';
        modal.style.opacity = 0;
        return false;
    };
}

function _confirm(msg, callback) {
    document.getElementById('pconfirm_title').innerHTML = msg;
    document.getElementById('pconfirm_ok').onclick = callback;
    modalOpen('modal_pconfirm');
    return false;
}

function _alert(msg) {
    document.getElementById('palert_title').innerHTML = msg;
    modalOpen('modal_palert');
    return false;
}
</script>
