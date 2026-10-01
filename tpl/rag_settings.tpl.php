<!-- ==========================================================
     KNOWLEDGE EDITOR
     ========================================================== -->
<main class="app-content">
    <section class="knowledge-editor">
        <form
          class="knowledge-editor-form"
          method="post"
          action="<?php echo $action; ?>">
          <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <!-- ==================================================
                 HEADER
                 ================================================== -->
            <header class="model-editor-header">
                <div>
                    <h1 class="model-editor-title">
                        <?php echo $title; ?>
                    </h1>
                    <p class="model-editor-description">
                        <?php echo LANG['KNOW_EDIT']; ?>
                    </p>
                </div>
                <button
                    type="submit"
                    class="btn btn-primary">
                    <?php echo LANG['SAVE']; ?>
                </button>
            </header>
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
                        value="<?php echo $title; ?>"
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
                        placeholder="<?php echo LANG['KNOW_CONTENT_PLH']; ?>"><?php echo $content; ?></textarea>
                </div>
            </div>
        </form>
    </section>
</main><!-- /.app-content -->