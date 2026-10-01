<!-- ======================================================
      MODELL
      ====================================================== -->
    <div class="model-row">
        <a href="<?php echo DIR_HOST.'/main/models/'.$id; ?>" class="model-card">
          <div class="model-img-wrapper">
            <img
                src="<?php echo DIR_HOST.'/assets/img/models/'.$image; ?>"
                alt="<?php echo $name; ?>"
                class="model-thumb"
            >
          </div>
          <div class="model-info">
            <div class="model-name-line">
              <h2 class="model-name"><?php echo $name; ?></h2>
              <span class="model-badge <?php echo $badgeclass; ?>">
                <?php echo $info; ?>
              </span>
            </div>
              <p class="model-desc">
                <?php echo $note; ?>
              </p>
          </div>
          <div class="model-properties">
              <div class="model-property-main">
                <?php echo $basemodel; ?>
              </div>

              <div class="model-meta">
                <?php
                  $meta = [];

                  if (!empty($modelinfo['size']))
                    $meta[] = $modelinfo['size'];

                  if (!empty($modelinfo['quantization']))
                    $meta[] = $modelinfo['quantization'];

                  if (!empty($modelinfo['context']))
                    $meta[] = number_format((int)$modelinfo['context'], 0, '', ' ').' ctx';

                  echo implode(' · ', $meta);
                ?>
              </div>

              <?php
                $capabilities = [];

                if (!empty($modelinfo['vision']))   $capabilities[] = 'Vision';
                if (!empty($modelinfo['video']))    $capabilities[] = 'Video';
                if (!empty($modelinfo['audio']))    $capabilities[] = 'Audio';
                if (!empty($modelinfo['tools']))    $capabilities[] = 'Tools';
                if (!empty($modelinfo['thinking'])) $capabilities[] = 'Thinking';
              ?>

              <?php if (!empty($capabilities)) { ?>
                <div class="model-capabilities">
                  <?php echo implode(' · ', $capabilities); ?>
                </div>
              <?php } ?>

              <div class="model-provider">
                <?php echo $provider; ?>
              </div>
          </div>
        </a>
        <button
          type="button"
          class="model-delete-btn"
          data-id="<?php echo $id; ?>"
          title="<?php echo LANG['MODELS_DELETE']; ?>"
          aria-label="<?php echo LANG['MODELS_DELETE']; ?>">
          <img
            src="<?php echo DIR_HOST; ?>/assets/icons/trash.svg"
            width="20"
            alt=""
          >
        </button>
    </div>