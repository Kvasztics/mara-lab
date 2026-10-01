<section class="models-page">
  <header class="models-header">
    <div>
      <h1 class="models-title"><?php echo LANG['MODEL_SETTINGS']; ?></h1>
      <p class="models-description">
        <?php echo LANG['MODELS_TITLE']; ?>
      </p>
    </div>
    <a href="<?php echo DIR_HOST.'/main/newmodel'; ?>"
      class="btn btn-primary btn-new-model">
      <span class="btn-icon">+</span>
      <?php echo LANG['MODELS_NEW_MODEL']; ?>
    </a>
  </header>
  <div class="models-grid" style="padding-top:10px;">
    <?php echo $smodels; ?>
  </div>
</section>