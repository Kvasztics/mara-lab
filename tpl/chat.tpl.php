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


        <!-- TEST DATA -->

        <div class="test-data" id="model_metrics">

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


        <!-- --------------------------------------------------------------
             MESSAGES
             -------------------------------------------------------------- -->

        <div class="chat-messages" id="chat_messages">
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
                        <button type="button" disabled title="Fejlesztés alatt">Videó hozzáadása — hamarosan</button>
                    </div>
                    <input type="file" id="attachment_file" accept="image/jpeg,image/png,image/webp" hidden>
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


</div>		


        </main>
