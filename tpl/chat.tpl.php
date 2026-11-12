        <!-- ============================================================
             PAGE CONTENT
             ============================================================ -->

        <main class="app-content">


<!-- ======================================================================
     MARA LAB - CHAT
     ====================================================================== -->

<div
    class="chat-layout"
    data-stt-provider="<?= htmlspecialchars($stt_provider ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-stt-language="<?= htmlspecialchars($stt_language ?? '', ENT_QUOTES, 'UTF-8') ?>">


    <!-- ==================================================================
         MODEL / CHARACTER SIDE
         ================================================================== -->

    <aside class="chat-side">


        <!-- --------------------------------------------------------------
             LIVING PORTRAIT
             -------------------------------------------------------------- -->

        <div class="model-portrait">

            <img
                id="modelimage"
                src="<?php echo $image; ?>"
                alt="Model Avatar">

        </div>

<div class="chat-side-scroll">

    <!-- ================================================================
         TEST
         ================================================================ -->

    <div class="chat-side-section">

        <div class="side-option">

            <span class="side-option-label">
                Teszt
            </span>

            <label class="switch">
                <input
                    type="checkbox"
                    id="test_mode"
                    checked>

                <span class="switch-slider"></span>
            </label>

        </div>


        <div id="gpu-mini" style="margin:8px 0" hidden>
          <div id="gpu-mini-bar" role="progressbar" aria-label="GPU VRAM foglaltság" aria-valuemin="0" aria-valuemax="100" style="height:15px;background:#e5e7eb;border-radius:3px;position:relative;overflow:hidden">
            <div id="gpu-mini-fill" style="height:100%;width:0;background:#1976d2"></div>
            <span id="gpu-mini-value" style="position:absolute;right:4px;top:0;font:11px/15px sans-serif;color:#111"></span>
          </div>
        </div>

        <!-- TEST DATA -->

        <div class="test-data" id="model_metrics">

        </div>

    </div>


    <!-- Emotional Ball: persisted character state, independent 0–10 axes. -->
    <div class="chat-side-section">
        <div class="side-option">
            <span class="side-option-label">Emotional Ball</span>
            <label class="switch">
                <input type="checkbox" id="emotional_ball" aria-label="Emotional Ball">
                <span class="switch-slider"></span>
            </label>
        </div>
        <div id="emotional_chart" class="emotional-chart" aria-live="polite" hidden>
            <svg id="emotional_radar" viewBox="0 0 200 200" role="img" aria-label="Emotional Ball: nyolc érzelem, 0–10"></svg>
            <div id="emotional_chart_status" class="emotional-chart-status">Betöltés…</div>
        </div>
    </div>

    <!-- ================================================================
         RATE USER
         ================================================================ -->

    <div class="chat-side-section">

        <div class="side-option">

            <span class="side-option-label">
                Rate User
            </span>

            <label class="switch">
                <input
                    type="checkbox"
                    id="rate_user">

                <span class="switch-slider"></span>
            </label>

        </div>


        <!-- USER RATING -->

        <div class="user-rating" id="user_rating">

        </div>

    </div>

</div>


    </aside>


    <!-- ==================================================================
         CHAT MAIN
         ================================================================== -->

    <section class="chat-main">
        <?php if (!empty($memoryReview) || !empty($memoryReviewError)): ?>
            <?php require DIR_TPL . '/memory_review.tpl.php'; ?>
        <?php endif; ?>


        <!-- --------------------------------------------------------------
             MESSAGES
             -------------------------------------------------------------- -->

        <div class="chat-messages" id="chat_messages" data-active-chat-id="<?= (int)($active_chat_id ?? 0) ?>">
          <?php echo $messages ?? ''; ?>
        </div>


        <!-- ==================================================================
             COMPOSER
             ================================================================== -->

        <div class="chat-composer">
            <div id="attachment_preview" class="attachment-preview" hidden>
                <img id="attachment_thumbnail" alt="Csatolt kép">
                <span id="attachment_name"></span>
                <button type="button" id="attachment_remove" aria-label="Csatolmány eltávolítása">×</button>
            </div>
            <details id="document_preview" class="document-preview" hidden open>
                <summary><?= LANG['CHAT_DOCUMENT_PREVIEW'] ?></summary>
                <pre id="document_text"></pre>
            </details>
            <div id="attachment_feedback" class="attachment-feedback" role="status" aria-live="polite"></div>


            <div class="composer-inner">


                <!-- Ritkább műveletek -->

                <div class="attachment-actions">
                    <button type="button" class="composer-action" id="chat_add"
                        aria-label="Csatolmány hozzáadása" aria-expanded="false" aria-controls="attachment_menu">
                        <?= $this->icon('plus') ?>
                    </button>
                    <div id="attachment_menu" class="attachment-menu" hidden>
                        <button type="button" id="attach_image">Kép csatolása</button>
                        <button type="button" id="attach_document"><?= LANG['CHAT_DOCUMENT_ATTACH'] ?></button>
                        <button type="button" disabled title="Fejlesztés alatt">Videó hozzáadása — hamarosan</button>
                    </div>
                    <input type="file" id="attachment_file" accept="image/jpeg,image/png,image/webp" hidden>
                    <input type="file" id="document_file" accept="<?= \mara\core\ChatDocument::accept() ?>"
                           data-token="<?= htmlspecialchars((string)($_SESSION['chat_turn_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           data-loading="<?= LANG['CHAT_DOCUMENT_LOADING'] ?>"
                           data-error="<?= LANG['CHAT_DOCUMENT_UPLOAD_ERROR'] ?>" hidden>
                </div>


                <!-- Message input -->

                <textarea
                    id="chat_message"
                    name="chat_message"
                    class="composer-input"
                    rows="1"
                    placeholder="Írj egy üzenetet..."></textarea>


                <!-- Gyakori műveletek -->

                <div class="composer-actions">


                <!-- textarea/input -->

                <button type="button" class="composer-send" aria-label="Küldés" id="chat_send">
                    <?= $this->icon('send') ?>
                </button>

                <button type="button" class="composer-action" aria-label="Hangkimenet" id="btn_speaker">
                    <?= $this->icon('speaker') ?>
                </button>

                <button type="button" class="composer-action" aria-label="Beszédfelismerés" id="btn_mic">
                    <?= $this->icon('microphone') ?>
                </button>


                </div>

            </div>


            <!-- ----------------------------------------------------------
                 NORMAL STATUS
                 ---------------------------------------------------------- -->

            <div class="chat-status">

                <span
                    class="status"
                    id="status">
                </span>

            </div>


        </div>


    </section>

    <dialog id="chat-reply-editor" class="chat-reply-editor" aria-labelledby="chat-reply-editor-title">
        <form>
            <label id="chat-reply-editor-title" for="chat-reply-text"><?= htmlspecialchars(LANG['CHAT_TURN_EDIT']) ?></label>
            <textarea id="chat-reply-text" rows="10" required></textarea>
            <p role="alert" class="chat-reply-error" hidden></p>
            <div class="chat-reply-editor-actions">
                <button type="button" class="btn" data-cancel><?= htmlspecialchars(LANG['CANCEL']) ?></button>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(LANG['SAVE']) ?></button>
            </div>
        </form>
    </dialog>


</div>		


        </main>
