<!-- ==========================================================
     MODEL EDITOR
     ========================================================== -->
<main class="app-content">
<section class="model-editor">

<!-- ======================================================
      LEFT / PORTRAIT
      ====================================================== -->

    <aside class="model-editor-side">

        <div class="model-editor-image">

            <button
                type="button"
                class="model-image-delete"
                title="<?php echo htmlspecialchars(LANG['MODEL_IMAGE_DELETE']); ?>"
                aria-label="<?php echo htmlspecialchars(LANG['MODEL_IMAGE_DELETE']); ?>">
                ×
            </button>

            <button
                type="button"
                class="model-image-button"
                title="<?php echo htmlspecialchars(LANG['MODEL_IMAGE_SELECT']); ?>" onclick="addImage('modelImageModal')">

                <img
                    id="model-avatar" 
                    src="<?php echo DIR_HOST.'/assets/img/models/'.htmlspecialchars($image ?? 'noimage.png'); ?>"
                    alt="<?php echo htmlspecialchars($name ?? ''); ?>"
                    class="model-editor-avatar">

            </button>

        </div>

        <p class="model-image-help">
            <?php echo LANG['MODEL_IMAGE_HELP']; ?>
        </p>

    </aside>


    <!-- ======================================================
         MAIN
         ====================================================== -->

    <div class="model-editor-main">

        <form class="model-editor-form" method="post" action="<?php echo DIR_HOST; ?>/main/savemodel">

            <input type="hidden" name="id" value="<?php echo (int)($id ?? 0); ?>">
            <input type="hidden" name="image_path" id="hidden-image-input" value="<?php echo $image; ?>">
            <input
                type="hidden"
                id="modelinfo"
                name="modelinfo"
                value="<?php echo htmlspecialchars(json_encode($modelinfo ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">

            <!-- HEADER -->

            <header class="model-editor-header">

                <div>
                    <h1 class="model-editor-title">
                        <?php echo htmlspecialchars($name ?? ''); ?>
                    </h1>

                    <p class="model-editor-description">
                        <?php echo htmlspecialchars($name ?? ''); ?> <?php echo LANG['MODEL_SETTINGS_SUFFIX']; ?>
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary">
                    <?php echo LANG['SAVE']; ?>
                </button>

            </header>


            <!-- ==================================================
                 TABS
                 ================================================== -->

            <nav class="settings-tabs model-tabs">

                <button
                    type="button"
                    class="settings-tab active"
                    data-model-tab="general">
                    <?php echo LANG['MODEL_TAB_GENERAL']; ?>
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-model-tab="behavior">
                    <?php echo LANG['MODEL_TAB_BEHAVIOR']; ?>
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-model-tab="parameters">
                    <?php echo LANG['MODEL_TAB_PARAMETERS']; ?>
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-model-tab="capabilities">
                    <?php echo LANG['MODEL_TAB_CAPABILITIES']; ?>
                </button>

                <button
                    type="button"
                    class="settings-tab"
                    data-model-tab="rag">
                    RAG
                </button>

            </nav>


            <!-- ==================================================
                 GENERAL
                 ================================================== -->

            <section
                class="model-tab-panel"
                data-model-panel="general">

                <div class="settings-section">

                    <div class="settings-section-title">
                        <h2><?php echo LANG['MODEL_TAB_GENERAL']; ?></h2>
                        <p>
                            <?php echo LANG['MODEL_GENERAL_HELP']; ?>
                        </p>
                    </div>


                    <!-- NAME -->

                    <div class="settings-field">

                        <label for="model-name">
                            <?php echo LANG['NAME']; ?>
                        </label>

                        <input
                            type="text"
                            id="model-name"
                            name="name"
                            value="<?php echo htmlspecialchars($name ?? ''); ?>"
                            placeholder="<?php echo htmlspecialchars(LANG['MODEL_NAME_PLACEHOLDER']); ?>">

                    </div>


                    <!-- PROVIDER -->

                    <div class="settings-field">

                        <label for="model-provider">
                            <?php echo LANG['MODEL_PROVIDER']; ?>
                        </label>

                        <select
                            id="model-provider"
                            name="provider">

                            <?php foreach (PROVIDERS as $providerName) { ?>
                              <option
                                value="<?php echo htmlspecialchars($providerName); ?>"
                                <?php echo (($provider ?? '') === $providerName) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($providerName); ?>
                              </option>
                            <?php } ?>

                        </select>

                    </div>


                                        <!-- BASE MODEL -->

                    <div class="settings-field">

                        <label for="base-model">
                            <?php echo LANG['MODEL_BASE_MODEL']; ?>
                        </label>

                        <select
                            id="base-model"
                            name="basemodel">

                            <option value="<?php echo htmlspecialchars($basemodel ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($basemodel ?? ''); ?>
                            </option>

                        </select>

                    </div>

                    <!-- MMPROJ -->

                    <div class="settings-field">

                        <label for="model-mmproj">
                            MMProj
                        </label>

                        <input
                            type="text"
                            id="model-mmproj"
                            name="mmproj"
                            value="<?php echo htmlspecialchars($mmproj ?? ''); ?>"
                            placeholder="mmproj model path">

                    </div>
                                        <!-- MODEL INFORMATION -->
                    <div class="model-base-info-row">
                      <div class="model-base-info">

                          <div class="model-base-info-header">
                              <?php echo LANG['MODEL_BASE_MODEL']; ?> tulajdonságai
                          </div>

                          <div class="model-base-properties">

                              <div>
                                  <span><?php echo LANG['MODEL_PARAMETERS']; ?></span>
                                  <strong id="modelinfo-size"><?php echo htmlspecialchars($modelinfo['size'] ?? '-'); ?></strong>
                              </div>

                              <div>
                                  <span><?php echo LANG['MODEL_QUANTIZATION']; ?></span>
                                  <strong id="modelinfo-quantization"><?php echo htmlspecialchars($modelinfo['quantization'] ?? '-'); ?></strong>
                              </div>

                              <div>
                                  <span>Context</span>
                                  <strong id="modelinfo-context">
                                    <?php
                                      echo !empty($modelinfo['context'])
                                        ? number_format((int)$modelinfo['context'], 0, '', ' ')
                                        : '-';
                                    ?>
                                  </strong>
                              </div>

                          </div>

                          <?php
                            $baseCapabilities = [];

                            if (!empty($modelinfo['vision']))   $baseCapabilities[] = 'Vision';
                            if (!empty($modelinfo['video']))    $baseCapabilities[] = 'Video';
                            if (!empty($modelinfo['audio']))    $baseCapabilities[] = 'Audio';
                            if (!empty($modelinfo['tools']))    $baseCapabilities[] = 'Tools';
                            if (!empty($modelinfo['thinking'])) $baseCapabilities[] = 'Thinking';
                          ?>

                            <div class="model-base-capabilities" id="modelinfo-capabilities">
                          <?php if (empty($baseCapabilities) && (($modelinfo['capabilities_known'] ?? (($provider ?? '') === 'llamacpp' ? false : true)) === false)) { ?>
                              <span>Capabilities not detected</span>
                          <?php } ?>
                          <?php if (!empty($baseCapabilities)) { ?>
                              <?php foreach ($baseCapabilities as $capability) { ?>
                                <span><?php echo $capability; ?></span>
                              <?php } ?>
                          <?php } ?>
                            </div>

                      </div>
                    </div>


                                        <!-- NOTE -->

                    <div class="settings-field settings-field-textarea">

                        <label for="model-note">
                            <?php echo LANG['NOTE']; ?>
                        </label>

                        <textarea
                            id="model-note"
                            name="note"
                            rows="3"
                            placeholder="<?php echo htmlspecialchars(LANG['MODEL_NOTE_PLACEHOLDER']); ?>"><?php echo htmlspecialchars($note ?? ''); ?></textarea>

                    </div>


                    <!-- VOICE -->

                    <div class="settings-field">

                        <label for="voice-id">
                            <?php echo LANG['VOICE']; ?>
                        </label>

                        <select
                            id="voice-id"
                            name="voice_id">
                            <option value="0">
                                <?php echo LANG['NO_VOICE']; ?>
                            </option>
                            <?php echo $voiceselect; ?>
                        </select>

                    </div>


                    <!-- PRIVATE / OWN MODEL -->
                    <div class="settings-field model-private-field">
                        <label>
                            <?php echo LANG['MODEL_PRIVATE']; ?>
                        </label>

                        <div class="model-private-control">

                            <label class="switch">
                                <input
                                    type="checkbox"
                                    name="private_model"
                                    value="1"
                                    <?php echo ((int)($user_id ?? 0) > 0) ? 'checked' : ''; ?>>
                                <span class="switch-slider"></span>
                            </label>

                            <span class="model-private-description">
                                <?php echo LANG['MODEL_PRIVATE_HELP']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="settings-field">
                        <label for="context_limit">Context limit</label>

                        <div class="rag-range range-group">
                            <input
                                type="range"
                                id="context_limit"
                                name="context_limit"
                                min="60"
                                max="95"
                                step="1"
                                value="<?php echo (int)($parameters['context_limit'] ?? 75); ?>">

                            <input
                                type="number"
                                class="range-number"
                                min="60"
                                max="95"
                                step="1"
                                value="<?php echo (int)($parameters['context_limit'] ?? 75); ?>">
                        </div>
                    </div>                    

                </div>

            </section>


            <!-- ==================================================
                 BEHAVIOR
                 ================================================== -->

<section
    class="model-tab-panel"
    data-model-panel="behavior"
    hidden>

    <div class="settings-section">

        <div class="settings-section-title">
            <h2><?php echo LANG['MODEL_TAB_BEHAVIOR']; ?></h2>
            <p>
                <?php echo LANG['MODEL_BEHAVIOR_HELP']; ?>
            </p>
        </div>


        <!-- SYSTEM PROMPT -->

        <div class="settings-field settings-field-textarea model-prompt-field">

            <label for="model-system-prompt">
                <?php echo LANG['MODEL_SYSTEM_PROMPT']; ?>
            </label>

            <textarea
                id="model-system-prompt"
                name="system_prompt"
                rows="8"
                placeholder="<?php echo htmlspecialchars(LANG['MODEL_SYSTEM_PROMPT_PLACEHOLDER']); ?>"><?php echo htmlspecialchars($prompt ?? ''); ?></textarea>

        </div>


        <!-- PSYCHE SWITCH -->

        <div class="settings-field model-psyche-switch">

            <label>
                <?php echo LANG['MODEL_PSYCHE']; ?>
            </label>

            <div class="model-switch-control">

                <label class="switch">
                    <input
                        type="checkbox"
                        id="model-psyche-enabled"
                        name="psyche"
                        value="1"
                        <?php echo !empty($psyche) ? 'checked' : ''; ?>>

                    <span class="switch-slider"></span>
                </label>

                <span class="model-switch-description">
                    <?php echo LANG['MODEL_PSYCHE_HELP']; ?>
                </span>

            </div>

        </div>

        <!-- PSYCHE CONTENT -->

        <div
            class="model-psyche-content"
            id="model-psyche-content">

          <!-- PSYCHE HISTORY -->

          <div class="psyche-history">

              <div class="psyche-history-header">
                  <?php echo LANG['MODEL_PSYCHE_HISTORY']; ?>
              </div>

              <div class="psyche-history-list">

                  <?php foreach (($past ?? []) as $pastPsyche) { ?>
                    <button
                        type="button"
                        class="psyche-history-item"
                        data-psyche-id="<?php echo (int)($pastPsyche['id'] ?? 0); ?>">
                        <span><?php echo htmlspecialchars($pastPsyche['modify'] ?? ''); ?></span>
                    </button>
                  <?php } ?>

              </div>

          </div>            

            <!-- PSYCHE PROMPT -->

            <div class="settings-field settings-field-textarea model-psyche-prompt">

                <label for="psyche-prompt">
                    Prompt
                </label>

                <textarea
                    id="psyche-prompt"
                    name="psyche_prompt"
                    rows="10"
                    placeholder="<?php echo htmlspecialchars(LANG['MODEL_PSYCHE_PROMPT_PLACEHOLDER']); ?>"><?php echo htmlspecialchars($psyche_data['prompt'] ?? ''); ?></textarea>

            </div>


            <!-- MEMORY -->

            <div class="settings-field settings-field-textarea">

                <label for="psyche-memory">
                    Memory
                </label>

                <textarea
                    id="psyche-memory"
                    name="psyche_memory"
                    rows="4"
                    placeholder="<?php echo htmlspecialchars(LANG['MODEL_PSYCHE_MEMORY_PLACEHOLDER']); ?>"><?php echo htmlspecialchars($psyche_data['memory'] ?? ''); ?></textarea>

            </div>


            <!-- REASON -->

            <div class="settings-field settings-field-textarea">

                <label for="psyche-reason">
                    Reason
                </label>

                <textarea
                    id="psyche-reason"
                    name="psyche_reason"
                    rows="4"
                    placeholder="<?php echo htmlspecialchars(LANG['MODEL_PSYCHE_REASON_PLACEHOLDER']); ?>"><?php echo htmlspecialchars($psyche_data['reason'] ?? ''); ?></textarea>

            </div>


            <!-- INSERT -->

            <div class="settings-field">

                <label>
                    <?php echo LANG['MODEL_PSYCHE_NEW']; ?>
                </label>

                <div class="model-switch-control">

                    <label class="switch">
                        <input
                            type="checkbox"
                            id="psyche-insert"
                            name="psyche_insert"
                            value="1">

                        <span class="switch-slider"></span>
                    </label>

                    <span class="model-switch-description">
                        <?php echo LANG['MODEL_PSYCHE_INSERT_HELP']; ?>
                    </span>

                </div>

            </div>

        </div>

    </div>

</section>


            <!-- ==================================================
                 PARAMETERS
                 ================================================== -->
<!-- ==================================================
     PARAMETERS
     ================================================== -->

<section
    class="model-tab-panel"
    data-model-panel="parameters"
    hidden>

    <div class="settings-section">

        <div class="settings-section-title">
            <h2><?php echo LANG['MODEL_TAB_PARAMETERS']; ?></h2>
            <p>
                <?php echo LANG['MODEL_PARAMETERS_HELP']; ?>
            </p>
        </div>


        <div class="model-parameters-grid">


            <!-- TEMPERATURE -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-temperature">
                            <?php echo LANG['MODEL_PARAM_TEMPERATURE']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_TEMPERATURE_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="2"
                        step="0.05"
                        value="<?php echo htmlspecialchars($parameters['temperature'] ?? 0.7); ?>">
                </div>

                <input
                    type="range"
                    id="param-temperature"
                    name="temperature"
                    min="0"
                    max="2"
                    step="0.05"
                    value="<?php echo htmlspecialchars($parameters['temperature'] ?? 0.7); ?>">
            </div>


            <!-- REPEAT PENALTY -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-repeat-penalty">
                            <?php echo LANG['MODEL_PARAM_REPEAT_PENALTY']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_REPEAT_PENALTY_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="2"
                        step="0.05"
                        value="<?php echo htmlspecialchars($parameters['repeat_penalty'] ?? 1.1); ?>">
                </div>

                <input
                    type="range"
                    id="param-repeat-penalty"
                    name="repeat_penalty"
                    min="0"
                    max="2"
                    step="0.05"
                    value="<?php echo htmlspecialchars($parameters['repeat_penalty'] ?? 1.1); ?>">
            </div>


            <!-- TOP P -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-top-p">
                            <?php echo LANG['MODEL_PARAM_TOP_P']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_TOP_P_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="1"
                        step="0.01"
                        value="<?php echo htmlspecialchars($parameters['top_p'] ?? 0.9); ?>">
                </div>

                <input
                    type="range"
                    id="param-top-p"
                    name="top_p"
                    min="0"
                    max="1"
                    step="0.01"
                    value="<?php echo htmlspecialchars($parameters['top_p'] ?? 0.9); ?>">
            </div>


            <!-- TOP K -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-top-k">
                            <?php echo LANG['MODEL_PARAM_TOP_K']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_TOP_K_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="200"
                        step="1"
                        value="<?php echo (int)($parameters['top_k'] ?? 40); ?>">
                </div>

                <input
                    type="range"
                    id="param-top-k"
                    name="top_k"
                    min="0"
                    max="200"
                    step="1"
                    value="<?php echo (int)($parameters['top_k'] ?? 40); ?>">
            </div>


            <!-- MIN P -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-min-p">
                            <?php echo LANG['MODEL_PARAM_MIN_P']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_MIN_P_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="1"
                        step="0.01"
                        value="<?php echo htmlspecialchars($parameters['min_p'] ?? 0.05); ?>">
                </div>

                <input
                    type="range"
                    id="param-min-p"
                    name="min_p"
                    min="0"
                    max="1"
                    step="0.01"
                    value="<?php echo htmlspecialchars($parameters['min_p'] ?? 0.05); ?>">
            </div>


            <!-- PRESENCE PENALTY -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-presence-penalty">
                            <?php echo LANG['MODEL_PARAM_PRESENCE_PENALTY']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_PRESENCE_PENALTY_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="-2"
                        max="2"
                        step="0.05"
                        value="<?php echo htmlspecialchars($parameters['presence_penalty'] ?? 0); ?>">
                </div>

                <input
                    type="range"
                    id="param-presence-penalty"
                    name="presence_penalty"
                    min="-2"
                    max="2"
                    step="0.05"
                    value="<?php echo htmlspecialchars($parameters['presence_penalty'] ?? 0); ?>">
            </div>


            <!-- FREQUENCY PENALTY -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-frequency-penalty">
                            <?php echo LANG['MODEL_PARAM_FREQUENCY_PENALTY']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_FREQUENCY_PENALTY_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="-2"
                        max="2"
                        step="0.05"
                        value="<?php echo htmlspecialchars($parameters['frequency_penalty'] ?? 0); ?>">
                </div>

                <input
                    type="range"
                    id="param-frequency-penalty"
                    name="frequency_penalty"
                    min="-2"
                    max="2"
                    step="0.05"
                    value="<?php echo htmlspecialchars($parameters['frequency_penalty'] ?? 0); ?>">
            </div>


            <!-- NUM CTX -->

            <div class="parameter-item range-group">

                <div class="parameter-header">
                    <div>
                        <label for="param-num-ctx">
                            <?php echo LANG['MODEL_PARAM_NUM_CTX']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_NUM_CTX_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        min="1024"
                        max="131072"
                        step="1024"
                        value="<?php echo (int)($parameters['num_ctx'] ?? 4096); ?>">
                </div>

                <input
                    type="range"
                    id="param-num-ctx"
                    name="num_ctx"
                    min="1024"
                    max="131072"
                    step="1024"
                    value="<?php echo (int)($parameters['num_ctx'] ?? 4096); ?>">
            </div>


            <!-- MAX TOKENS -->

            <div class="parameter-item parameter-max-tokens range-group">

                <div class="parameter-header">

                    <div>
                        <label for="param-max-tokens">
                            <?php echo LANG['MODEL_PARAM_MAX_TOKENS']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_MAX_TOKENS_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        id="number-max-tokens"
                        min="1"
                        max="32768"
                        step="1"
                        value="<?php echo ((int)($parameters['max_tokens'] ?? 600) === -1) ? 600 : (int)($parameters['max_tokens'] ?? 600); ?>">
                </div>

                <input
                    type="range"
                    id="param-max-tokens"
                    name="max_tokens"
                    min="1"
                    max="32768"
                    step="1"
                    value="<?php echo ((int)($parameters['max_tokens'] ?? 600) === -1) ? 600 : (int)($parameters['max_tokens'] ?? 600); ?>">

                <div class="parameter-option">

                    <label class="switch">
                        <input
                            type="checkbox"
                            id="max-tokens-auto"
                            name="max_tokens_auto"
                            value="1"
                            <?php echo ((int)($parameters['max_tokens'] ?? 600) === -1) ? 'checked' : ''; ?>>

                        <span class="switch-slider"></span>
                    </label>

                    <span>
                        <?php echo LANG['MODEL_PARAM_UNLIMITED']; ?>
                    </span>

                </div>

            </div>


            <!-- REPEAT LAST N -->

            <div class="parameter-item parameter-repeat-last range-group">

                <div class="parameter-header">

                    <div>
                        <label for="param-repeat-last-n">
                            <?php echo LANG['MODEL_PARAM_REPEAT_LAST_N']; ?>
                        </label>

                        <span class="parameter-help">
                            <?php echo LANG['MODEL_PARAM_REPEAT_LAST_N_HELP']; ?>
                        </span>
                    </div>

                    <input
                        type="number"
                        class="range-number"
                        id="number-repeat-last-n"
                        min="0"
                        max="4096"
                        step="64"
                        value="<?php echo ((int)($parameters['repeat_last_n'] ?? 64) === -1) ? 64 : (int)($parameters['repeat_last_n'] ?? 64); ?>">
                </div>

                <input
                    type="range"
                    id="param-repeat-last-n"
                    name="repeat_last_n"
                    min="0"
                    max="4096"
                    step="64"
                    value="<?php echo ((int)($parameters['repeat_last_n'] ?? 64) === -1) ? 64 : (int)($parameters['repeat_last_n'] ?? 64); ?>">

                <div class="parameter-option">

                    <label class="switch">
                        <input
                            type="checkbox"
                            id="repeat-last-auto"
                            name="repeat_last_auto"
                            value="1"
                            <?php echo ((int)($parameters['repeat_last_n'] ?? 64) === -1) ? 'checked' : ''; ?>>

                        <span class="switch-slider"></span>
                    </label>

                    <span>
                        <?php echo LANG['MODEL_PARAM_FULL_CONTEXT']; ?>
                    </span>

                </div>

            </div>


        </div>

    </div>

</section>


            <!-- ==================================================
                 CAPABILITIES
                 ================================================== -->

<?php
    $capabilities = $parameters['capabilities'] ?? [];
    $builtinTools = $parameters['builtin_tools'] ?? [];

    if (!is_array($capabilities)) $capabilities = [];
    if (!is_array($builtinTools)) $builtinTools = [];

    $unknownCapabilities = ($modelinfo['capabilities_known'] ?? (($provider ?? '') === 'llamacpp' ? false : true)) === false;
    $supportsThinking = $unknownCapabilities || !empty($modelinfo['thinking']);
    $supportsTools    = $unknownCapabilities || !empty($modelinfo['tools']);
?>

<section
    class="model-tab-panel"
    data-model-panel="capabilities"
    hidden>

    <div class="settings-section">

        <div class="settings-section-title">
            <h2><?php echo LANG['MODEL_CAPABILITIES']; ?></h2>
            <p><?php echo LANG['MODEL_CAPABILITIES_HELP']; ?></p>
        </div>

        <!-- THINKING -->
        <div class="settings-field model-capability-field">
            <label><?php echo LANG['MODEL_CAP_THINKING']; ?></label>
            <div class="model-switch-control">
                <label class="switch">
                    <input
                        type="checkbox"
                        id="cap-thinking"
                        name="param_think"
                        value="1"
                        <?php echo !empty($parameters['think']) ? 'checked' : ''; ?>
                        <?php echo !$supportsThinking ? 'disabled' : ''; ?>>
                    <span class="switch-slider"></span>
                </label>
                <span class="model-switch-description">
                    <?php echo LANG['MODEL_CAP_THINKING_HELP']; ?>
                </span>
            </div>
        </div>

        <!-- WEB SEARCH -->
        <div class="settings-field model-capability-field">
            <label><?php echo LANG['MODEL_CAP_WEB_SEARCH']; ?></label>
            <div class="model-switch-control">
                <label class="switch">
                    <input
                        type="checkbox"
                        id="cap-web-search"
                        name="cap_web_search"
                        value="1"
                        <?php echo !empty($capabilities['web_search']) ? 'checked' : ''; ?>>
                    <span class="switch-slider"></span>
                </label>
                <span class="model-switch-description">
                    <?php echo LANG['MODEL_CAP_WEB_SEARCH_HELP']; ?>
                </span>
            </div>
        </div>

        <!-- CODE INTERPRETER -->
        <div class="settings-field model-capability-field">
            <label><?php echo LANG['MODEL_CAP_CODE']; ?></label>
            <div class="model-switch-control">
                <label class="switch">
                    <input
                        type="checkbox"
                        id="cap-code-interpreter"
                        name="cap_code_interpreter"
                        value="1"
                        <?php echo !empty($capabilities['code_interpreter']) ? 'checked' : ''; ?>>
                    <span class="switch-slider"></span>
                </label>
                <span class="model-switch-description">
                    <?php echo LANG['MODEL_CAP_CODE_HELP']; ?>
                </span>
            </div>
        </div>

        <!-- TOOLS -->
        <div class="settings-field model-capability-field">
            <label><?php echo LANG['MODEL_CAP_TOOLS']; ?></label>
            <div class="model-switch-control">
                <label class="switch">
                    <input
                        type="checkbox"
                        id="cap-tools"
                        name="cap_tools"
                        value="1"
                        <?php echo !empty($capabilities['tools']) ? 'checked' : ''; ?>
                        <?php echo !$supportsTools ? 'disabled' : ''; ?>>
                    <span class="switch-slider"></span>
                </label>
                <span class="model-switch-description">
                    <?php echo LANG['MODEL_CAP_TOOLS_HELP']; ?>
                </span>
            </div>
        </div>

        <!-- TOOL CONFIGURATION -->
        <div class="model-tools" id="model-tools">
            <div class="model-tools-header">
                <div>
                    <strong><?php echo LANG['MODEL_ENABLED_TOOLS']; ?></strong>
                    <span><?php echo LANG['MODEL_ENABLED_TOOLS_HELP']; ?></span>
                </div>
            </div>

            <div class="model-tool-add">
                <select id="tool-select">
                    <option value=""><?php echo LANG['MODEL_ADD_TOOL']; ?></option>
                    <option value="search_web">search_web</option>
                    <option value="visit_webpage">visit_webpage</option>
                    <option value="search_images">search_images</option>
                    <option value="generate_image">generate_image</option>
                    <option value="update_memory">update_memory</option>
                    <option value="get_last_response_feedback">get_last_response_feedback</option>
                    <option value="rate_user">rate_user</option>
                </select>

                <button type="button" class="btn btn-dark" id="tool-add">
                    <?php echo LANG['ADD']; ?>
                </button>
            </div>

            <div class="model-tool-list" id="active-tools">
                <?php foreach ($builtinTools as $tool) { ?>
                  <div class="model-tool-item" data-tool="<?php echo htmlspecialchars($tool); ?>">
                      <span><?php echo htmlspecialchars($tool); ?></span>
                      <button
                          type="button"
                          class="model-tool-remove"
                          title="<?php echo htmlspecialchars(LANG['MODEL_REMOVE_TOOL']); ?>">×</button>
                  </div>
                <?php } ?>
            </div>

            <input
                type="hidden"
                id="builtin-tools"
                name="builtin_tools"
                value="<?php echo htmlspecialchars(json_encode(array_values($builtinTools), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

    </div>
</section>


            <!-- ==================================================
                 RAG
                 ================================================== -->

<section
    class="model-tab-panel"
    data-model-panel="rag"
    hidden>

    <?php
      $selectedRagIds = $rag_ids ?? [];

      if (is_string($selectedRagIds))
        {
          $selectedRagIds = json_decode($selectedRagIds, true) ?? [];
        }

      $selectedRagIds = array_values(array_unique(array_map('intval', $selectedRagIds)));
      $ragDocuments    = $ragdocuments ?? [];
    ?>

    <div class="settings-section">

        <div class="settings-section-title">
            <h2>RAG</h2>
            <p><?php echo LANG['MODEL_RAG_DESCRIPTION']; ?></p>
        </div>

        <div class="settings-field">
            <label>RAG</label>

            <div class="model-switch-control">
                <label class="switch">
                    <input
                        type="checkbox"
                        id="rag-enabled"
                        name="rag"
                        value="1"
                        <?php echo !empty($rag) ? 'checked' : ''; ?>>
                    <span class="switch-slider"></span>
                </label>

                <span class="model-switch-description">
                    <?php echo LANG['MODEL_RAG_ENABLE_HELP']; ?>
                </span>
            </div>
        </div>

        <div id="rag-settings">

            <div class="settings-field">
                <label for="rag-similarity"><?php echo LANG['MODEL_RAG_SIMILARITY']; ?></label>

                <div class="rag-range range-group">
                    <input
                        type="range"
                        id="rag-similarity"
                        name="rag_similarity"
                        min="0"
                        max="1"
                        step="0.01"
                        value="<?php echo htmlspecialchars((string)($rag_similarity ?? 0.35)); ?>">

                    <input
                        type="number"
                        class="range-number"
                        min="0"
                        max="1"
                        step="0.01"
                        value="<?php echo htmlspecialchars((string)($rag_similarity ?? 0.35)); ?>">
                </div>
            </div>

            <div class="settings-field">
                <label for="rag-limit">
                    <?php echo LANG['MODEL_RAG_LIMIT']; ?>
                </label>

                <div class="rag-range range-group">
                    <input
                        type="range"
                        id="rag-limit"
                        name="rag_limit"
                        min="1"
                        max="20"
                        step="1"
                        value="<?php echo (int)($rag_limit ?? 3); ?>">

                    <input
                        type="number"
                        class="range-number"
                        min="1"
                        max="20"
                        step="1"
                        value="<?php echo (int)($rag_limit ?? 3); ?>">
                </div>
            </div>

            <div class="model-rag-knowledge">
                <div class="model-tools-header">
                    <div>
                        <strong><?php echo LANG['MODEL_RAG_ASSIGNED']; ?></strong>
                        <span><?php echo LANG['MODEL_RAG_ASSIGNED_HELP']; ?></span>
                    </div>
                </div>

                <div class="model-tool-add">
                    <select id="rag-item-select">
                        <option value="">
                            <?php echo LANG['MODEL_RAG_ADD']; ?>
                        </option>

                        <?php foreach ($ragDocuments as $document) { ?>
                          <?php if (!in_array((int)$document['id'], $selectedRagIds, true)) { ?>
                            <option value="<?php echo (int)$document['id']; ?>">
                                <?php echo htmlspecialchars($document['title'] ?? ''); ?>
                            </option>
                          <?php } ?>
                        <?php } ?>
                    </select>

                    <button
                        type="button"
                        class="btn btn-dark"
                        id="rag-item-add">
                        <?php echo LANG['ADD']; ?>
                    </button>
                </div>

                <div class="model-tool-list" id="rag-item-list">
                    <?php foreach ($ragDocuments as $document) { ?>
                      <?php if (in_array((int)$document['id'], $selectedRagIds, true)) { ?>
                        <div
                            class="model-tool-item"
                            data-rag-id="<?php echo (int)$document['id']; ?>">

                            <span><?php echo htmlspecialchars($document['title'] ?? ''); ?></span>

                            <button
                                type="button"
                                class="model-tool-remove"
                                title="<?php echo htmlspecialchars(LANG['MODEL_RAG_REMOVE']); ?>">
                                ×
                            </button>
                        </div>
                      <?php } ?>
                    <?php } ?>
                </div>

                <input
                    type="hidden"
                    id="rag-items"
                    name="rag_ids"
                    value="<?php echo htmlspecialchars(json_encode($selectedRagIds), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

        </div>

    </div>

</section>

        </form>

    </div>

</section>

</main><!-- /.app-content -->





<!-- KÉP MODAL -->
<div id="modelImageModal" class="image-modal">
  <div class="image-modal-content">

    <div class="image-modal-header">
      <span>Kép hozzáadása</span>
      <button type="button" class="image-modal-close" onclick="closeImageModal('modelImageModal')">×</button>
    </div>
    <div class="image-modal-body">
      <div class="main-gallery-grid">
        <?php echo $modelimages; ?>
      </div>  
    </div>
    <div class="image-modal-footer">
      <input type="hidden" name="newModelImage" id="newModelImage">
      <button type="button" onclick="closeImageModal('modelImageModal')">OK</button>
    </div>

  </div>
</div>
