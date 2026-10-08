<main class="app-content">
<section class="models-page">
  <header class="models-header">
    <div>
      <h1 class="models-title"><?php echo LANG['MODEL_SETTINGS']; ?></h1>
      <p class="models-description">
        <?php echo LANG['MODELS_TITLE']; ?>
      </p>
    </div>
    <div class="models-header-actions">
<a href="<?php echo DIR_HOST.'/main/newmodel'; ?>"
      class="btn btn-primary btn-new-model">
      <span class="btn-icon">+</span>
      <?php echo LANG['MODELS_NEW_MODEL']; ?>
    </a>
    <button type="button" class="btn btn-primary"
            onclick="modalOpen('modal_character_import');">
      <?= htmlspecialchars(LANG['CARD_IMPORT'], ENT_QUOTES, 'UTF-8') ?>
    </button>
    </div>
  </header>
  <div class="models-grid" style="padding-top:10px;">
    <?php echo $smodels; ?>
  </div>
</section>
</main>
<?php require DIR_TPL . '/character_cards.tpl.php'; ?>
<script src="<?= htmlspecialchars(DIR_JS . '/character-cards.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
