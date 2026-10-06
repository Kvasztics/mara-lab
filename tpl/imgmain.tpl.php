<?php
$escape = static fn(string $value): string =>
    htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$imageTexts = [];
foreach ([
    'IMG_BUSY', 'IMG_ERROR_REQUEST', 'IMG_ERROR_PROMPT',
    'IMG_ERROR_PARAMS', 'IMG_ERROR_CAPABILITIES',
    'IMG_GALLERY_EMPTY', 'IMG_READY', 'IMG_SECONDS',
    'IMG_SERVER_READY', 'IMG_SERVER_UNAVAILABLE',
    'IMG_SERVER_START_SENT', 'IMG_SERVER_STOP_SENT',
    'IMG_SERVER_WORKING', 'IMG_SERVER_CONTROL_ERROR',
    'IMG_SERVER_STARTING', 'IMG_SERVER_START_TIMEOUT',
    'IMG_GEN_START', 'IMG_GEN_STOP', 'IMG_STOPPING',
    'IMG_CANCELLED', 'IMG_ERROR_INTERRUPT', 'IMG_ERROR_BACKEND_BUSY',
    'IMG_DELETE', 'IMG_DELETED', 'IMG_ERROR_DELETE', 'IMG_DELETE_CONFIRM',
    'IMG_ERROR_IMAGE_MISSING', 'IMG_ERROR_IMAGE_INVALID', 'IMG_ERROR_IMAGE_LIMIT',
    'IMG_UPSCALING', 'IMG_UPSCALE_READY', 'IMG_ERROR_UPSCALE', 'IMG_ERROR_UPSCALE_SIZE',
] as $key) {
    $imageTexts[$key] = LANG[$key];
}
?>
<script type="application/json" id="image-translations"><?= json_encode(
    $imageTexts,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) ?></script>

<main class="image-layout">
  <section class="left-panel">
    <div id="image-status" role="status" aria-live="polite"></div>
    <div id="image-progress-box" class="image-progress-box" hidden>
      <progress id="image-progress" max="100" value="0"
                aria-label="<?= $escape(LANG['IMG_BUSY']) ?>"></progress>
      <span id="image-progress-value">0%</span>
    </div>
    <div class="image-container" id="imgContainer" aria-busy="false"
         role="button" tabindex="0"
         title="<?= $escape(LANG['IMG_UPLOAD']) ?>"
         aria-label="<?= $escape(LANG['IMG_UPLOAD']) ?>">
      <p id="placeholder"><?= $escape(LANG['IMG_EMPTY']) ?></p>
      <img id="result-image" alt="" hidden>
    </div>
    <input type="file" id="image-upload"
           accept="image/png,image/jpeg,image/webp" hidden>
    <a id="image-download" class="image-link" target="_blank"
       rel="noopener" hidden><?= $escape(LANG['IMG_DOWNLOAD']) ?></a>
    <div id="batchGallery" class="image-thumbnails"></div>
  </section>

  <section class="right-panel">
    <header class="image-header">
      <h1>MaraImg</h1>
      <a href="<?= $escape(DIR_HOST) ?>"><?= $escape(LANG['IMG_BACK_TO_CHAT']) ?></a>
    </header>

    <nav class="settings-tabs">
      <button type="button" class="settings-tab active"
              data-tab="generate"><?= $escape(LANG['IMG_GENERATE']) ?></button>
      <button type="button" class="settings-tab"
              data-tab="gallery"><?= $escape(LANG['IMG_GALLERY']) ?></button>

      <button type="button" class="settings-tab"
              data-tab="settings"><?= $escape(LANG['IMG_SETTINGS']) ?></button>
    </nav>

    <section id="tab-generate" class="settings-tab-panel">
      <form id="image-form">
        <div class="form-group">
          <label for="image-backend"><?= $escape(LANG['IMG_BACKEND']) ?></label>
          <select id="image-backend" name="backend">
            <option value="qwen2" <?= $backend === 'qwen2' ? 'selected' : '' ?>>Qwen Image 2.1</option>
            <option value="forge" <?= $backend === 'forge' ? 'selected' : '' ?>>Forge</option>
          </select>
        </div>

        <div class="form-group">
          <label for="prompt"><?= $escape(LANG['IMG_POS_PROMPT']) ?></label>
          <textarea id="prompt" name="prompt" required rows="5"></textarea>
        </div>

        <div class="form-group">
          <label for="negative_prompt"><?= $escape(LANG['IMG_NEG_PROMPT']) ?></label>
          <textarea id="negative_prompt" name="negative_prompt" rows="3"></textarea>
        </div>

        <div class="form-group" data-forge-only hidden>
          <label for="modelSelect"><?= $escape(LANG['IMG_BASIC_MODEL']) ?></label>
          <select id="modelSelect" name="model" disabled></select>
        </div>

        <div class="settings-grid">
          <div class="form-group image-range-group">
            <label for="steps"><?= $escape(LANG['IMG_STEPS']) ?></label>
            <div class="image-range">
              <input type="range" id="steps" name="steps"
                     min="1" max="150" step="1" value="20">
              <output id="steps-value" for="steps">20</output>
            </div>
          </div>
          <div class="form-group image-range-group">
            <label for="cfg_scale"><?= $escape(LANG['IMG_CFG']) ?></label>
            <div class="image-range">
              <input type="range" id="cfg_scale" name="cfg_scale"
                     min="1" max="30" step="0.1" value="1">
              <output id="cfg_scale-value" for="cfg_scale">1</output>
            </div>
          </div>
          <div class="form-group">
            <label for="sampler_name"><?= $escape(LANG['IMG_SAMPLER']) ?></label>
            <select id="sampler_name" name="sampler">
              <option value="Euler">Euler</option>
            </select>
          </div>
          <div class="form-group" data-forge-only hidden>
            <label for="scheduler"><?= $escape(LANG['IMG_SCHEDULER']) ?></label>
            <select id="scheduler" name="scheduler" disabled></select>
          </div>
          <div class="form-group">
            <label for="image_count"><?= $escape(LANG['IMG_IMAGE_COUNT']) ?></label>
            <select id="image_count" name="image_count" disabled>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="4">4</option>
              <option value="6">6</option>
            </select>
          </div>
          <div class="form-group">
            <label for="image_size"><?= $escape(LANG['IMG_IMAGE_SIZE']) ?></label>
            <select id="image_size" name="image_size">
              <option value="512x512">512 × 512</option>
              <option value="768x768">768 × 768</option>
              <option value="1024x1024" selected>1024 × 1024</option>
              <option value="832x1216">832 × 1216</option>
              <option value="1216x832">1216 × 832</option>
              <option value="1024x576">1024 × 576</option>
              <option value="576x1024">576 × 1024</option>
            </select>
            <input type="hidden" id="width" name="width" value="1024">
            <input type="hidden" id="height" name="height" value="1024">
          </div>
          <div class="form-group">
            <label for="seed"><?= $escape(LANG['IMG_SEED']) ?></label>
            <input type="number" id="seed" name="seed" min="-1"
                   max="2147483647" step="1" value="-1"
                   placeholder="<?= $escape(LANG['IMG_RANDOM_SEED']) ?>" required>
          </div>
          <div class="form-group">
            <label for="generated_seed"><?= $escape(LANG['IMG_LAST_SEED']) ?></label>
            <input type="text" id="generated_seed" readonly>
          </div>
        </div>

        <div class="image-edit-controls" data-forge-only hidden>
          <label class="image-mode-toggle" for="img2img-mode">
            <span><?= $escape(LANG['IMG_IMG2IMG']) ?></span>
            <input type="checkbox" id="img2img-mode" role="switch" disabled>
            <span class="image-switch" aria-hidden="true"></span>
          </label>
          <div id="denoising-group" class="form-group" hidden>
            <label for="denoising_strength"><?= $escape(LANG['IMG_DENOISING']) ?></label>
            <div class="image-range">
              <input type="range" id="denoising_strength"
                     min="0" max="1" step="0.01" value="0.55" disabled>
              <output id="denoising_strength-value" for="denoising_strength">0.55</output>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-start" id="startBtn">
          <?= $escape(LANG['IMG_GEN_START']) ?>
        </button>

        <div class="image-upscale-controls" data-forge-only hidden>
          <div class="settings-grid">
            <div class="form-group">
              <label for="upscaler_select"><?= $escape(LANG['IMG_UPSCALER']) ?></label>
              <select id="upscaler_select" disabled></select>
            </div>
            <div class="form-group">
              <label for="upscale_factor"><?= $escape(LANG['IMG_UPSCALE_FACTOR']) ?></label>
              <select id="upscale_factor" disabled>
                <option value="2">2×</option>
                <option value="4">4×</option>
              </select>
            </div>
          </div>
          <button type="button" class="btn-start btn-upscale"
                  id="upscaleBtn" disabled>
            <?= $escape(LANG['IMG_UPSCALE']) ?>
          </button>
        </div>
      </form>
    </section>

    <section id="tab-settings" class="settings-tab-panel" hidden>
      <p><?= $escape(LANG['IMG_SERVER_LOCAL_NOTE']) ?></p>

      <?php foreach ([
          'qwen2' => 'Qwen Image 2.1',
          'forge' => 'Forge',
      ] as $serverBackend => $serverTitle): ?>
        <div class="image-server-card">
          <h2><?= $escape($serverTitle) ?></h2>
          <p class="image-server-url"><?= $escape(
              (string)\mara\core\App::get(
                  'system.' . $serverBackend . '_url',
                  ''
              )
          ) ?></p>

          <div class="image-server-actions">
            <button type="button" class="image-button"
                    data-server-backend="<?= $escape($serverBackend) ?>"
                    data-server-action="test">
              <?= $escape(LANG['IMG_SERVER_TEST']) ?>
            </button>

            <?php if (\mara\core\User::isAdmin()): ?>
              <button type="button" class="image-button"
                      data-server-backend="<?= $escape($serverBackend) ?>"
                      data-server-action="start">
                <?= $escape(LANG['IMG_SERVER_START']) ?>
              </button>
              <button type="button" class="image-button"
                      data-server-backend="<?= $escape($serverBackend) ?>"
                      data-server-action="stop">
                <?= $escape(LANG['IMG_SERVER_STOP']) ?>
              </button>
            <?php endif; ?>
          </div>

          <p id="server-status-<?= $escape($serverBackend) ?>"
             role="status" aria-live="polite"></p>
        </div>
      <?php endforeach; ?>

      <?php if (!\mara\core\User::isAdmin()): ?>
        <p><?= $escape(LANG['IMG_SERVER_ADMIN_ONLY']) ?></p>
      <?php endif; ?>
    </section>

    <section id="tab-gallery" class="settings-tab-panel" hidden>
      <button type="button" class="image-button" id="gallery-refresh">
        <?= $escape(LANG['IMG_REFRESH']) ?>
      </button>
      <p id="gallery-status" role="status" aria-live="polite"></p>
      <div id="gallery" class="main-gallery-grid"></div>
    </section>
  </section>
</main>
<?php require DIR_TPL . '/modals.tpl.php'; ?>
</body>
</html>
