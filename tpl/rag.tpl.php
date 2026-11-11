<!-- ==========================================================
     KNOWLEDGE LIST
     ========================================================== -->
<main class="app-content">
    <section class="models-page knowledge-page">
        <!-- ==================================================
             HEADER
             ================================================== -->
        <header class="models-page-header">
            <div>
                <h1><?php echo LANG['KNOWLEDGE']; ?></h1>
                <p>
                  <?php echo LANG['KNOW_PTITLE']; ?>  
                </p>
            </div>
        </header>
        <!-- ==================================================
             KNOWLEDGE LIST
             ================================================== -->
        <?php $ragTab = $ragTab ?? 'rag'; ?>
        <nav class="settings-tabs">
            <button type="button" class="settings-tab <?= $ragTab === 'rag' ? 'active' : '' ?>" data-tab="rag-list"><?= LANG['RAG_TAB_LIST'] ?></button>
            <?php if (isset($embeddingConfig, $embeddingToken)): ?>
                <button type="button" class="settings-tab <?= $ragTab === 'embedding' ? 'active' : '' ?>" data-tab="rag-embedding"><?= LANG['RAG_TAB_EMBEDDING'] ?></button>
                <button type="button" class="settings-tab <?= $ragTab === 'reader' ? 'active' : '' ?>" data-tab="rag-reader"><?= LANG['RAG_TAB_READER'] ?></button>
            <?php endif; ?>
        </nav>
        <section id="settings-rag-list" class="settings-panel <?= $ragTab === 'rag' ? 'active' : '' ?>" <?= $ragTab === 'rag' ? '' : 'hidden' ?>>
            <a href="<?php echo DIR_HOST; ?>/main/ragnew" class="btn btn-primary">
                + <?php echo LANG['KNOW_NEW']; ?>
            </a>
            <div class="models-list knowledge-list"><?= $knowledge ?></div>
        </section>
        <?php if (isset($embeddingConfig, $embeddingToken)): ?>
        <section id="settings-rag-embedding" class="settings-panel settings-section <?= $ragTab === 'embedding' ? 'active' : '' ?>"
                 <?= $ragTab === 'embedding' ? '' : 'hidden' ?>>
            <form id="rag-embedding-form" method="post" action="<?= DIR_HOST ?>/main/ragembeddingsave"
                  data-working="<?= htmlspecialchars(LANG['RAG_EMBEDDING_WORKING'], ENT_QUOTES, 'UTF-8') ?>"
                  data-test-error="<?= htmlspecialchars(LANG['RAG_EMBEDDING_TEST_ERROR'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($embeddingToken, ENT_QUOTES, 'UTF-8') ?>">
                <p><?= LANG['RAG_EMBEDDING_HELP'] ?></p>
                <div class="settings-field">
                    <label for="rag-embedding-provider"><?= LANG['RAG_EMBEDDING_PROVIDER'] ?></label>
                    <select id="rag-embedding-provider" name="embedding_provider">
                        <option value="ollama" <?= $embeddingConfig['provider'] === 'ollama' ? 'selected' : '' ?>>Ollama</option>
                        <option value="llamacpp" <?= $embeddingConfig['provider'] === 'llamacpp' ? 'selected' : '' ?>>llama.cpp</option>
                    </select>
                </div>
                <div class="settings-field">
                    <label for="rag-embedding-model"><?= LANG['RAG_EMBEDDING_MODEL'] ?></label>
                    <input type="text" id="rag-embedding-model" name="embedding_model" required maxlength="255"
                           value="<?= htmlspecialchars($embeddingConfig['model'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="settings-field">
                    <label for="rag-embedding-port"><?= LANG['RAG_EMBEDDING_PORT'] ?></label>
                    <input type="number" id="rag-embedding-port" name="embedding_port" min="1" max="65535"
                           value="<?= (int)$embeddingConfig['port'] ?>">
                </div>
                <button type="submit" class="btn btn-primary"><?= LANG['RAG_EMBEDDING_SAVE'] ?></button>
                <button type="button" id="rag-embedding-test" class="btn" hidden><?= LANG['RAG_EMBEDDING_TEST'] ?></button>
                <progress id="rag-embedding-progress" aria-label="<?= LANG['RAG_EMBEDDING_WORKING'] ?>" hidden></progress>
                <p id="rag-embedding-feedback" role="status"></p>
            </form>
        </section>
        <section id="settings-rag-reader" class="settings-panel settings-section <?= $ragTab === 'reader' ? 'active' : '' ?>"
                 <?= $ragTab === 'reader' ? '' : 'hidden' ?>>
            <form method="post" action="<?= DIR_HOST ?>/main/ragreadersave">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($embeddingToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="settings-field">
                    <label for="rag-reader-prompt"><?= LANG['RAG_READER_PROMPT'] ?></label>
                    <textarea id="rag-reader-prompt" name="system_prompt" rows="8" maxlength="8000" required><?= htmlspecialchars(
                        \mara\core\integration\KnowledgeWebImport::prompt(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
                    ) ?></textarea>
                </div>
                <?php foreach (\mara\core\integration\KnowledgeWebImport::LIMITS as $key => [$default, $min, $max]):
                    $value = \mara\core\integration\KnowledgeWebImport::limit($key);
                ?>
                <div class="settings-field">
                    <label for="reader-<?= $key ?>"><?= LANG['RAG_READER_'.strtoupper($key)] ?></label>
                    <div class="rag-range range-group">
                        <input type="range" aria-label="<?= LANG['RAG_READER_'.strtoupper($key)] ?>"
                               min="<?= $min ?>" max="<?= $max ?>" value="<?= $value ?>">
                        <input type="number" class="range-number" id="reader-<?= $key ?>" name="<?= $key ?>"
                               min="<?= $min ?>" max="<?= $max ?>" value="<?= $value ?>" required>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary"><?= LANG['SAVE'] ?></button>
            </form>
        </section>
        <?php endif; ?>
    </section>
</main><!-- /.app-content -->
