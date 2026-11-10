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
            <a href="<?php echo DIR_HOST; ?>/main/ragnew" class="btn btn-primary">
                + <?php echo LANG['KNOW_NEW']; ?>
            </a>
        </header>
        <!-- ==================================================
             KNOWLEDGE LIST
             ================================================== -->
        <?php if (isset($embeddingConfig, $embeddingToken)): ?>
        <details class="settings-section">
            <summary><?= LANG['RAG_EMBEDDING_TITLE'] ?></summary>
            <form method="post" action="<?= DIR_HOST ?>/main/ragembeddingsave">
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
            </form>
        </details>
        <?php endif; ?>
        <div class="models-list knowledge-list">
          <?php echo $knowledge; ?>
        </div>
    </section>
</main><!-- /.app-content -->