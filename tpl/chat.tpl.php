        <!-- ============================================================
             PAGE CONTENT
             ============================================================ -->

        <main class="app-content">


<!-- ======================================================================
     MARA LAB - CHAT
     ====================================================================== -->

<div class="chat-layout">


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
                    id="rate_user"
                    checked>

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
          <?php echo $messages; ?>
        </div>


        <!-- ==================================================================
             COMPOSER
             ================================================================== -->

        <div class="chat-composer">


            <div class="composer-inner">


                <!-- Ritkább műveletek -->

                <button type="button" class="composer-action" aria-label="További lehetőségek">
                    <?= $this->icon('plus') ?>
                </button>


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