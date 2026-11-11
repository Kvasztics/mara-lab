            <!-- KNOWLEDGE ITEM -->
            <article class="model-card knowledge-card">

                <button
                    type="button"
                    class="model-card-delete knowledge-delete"
                    data-id="<?php echo (int)$id; ?>"
                    title="<?php echo LANG['DELETE']; ?>"
                    aria-label="<?php echo LANG['DELETE']; ?>">
                    🗑
                </button>

                    <div class="knowledge-card-content">

                        <h2 class="knowledge-card-title">
                            <?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </h2>

                        <p class="knowledge-card-preview">
                            <?php echo htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                        </p>

                        <div class="knowledge-card-meta">
                            <span class="knowledge-badge knowledge-badge-global">
                                <?php echo $info; ?>
                            </span>
                            <?php if (!empty($source)): ?>
                                <span><?= LANG['KNOW_IMPORT_SOURCE'] ?> <?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($source_fetched_at)): ?>
                                <time datetime="<?= htmlspecialchars(str_replace(' ', 'T', $source_fetched_at).'Z', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($source_fetched_at.' UTC', ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            <?php endif; ?>
                        </div>

                    </div>

            </article>