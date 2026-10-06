<script>
const providerData = <?= json_encode(
    $providerdata,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;

const voiceData = <?= json_encode(
    $voices,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;
</script>

        <section class="settings-page">
          <form method="post" action="<?= DIR_HOST ?>/main/settingssave"> 
          <header class="settings-header">
            <div>
              <h1><?php echo LANG['SETTINGS']; ?></h1>
              <p><?php echo LANG['SETTINGS_TITLE']; ?></p>
            </div>
            <button type="submit" class="btn btn-primary">
                <?php echo LANG['SAVE']; ?>
            </button>
          </header>

          <nav class="settings-tabs">
            <button type="button"
              class="settings-tab active"
              data-tab="system">
              <?php echo LANG['SYSTEM']; ?>
            </button>

            <button type="button"
              class="settings-tab"
              data-tab="voice">
              <?php echo LANG['SETTINGS_VOICE']; ?>
            </button>

            <button type="button"
              class="settings-tab"
              data-tab="voices">
              <?php echo LANG['SETTINGS_VOICES']; ?>
            </button>            

            <button type="button"
              class="settings-tab"
              data-tab="stt">
              <?php echo LANG['SPEECH_RECOGNITION']; ?>
            </button>
            <?php if (!empty($logSources)): ?>
            <button type="button" class="settings-tab" data-tab="logs">Naplók</button>
            <?php endif; ?>
          </nav>

          <div class="settings-content">
<!-- ==========================================================
      RENDSZER
      ========================================================== -->
          <section class="settings-panel active" id="settings-system">
            <?php if (\mara\core\User::isAdmin()): ?>
            <div class="settings-section" id="gpu-system">
              <div class="side-option" style="display:flex;justify-content:space-between;align-items:center">
                <h2>GPU / VRAM figyelés</h2>
                <label class="switch"><input type="checkbox" id="gpu-enabled" aria-label="GPU figyelés"><span class="switch-slider"></span></label>
              </div>
              <div id="gpu-details" hidden></div>
            </div>
            <?php endif; ?>

            <div class="settings-section">
              <div class="settings-section-title">
                <h2><?php echo LANG['SETTINGS_GENERAL']; ?></h2>
              </div>
              <div class="settings-field">
                <label for="language"><?php echo LANG['SETTINGS_LANGUAGE']; ?></label>
                <select id="language" name="language">
                  <?php echo $slanguages; ?>
                </select>
              </div>
            </div>
            <div class="settings-section">
              <div class="settings-section-title">
                <h2><?php echo LANG['SETTINGS_MMANAGER']; ?></h2>
                  <p>
                    <?php echo LANG['SETTINGS_MSERVER']; ?>
                  </p>
              </div>
              <input
                  type="hidden"
                  id="provider_data"
                  name="provider_data"
                  value="">              
              <div class="settings-field">
                <label for="model-provider">
                  <?php echo LANG['SETTINGS_MMANAGER']; ?>
                </label>
                <select id="model-provider" name="model_provider">
                  <?php echo $sproviders; ?>
                </select>
              </div>
              <div class="settings-field">
                <label for="provider_url">
                  <?php echo LANG['SETTINGS_PROVIDER_URL']; ?>
                </label>
                <input type="text"
                      id="provider_url"
                      name="provider_url"
                      value="<?php echo $provider_url; ?>" 
                      placeholder="<?php echo LANG['SETTINGS_PROVIDER_PH']; ?>">
              </div>
              <div class="settings-provider-fields"
                  data-provider="llamacpp"
                  hidden>
                <div class="settings-field">
                  <label for="llamacpp_model_dir">
                    <?php echo LANG['SETTINGS_MODEL_URL']; ?>
                  </label>
                  <input type="text"
                    id="llamacpp_model_dir"
                    name="llamacpp_model_dir"
                    value="<?php echo $llamacpp_model_dir; ?>"
                    placeholder="<?php echo LANG['SETTINGS_MODEL_URL_PH']; ?>">
                </div>
              </div>

              <div class="settings-provider-actions">
                <button
                    type="button"
                    class="btn btn-dark"
                    id="provider-test">
                    <?php echo LANG['SETTINGS_CONNECTION_TEST']; ?>
                </button>
                <span class="provider-status" id="provider-status"></span>
              </div>

              </div>


            <div class="settings-section" id="image-backend-settings">
              <div class="settings-section-title">
                <h2><?= LANG['SETTINGS_IMAGE_TITLE'] ?></h2>
                <p><?= LANG['SETTINGS_IMAGE_INFO'] ?></p>
              </div>

              <div class="settings-field">
                <label for="image-backend"><?= LANG['SETTINGS_IMAGE_BACKEND'] ?></label>
                <select id="image-backend" name="image_backend">
                  <option value="qwen2"
                    <?= \mara\core\App::get('system.image_backend') === 'qwen2' ? 'selected' : '' ?>>
                    Qwen Image 2.1
                  </option>
                  <option value="forge"
                    <?= \mara\core\App::get('system.image_backend') === 'forge' ? 'selected' : '' ?>>
                    Forge
                  </option>
                </select>
              </div>

              <div class="settings-field">
                <label for="qwen2-url"><?= LANG['SETTINGS_IMAGE_QWEN_URL'] ?></label>
                <input type="url"
                  id="qwen2-url"
                  name="qwen2_url"
                  required
                  value="<?= htmlspecialchars(
                      (string)\mara\core\App::get('system.qwen2_url', ''),
                      ENT_QUOTES,
                      'UTF-8'
                  ) ?>"
                  placeholder="http://127.0.0.1:7866">
              </div>

              <div class="settings-field">
                <label for="forge-url"><?= LANG['SETTINGS_IMAGE_FORGE_URL'] ?></label>
                <input type="url"
                  id="forge-url"
                  name="forge_url"
                  required
                  value="<?= htmlspecialchars(
                      (string)\mara\core\App::get('system.forge_url', ''),
                      ENT_QUOTES,
                      'UTF-8'
                  ) ?>"
                  placeholder="http://127.0.0.1:7861">
              </div>
            </div>

</section>


<!-- ==========================================================
     HANG / TTS
     ========================================================== -->

          <section class="settings-panel" id="settings-voice" hidden>
            <div class="settings-section">
              <div class="settings-section-title">
                  <h2><?php echo LANG['SETTINGS_VOICE_PROVIDERS']; ?></h2>
                  <p>
                    <?php echo LANG['SETTINGS_VOICE_PROVIDERS_TITLE']; ?>
                  </p>
              </div>
<!-- ==================================================
    XTTS
    ================================================== -->
              <div class="voice-provider">
                <div class="voice-provider-header">
                  <div class="voice-provider-name">
                    <strong>XTTS</strong>
                    <span
                      class="service-status"
                      id="xtts-service-status"
                      data-running="<?= LANG['SETTINGS_RUNNING'] ?>"
                      data-stopped="<?= LANG['SETTINGS_STOPPED'] ?>">
                      <span class="status-dot"></span>
                      <span class="status-text">...</span>
                    </span>
                  </div>
                  <span class="voice-provider-service">
                    <?= htmlspecialchars($tts['xtts']['service'] ?? '') ?>
                  </span>
                </div>

                <div class="voice-provider-body">
                  <div class="settings-field">
                    <label for="xtts-url">
                      <?php echo LANG['SETTINGS_SERVER_ADDR']; ?>
                    </label>
                    <input
                        type="text"
                        name="tts_data[xtts][url]"
                        value="<?= htmlspecialchars($tts['xtts']['url'] ?? '') ?>">

                  </div>

                  <div class="settings-field">
                    <label for="xtts-voices">
                      <?php echo LANG['SETTINGS_VOICE_DIR']; ?>
                    </label>
                    <input
                        type="text"
                        name="tts_data[xtts][voice_dir]"
                        value="<?= htmlspecialchars($tts['xtts']['voice_dir'] ?? '') ?>">
                  </div>

                  <div class="voice-provider-actions">
                    <button
                        type="button"
                        class="btn btn-dark"
                        id="xtts-test">
                        <?= LANG['SETTINGS_CONNECTION_TEST'] ?>
                    </button>
                    <span
                        class="provider-status"
                        id="xtts-status">
                    </span>
                  </div>

                </div>
<!-- ============================================================
     PIPER
     ============================================================ -->
                <div class="voice-provider">
                  <div class="voice-provider-header">
                    <div class="voice-provider-name">
                      <strong>Piper</strong>
                        <span
                          class="service-status"
                          id="piper-service-status"
                          data-running="<?= LANG['SETTINGS_AVAILABLE'] ?>"
                          data-stopped="<?= LANG['SETTINGS_NOT_AVAILABLE'] ?>">
                          <span class="status-dot"></span>
                          <span class="status-text">...</span>
                        </span>
                    </div>
                  </div>

                  <div class="voice-provider-body">
                    <div class="settings-field">
                      <label>
                        <?= LANG['SETTINGS_BINARY'] ?>
                      </label>
                      <input
                          type="text"
                          name="tts_data[piper][binary]"
                          value="<?= htmlspecialchars(
                              $tts['piper']['binary'] ?? ''
                          ) ?>">
                    </div>

                    <div class="settings-field">
                      <label>
                        <?= LANG['SETTINGS_MODEL_URL'] ?>
                      </label>
                      <input
                          type="text"
                          name="tts_data[piper][model_dir]"
                          value="<?= htmlspecialchars(
                              $tts['piper']['model_dir'] ?? ''
                          ) ?>">
                    </div>

                    <div class="voice-provider-actions">
                      <button
                          type="button"
                          class="btn btn-dark"
                          id="piper-test">
                          <?= LANG['SETTINGS_CONNECTION_TEST'] ?>
                      </button>
                      <span
                          class="provider-status"
                          id="piper-status"></span>
                    </div>                   

                  </div>
                </div> 
<!-- ============================================================
     ESPEAK
     ============================================================ -->
                <div class="voice-provider">
                  <div class="voice-provider-header">
                    <div class="voice-provider-name">
                      <strong>eSpeak</strong>
                      <span
                        class="service-status"
                        id="espeak-service-status"
                        data-running="<?= LANG['SETTINGS_AVAILABLE'] ?>"
                        data-stopped="<?= LANG['SETTINGS_NOT_AVAILABLE'] ?>">
                        <span class="status-dot"></span>
                        <span class="status-text">...</span>
                      </span>
                    </div>
                  </div>
                  <div class="voice-provider-body">
                    <div class="settings-field">
                      <label>
                        <?= LANG['SETTINGS_BINARY'] ?>
                      </label>
                      <input
                          type="text"
                          name="tts_data[espeak][binary]"
                          value="<?= htmlspecialchars(
                              $tts['espeak']['binary'] ?? ''
                          ) ?>">
                    </div>
                    <div class="settings-field">
                      <label>
                        <?= LANG['SETTINGS_VOICE'] ?>
                      </label>
                      <input
                          type="text"
                          name="tts_data[espeak][voice]"
                          value="<?= htmlspecialchars(
                              $tts['espeak']['voice'] ?? ''
                          ) ?>">
                    </div>
                    <div class="settings-field">
                      <label>
                          <?= LANG['SETTINGS_PITCH'] ?>
                      </label>
                      <input
                          type="number"
                          name="tts_data[espeak][pitch]"
                          value="<?= htmlspecialchars(
                              $tts['espeak']['pitch'] ?? '70'
                          ) ?>">
                    </div>
                    <div class="settings-field">
                      <label>
                        <?= LANG['SETTINGS_SPEED'] ?>
                      </label>
                      <input
                          type="number"
                          name="tts_data[espeak][speed]"
                          value="<?= htmlspecialchars(
                              $tts['espeak']['speed'] ?? '100'
                          ) ?>">
                    </div>
                    <div class="settings-field">
                      <label></label>
                      <div>
                        <button
                          type="button"
                          class="btn btn-dark"
                          id="espeak-test">
                          <?= LANG['SETTINGS_CONNECTION_TEST'] ?>
                        </button>
                        <span
                          class="provider-status"
                          id="espeak-status"></span>
                      </div>
                    </div>
                  </div>
                </div>                
                


              </div>
            </div>
          </section>

<!-- ==========================================================
     MODELL HANGOK
     ========================================================== -->

          <section class="settings-panel" id="settings-voices" hidden>
            <div class="settings-section">
              <div class="settings-section-title">
                  <h2><?php echo LANG['SETTINGS_VOICES']; ?></h2>
                  <p>
                    <?php echo LANG['SETTINGS_VOICES_TITLE']; ?>
                  </p>
              </div>
              <div class="voice-provider">  
                <div class="voice-list-header">
                  <button
                      type="button"
                      class="voice-add"
                      id="voice-add"
                  >
                    + <?php echo LANG['SETTINGS_VOICE_NEW']; ?>
                  </button>
                </div>                 
                <div class="voice-list">
                  <?php foreach ($voices as $voice): ?>
                    <div
                        class="voice-item"
                        data-id="<?php echo (int)$voice['id']; ?>"
                    >
                      <span class="voice-name">
                        <?php echo htmlspecialchars($voice['name']); ?>
                      </span>

                      <button
                          type="button"
                          class="voice-delete"
                          data-id="<?php echo (int)$voice['id']; ?>"
                          title="<?php echo LANG['DELETE']; ?>"
                      >
                        ✕
                      </button>
                    </div>
                  <?php endforeach; ?>
                </div>
<!-- --------------------------------------------------------------- -->
                <div class="voice-editor" id="voice-editor" hidden>
                  <div class="voice-editor-header">
                    <h3 id="voice-editor-title">
                      <?php echo LANG['SETTINGS_VOICE_EDIT']; ?>
                    </h3>
                  </div>
                  <input
                      type="hidden"
                      id="voice-id"
                      value=""
                  >
                  <div class="settings-field">
                    <label for="voice-name">
                      <?php echo LANG['SETTINGS_VOICE_NAME']; ?>
                    </label>
                    <input
                        type="text"
                        id="voice-name"
                        autocomplete="off"
                    >
                  </div>
                  <div class="settings-field">
                    <label for="voice-provider">
                      <?php echo LANG['SETTINGS_PROVIDER']; ?>
                    </label>
                    <select id="voice-provider">
                      <?php foreach ($voiceproviders as $provider): ?>
                        <option value="<?php echo htmlspecialchars($provider); ?>">
                          <?php echo htmlspecialchars(strtoupper($provider)); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div
                      class="voice-provider-fields"
                      id="voice-provider-fields"
                  >
<!-- XTTS -------------------------------------------------------- -->
                    <div
                        class="voice-provider-group"
                        id="voice-fields-xtts"
                        hidden
                    >
                      <div class="settings-field">
                        <label for="voice-sample">
                          <?php echo LANG['SETTINGS_VOICE_SAMPLE']; ?>
                        </label>

                        <input
                            type="text"
                            id="voice-sample"
                            autocomplete="off"
                        >
                      </div>

                      <div class="settings-field">
                        <label for="voice-reftext">
                          <?php echo LANG['SETTINGS_VOICE_REFTEXT']; ?>
                        </label>

                        <textarea
                            id="voice-reftext"
                            rows="3"
                        ></textarea>
                      </div>
                      <!-- SPEED -->
                      <div class="parameter-item range-group">
                        <div class="parameter-header">
                          <div>
                            <label for="voice-xtts-speed">
                              <?php echo LANG['SETTINGS_VOICE_SPEED']; ?>
                            </label>
                          </div>
                          <input
                              type="number"
                              class="range-number"
                              id="voice-xtts-speed-number"
                              min="0.75"
                              max="1.25"
                              step="0.01"
                              value="1.00"
                          >
                        </div>

                        <input
                            type="range"
                            id="voice-xtts-speed"
                            min="0.75"
                            max="1.25"
                            step="0.01"
                            value="1.00"
                        >
                      </div>
                      <!-- TEMPERATURE -->
                      <div class="parameter-item range-group">
                        <div class="parameter-header">
                          <div>
                            <label for="voice-xtts-temperature">
                              <?php echo LANG['SETTINGS_VOICE_TEMPERATURE']; ?>
                            </label>
                          </div>
                          <input
                              type="number"
                              class="range-number"
                              id="voice-xtts-temperature-number"
                              min="0.1"
                              max="1.0"
                              step="0.05"
                              value="0.65"
                          >
                        </div>

                        <input
                            type="range"
                            id="voice-xtts-temperature"
                            min="0.1"
                            max="1.0"
                            step="0.05"
                            value="0.65"
                        >
                      </div>                      
                    </div>
<!-- Piper -------------------------------------------------------- -->
                    <div
                        class="voice-provider-group"
                        id="voice-fields-piper"
                        hidden
                    >
                      <div class="settings-field">
                        <label for="voice-sample-piper">
                          <?php echo LANG['SETTINGS_VOICE_MODEL']; ?>
                        </label>

                        <input
                            type="text"
                            id="voice-sample-piper"
                            autocomplete="off"
                        >
                      </div>
                      <!-- LENGTH SCALE -->
                      <div class="parameter-item range-group">
                        <div class="parameter-header">
                          <div>
                            <label for="voice-piper-length-scale">
                              <?php echo LANG['SETTINGS_VOICE_LENGTH_SCALE']; ?>
                            </label>
                          </div>
                          <input
                              type="number"
                              class="range-number"
                              id="voice-piper-length-scale-number"
                              min="0.5"
                              max="2.0"
                              step="0.05"
                              value="1.00"
                          >
                        </div>

                        <input
                            type="range"
                            id="voice-piper-length-scale"
                            min="0.5"
                            max="2.0"
                            step="0.05"
                            value="1.00"
                        >
                      </div>
                      <!-- NOISE W -->
                      <div class="parameter-item range-group">
                        <div class="parameter-header">
                          <div>
                            <label for="voice-piper-noise-w">
                              <?php echo LANG['SETTINGS_VOICE_NOISE_W']; ?>
                            </label>
                          </div>
                          <input
                              type="number"
                              class="range-number"
                              id="voice-piper-noise-w-number"
                              min="0.0"
                              max="1.5"
                              step="0.05"
                              value="0.80"
                          >
                        </div>
                        <input
                            type="range"
                            id="voice-piper-noise-w"
                            min="0.0"
                            max="1.5"
                            step="0.05"
                            value="0.80"
                        >
                      </div>                      
                    </div>
<!-- ESpeak ------------------------------------------------------- -->
                    <div
                        class="voice-provider-group"
                        id="voice-fields-espeak"
                        hidden
                    >
                      <div class="settings-field">
                        <label for="voice-espeak-voice">
                          <?php echo LANG['SETTINGS_VOICE_ESPEAK_VOICE']; ?>
                        </label>

                        <input
                            type="text"
                            id="voice-espeak-voice"
                            autocomplete="off"
                            value="mb-hu1"
                        >
                      </div>

                      <!-- PITCH -->
                      <div class="parameter-item range-group">

                        <div class="parameter-header">
                          <div>
                            <label for="voice-espeak-pitch">
                              <?php echo LANG['SETTINGS_PITCH']; ?>
                            </label>
                          </div>

                          <input
                              type="number"
                              class="range-number"
                              id="voice-espeak-pitch-number"
                              min="0"
                              max="99"
                              step="1"
                              value="70"
                          >
                        </div>

                        <input
                            type="range"
                            id="voice-espeak-pitch"
                            min="0"
                            max="99"
                            step="1"
                            value="70"
                        >
                      </div>

                      <!-- SPEED -->
                      <div class="parameter-item range-group">

                        <div class="parameter-header">
                          <div>
                            <label for="voice-espeak-speed">
                              <?php echo LANG['SETTINGS_SPEED']; ?>
                            </label>
                          </div>

                          <input
                              type="number"
                              class="range-number"
                              id="voice-espeak-speed-number"
                              min="80"
                              max="450"
                              step="1"
                              value="100"
                          >
                        </div>

                        <input
                            type="range"
                            id="voice-espeak-speed"
                            min="80"
                            max="450"
                            step="1"
                            value="100"
                        >
                      </div>
                    </div>
<!-- -------------------------------------------------------------- -->
                  </div>
                  <div class="voice-editor-actions">
                    <button
                        type="button"
                        class="btn btn-primary"
                        id="voice-save"
                    >
                      <?php echo LANG['SAVE']; ?>
                    </button>
                  </div>
                </div>                
<!-- --------------------------------------------------------------- -->
              </div>
            </div>
          </section>              


<!-- ==========================================================
     BESZÉDFELISMERÉS / STT
     ========================================================== -->

          <section class="settings-panel"
                  id="settings-stt"
                  hidden>

            <div class="settings-section">

              <div class="settings-section-title">
                <h2><?php echo LANG['SETTINGS_STT']; ?></h2>
                <p><?php echo LANG['SETTINGS_STT_TITLE']; ?></p>
              </div>

              <!-- STT PROVIDER -->
              <div class="settings-field">
                <label for="stt-provider">
                  <?php echo LANG['SETTINGS_STT_PROVIDER']; ?>
                </label>

                <select id="stt-provider"
                        name="stt_provider">

                  <option value="browser"
                    <?php echo (($stt['provider'] ?? 'browser') === 'browser') ? 'selected' : ''; ?>>
                    Browser
                  </option>

                  <option value="whisper"
                    <?php echo (($stt['provider'] ?? '') === 'whisper') ? 'selected' : ''; ?>>
                    Whisper
                  </option>

                </select>
              </div>


              <!-- ==================================================
                  BROWSER
                  ================================================== -->

              <div class="stt-provider"
                  data-stt-provider="browser">

                <div class="stt-provider-header">

                  <div class="stt-provider-name">

                    <strong>Browser</strong>

                    <span class="service-status running">
                      <span class="service-status-dot"></span>
                      <?php echo LANG['SETTINGS_AVAILABLE']; ?>
                    </span>

                  </div>

                </div>

                <div class="stt-provider-body">

                  <p class="stt-provider-info">
                    <?php echo LANG['SETTINGS_STT_BROWSER_INFO']; ?>
                  </p>

                </div>

              </div>


              <!-- ==================================================
                  WHISPER
                  ================================================== -->

              <div class="stt-provider"
                  data-stt-provider="whisper"
                  hidden>

                <div class="stt-provider-header">

                  <div class="stt-provider-name">

                    <strong>Whisper</strong>

                    <span class="service-status stopped"
                          id="whisper-status">

                      <span class="service-status-dot"></span>

                      <span id="whisper-status-text">
                        <?php echo LANG['SETTINGS_STOPPED']; ?>
                      </span>

                    </span>

                  </div>

                  <div class="stt-provider-actions">

                    <button type="button"
                            class="btn btn-dark"
                            id="whisper-test">
                      <?php echo LANG['SETTINGS_TEST_SERVER']; ?>
                    </button>

                  </div>                  

                </div>


                <div class="stt-provider-body">

                  <!-- WHISPER DIRECTORY -->
                  <div class="settings-field">

                    <label for="whisper-dir">
                      <?php echo LANG['SETTINGS_STT_WHISPER_DIR']; ?>
                    </label>

                    <input type="text"
                          id="whisper-dir"
                          name="whisper_dir"
                          value="<?php echo htmlspecialchars($stt['whisper']['dir'] ?? ''); ?>">

                  </div>


                  <!-- HOST -->
                  <div class="settings-field">

                    <label for="whisper-host">
                      <?php echo LANG['SETTINGS_HOST']; ?>
                    </label>

                    <input type="text"
                          id="whisper-host"
                          name="whisper_host"
                          value="<?php echo htmlspecialchars($stt['whisper']['host'] ?? '127.0.0.1'); ?>">

                  </div>


                  <!-- PORT -->
                  <div class="settings-field">

                    <label for="whisper-port">
                      <?php echo LANG['SETTINGS_PORT']; ?>
                    </label>

                    <input type="number"
                          id="whisper-port"
                          name="whisper_port"
                          value="<?php echo (int)($stt['whisper']['port'] ?? 8000); ?>">

                  </div>


                  <!-- MODEL -->
                  <div class="settings-field">

                    <label for="whisper-model">
                      <?php echo LANG['SETTINGS_MODEL']; ?>
                    </label>

                    <input type="text"
                          id="whisper-model"
                          name="whisper_model"
                          value="<?php echo htmlspecialchars($stt['whisper']['model'] ?? ''); ?>">

                  </div>


                  <!-- LANGUAGE -->
                  <div class="settings-field">

                    <label for="whisper-language">
                      <?php echo LANG['SETTINGS_LANGUAGE']; ?>
                    </label>

                    <input type="text"
                          id="whisper-language"
                          name="whisper_language"
                          value="<?php echo htmlspecialchars($stt['whisper']['language'] ?? 'hu'); ?>">

                  </div>


                  <!-- TEMPERATURE -->
                  <div class="parameter-item range-group">

                    <div class="parameter-header">

                      <label for="whisper-temperature">
                        <?php echo LANG['SETTINGS_STT_TEMPERATURE']; ?>
                      </label>

                      <input type="number"
                            class="range-number"
                            id="whisper-temperature-number"
                            min="0"
                            max="1"
                            step="0.05"
                            value="<?php echo htmlspecialchars($stt['whisper']['temperature'] ?? '0'); ?>">

                    </div>

                    <input type="range"
                          id="whisper-temperature"
                          name="whisper_temperature"
                          min="0"
                          max="1"
                          step="0.05"
                          value="<?php echo htmlspecialchars($stt['whisper']['temperature'] ?? '0'); ?>">

                  </div>


                  <!-- TEMPERATURE FALLBACK -->
                  <div class="parameter-item range-group">

                    <div class="parameter-header">

                      <label for="whisper-temperature-inc">
                        <?php echo LANG['SETTINGS_STT_TEMPERATURE_INC']; ?>
                      </label>

                      <input type="number"
                            class="range-number"
                            id="whisper-temperature-inc-number"
                            min="0"
                            max="1"
                            step="0.05"
                            value="<?php echo htmlspecialchars($stt['whisper']['temperature_inc'] ?? '0.2'); ?>">

                    </div>

                    <input type="range"
                          id="whisper-temperature-inc"
                          name="whisper_temperature_inc"
                          min="0"
                          max="1"
                          step="0.05"
                          value="<?php echo htmlspecialchars($stt['whisper']['temperature_inc'] ?? '0.2'); ?>">

                  </div>


                  <!-- BEST OF -->
                  <div class="parameter-item range-group">

                    <div class="parameter-header">

                      <label for="whisper-best-of">
                        <?php echo LANG['SETTINGS_STT_BEST_OF']; ?>
                      </label>

                      <input type="number"
                            class="range-number"
                            id="whisper-best-of-number"
                            min="1"
                            max="10"
                            step="1"
                            value="<?php echo (int)($stt['whisper']['best_of'] ?? 2); ?>">

                    </div>

                    <input type="range"
                          id="whisper-best-of"
                          name="whisper_best_of"
                          min="1"
                          max="10"
                          step="1"
                          value="<?php echo (int)($stt['whisper']['best_of'] ?? 2); ?>">

                  </div>


                  <!-- BEAM SIZE -->
                  <div class="parameter-item range-group">

                    <div class="parameter-header">

                      <label for="whisper-beam-size">
                        <?php echo LANG['SETTINGS_STT_BEAM_SIZE']; ?>
                      </label>

                      <input type="number"
                            class="range-number"
                            id="whisper-beam-size-number"
                            min="-1"
                            max="10"
                            step="1"
                            value="<?php echo (int)($stt['whisper']['beam_size'] ?? -1); ?>">

                    </div>

                    <input type="range"
                          id="whisper-beam-size"
                          name="whisper_beam_size"
                          min="-1"
                          max="10"
                          step="1"
                          value="<?php echo (int)($stt['whisper']['beam_size'] ?? -1); ?>">

                  </div>


                  <!-- NO SPEECH THRESHOLD -->
                  <div class="parameter-item range-group">

                    <div class="parameter-header">

                      <label for="whisper-no-speech-thold">
                        <?php echo LANG['SETTINGS_STT_NO_SPEECH']; ?>
                      </label>

                      <input type="number"
                            class="range-number"
                            id="whisper-no-speech-thold-number"
                            min="0"
                            max="1"
                            step="0.05"
                            value="<?php echo htmlspecialchars($stt['whisper']['no_speech_thold'] ?? '0.6'); ?>">

                    </div>

                    <input type="range"
                          id="whisper-no-speech-thold"
                          name="whisper_no_speech_thold"
                          min="0"
                          max="1"
                          step="0.05"
                          value="<?php echo htmlspecialchars($stt['whisper']['no_speech_thold'] ?? '0.6'); ?>">

                  </div>


                <!-- USE CONTEXT -->
                <div class="settings-field">

                  <label for="whisper-use-context">
                    <?php echo LANG['SETTINGS_STT_USE_CONTEXT']; ?>
                  </label>

                  <div class="model-switch-control">
                    <label class="switch">

                      <input type="checkbox"
                            id="whisper-use-context"
                            name="whisper_use_context"
                            value="1"
                            <?php echo !empty($stt['whisper']['use_context']) ? 'checked' : ''; ?>>

                      <span class="switch-slider"></span>

                    </label>
                  </div>

                </div>

                </div>

              </div>

            </div>

          </section>

<?php if (!empty($logSources)): ?>
<section class="settings-panel" id="settings-logs" hidden>
  <div class="settings-section">
    <h2>Naplók</h2>
    <div style="display:flex;flex-wrap:wrap;gap:18px;margin:16px 0">
    <?php $firstLog = true; foreach ($logSources as $logId => $logSource): ?>
      <label style="display:flex;align-items:center;gap:8px">
        <span class="switch"><input type="radio" name="log_view_source" value="<?= htmlspecialchars($logId, ENT_QUOTES, 'UTF-8') ?>" <?= $firstLog ? 'checked' : '' ?>><span class="switch-slider"></span></span>
        <?= htmlspecialchars($logSource['label'], ENT_QUOTES, 'UTF-8') ?>
      </label>
    <?php $firstLog = false; endforeach; ?>
    </div>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
      <button type="button" class="btn btn-primary" id="logs-refresh">Frissítés</button>
      <button type="button" class="btn" id="logs-copy">Másolás</button>
      <label style="display:flex;align-items:center;gap:8px"><span class="switch"><input type="checkbox" id="logs-auto"><span class="switch-slider"></span></span>Automatikus frissítés (5 s)</label>
    </div>
    <p id="logs-status" role="status" aria-live="polite"></p>
    <pre style="background:#111;color:#ddd;height:420px;max-width:100%;overflow:auto;padding:16px;border-radius:8px;white-space:pre;font:12px/1.6 monospace" tabindex="0" aria-label="Napló tartalma"><code id="logs-content"></code></pre>
  </div>
</section>
<?php endif; ?>
        </div>
        </form>
      </section>
      