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
                            <?php echo $title; ?>
                        </h2>

                        <p class="knowledge-card-preview">
                            <?php echo $content; ?>
                        </p>

                        <div class="knowledge-card-meta">
                            <span class="knowledge-badge knowledge-badge-global">
                                <?php echo $info; ?>
                            </span>
                        </div>

                    </div>

            </article>