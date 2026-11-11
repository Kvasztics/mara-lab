<!-- ==========================================================
     KNOWLEDGE EDITOR
     ========================================================== -->
<main class="app-content">
    <section class="knowledge-editor">
        <form
          class="knowledge-editor-form"
          method="post"
          action="<?php echo $action; ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($importCsrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="import_token" id="knowledge-import-token"
                 value="<?= htmlspecialchars($importToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <!-- ==================================================
                 HEADER
                 ================================================== -->
            <header class="model-editor-header">
                <div>
                    <h1 class="model-editor-title">
                        <?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?>
                    </h1>
                    <p class="model-editor-description">
                        <?php echo LANG['KNOW_EDIT']; ?>
                    </p>
                </div>
                <div>
                    <button type="button" id="knowledge-import-open" class="btn">
                        <?= LANG['KNOW_IMPORT_BUTTON'] ?>
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary">
                        <?php echo LANG['SAVE']; ?>
                    </button>
                </div>
            </header>
            <div id="knowledge-import-dialog" class="modal common-modal" role="dialog" aria-modal="true"
                    aria-labelledby="knowledge-import-heading"
                    data-endpoint="<?= htmlspecialchars(DIR_HOST.'/main/ragimport', ENT_QUOTES, 'UTF-8') ?>"
                    data-error="<?= htmlspecialchars(LANG['KNOW_IMPORT_ERROR'], ENT_QUOTES, 'UTF-8') ?>"
                    data-loading="<?= htmlspecialchars(LANG['KNOW_IMPORT_LOADING'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-content">
                    <button type="button" class="close" id="knowledge-import-close" aria-label="<?= LANG['MODAL_CLOSE'] ?>">&times;</button>
                    <div class="content">
                        <h2 id="knowledge-import-heading"><?= LANG['KNOW_IMPORT_BUTTON'] ?></h2>
                        <label for="knowledge-import-url"><?= LANG['KNOW_IMPORT_URL'] ?></label>
                        <input id="knowledge-import-url" type="url" maxlength="500" autocomplete="url">
                        <p id="knowledge-import-feedback" role="status"></p>
                        <div class="modal-actions">
                        <button type="button" id="knowledge-import-cancel" class="btn"><?= LANG['KNOW_IMPORT_CANCEL'] ?></button>
                        <button type="button" id="knowledge-import-start" class="btn btn-primary"><?= LANG['KNOW_IMPORT_START'] ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <p id="knowledge-import-source" <?= $importSource === '' ? 'hidden' : '' ?>>
                <?= LANG['KNOW_IMPORT_SOURCE'] ?>
                <span id="knowledge-import-source-url"><?= htmlspecialchars($importSource, ENT_QUOTES, 'UTF-8') ?></span>
                <span id="knowledge-import-source-time"><?= $importFetchedAt === '' ? '' : htmlspecialchars($importFetchedAt.' UTC', ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <!-- ==================================================
                 EDITOR
                 ================================================== -->
            <div class="settings-section knowledge-editor-section">
                <div class="settings-section-title">
                    <h2><?php echo LANG['KNOW_KNOW']; ?></h2>
                    <p>
                        <?php echo LANG['KNOW_TITLE_HLP']; ?>
                    </p>
                </div>
                <!-- TITLE -->
                <div class="settings-field">
                    <label for="knowledge-title">
                        <?php echo LANG['KNOW_TITLE']; ?>
                    </label>
                    <input
                        type="text"
                        id="knowledge-title"
                        name="title"
                        value="<?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="<?php echo LANG['KNOW_TITLE_PLH']; ?>">
                </div>
                <!-- MODEL -->
<?php if ($isAdmin) { ?>                 
    <div class="settings-field">

        <label for="knowledge-model">
            <?php echo LANG['KNOW_OWNER']; ?>
        </label>

        <select
            id="knowledge-model"
            name="user_id">

            <option
                value="0"
                <?php echo ((int)$user_id === 0) ? 'selected' : ''; ?>>
                <?php echo LANG['KNOW_PUBLIC']; ?>
            </option>

            <option
                value="<?php echo (int)$user_id; ?>"
                <?php echo ((int)$user_id > 0) ? 'selected' : ''; ?>>
                <?php echo LANG['KNOW_PERSONAL']; ?>
            </option>

        </select>

    </div>
<?php } ?>                
                <!-- CONTENT -->
                <div class="settings-field knowledge-content-row">
                    <label for="knowledge-content">
                        <?php echo LANG['KNOW_CONTENT']; ?>
                    </label>
                    <textarea
                        id="knowledge-content"
                        name="content"
                        placeholder="<?php echo LANG['KNOW_CONTENT_PLH']; ?>"><?php echo htmlspecialchars($content, ENT_QUOTES, "UTF-8"); ?></textarea>
                </div>
            </div>
        </form>
    </section>
</main><!-- /.app-content -->