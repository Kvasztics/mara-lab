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
        <div class="models-list knowledge-list">
          <?php echo $knowledge; ?>
        </div>
    </section>
</main><!-- /.app-content -->