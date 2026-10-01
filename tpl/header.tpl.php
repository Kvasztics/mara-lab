<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mara 2.1</title>

    <link rel="stylesheet"
          href="<?php echo DIR_CSS.'/basic.css'; ?>"
          type="text/css"
          media="all">

    <script src="<?php echo DIR_JS.'/settings.js'; ?>"></script>

    <script>
        getSettings('<?php echo DIR_HOST.'/main/jssettings'; ?>');
    </script>

    <script src="<?php echo DIR_JS.'/scripts.js?ver=2.1'; ?>"></script>
    <script src="<?php echo DIR_JS.'/marked.min.js'; ?>"></script>
    <script src="<?php echo DIR_JS.'/syntax.js'; ?>"></script>
    <script src="<?php echo DIR_JS.'/model.js?ver=2.1'; ?>"></script>
    <script src="<?php echo DIR_JS.'/speech.js?ver=2.1'; ?>"></script>
  </head>
  <body>
    <div id="_loader"></div>
    <div class="mara-app">
<!-- ================================================================
      HEADER
      ================================================================ -->
    <header class="app-header">
      <div class="app-header-left">
        <button
          type="button"
          class="sidebar-toggle"
          id="sidebar-toggle"
          aria-label="Menü"
          aria-controls="sidebar"
          aria-expanded="false"
          onclick="toggleSidebar();">
          <?= $this->icon('menu') ?>
        </button>
        <a href="<?php echo DIR_HOST.'/main/view'; ?>"
          class="app-brand">
          Mara Lab
        </a>
      </div>
      <div class="app-header-right">
            <!-- később: user / profil / egyéb -->
      </div>
    </header>
<!-- ================================================================
      APP BODY
      ================================================================ -->
    <div class="app-body">
<!-- ============================================================
     SIDEBAR
     ============================================================ -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-content sidebar-inner">
<!-- ========================================================
      BOTTOM NAVIGATION
      ======================================================== -->
          <nav class="sidebar-nav sidebar-navigation">
            <a
              href=""
              onclick="newChat();"
              class="menu-link">
              <?= $this->icon('new-chat') ?>
              <span><?php echo LANG['NEW_CHAT']; ?></span>
            </a>
            <a
              href="<?php echo DIR_HOST.'/main/settings'; ?>"
              onclick="settings();"
              class="menu-link">
              <?= $this->icon('settings') ?>
              <span><?php echo LANG['SETTINGS']; ?></span>
            </a>
            <a
              href="<?php echo DIR_HOST.'/main/models'; ?>"
              class="menu-link">
              <?= $this->icon('models') ?>
              <span><?php echo LANG['MODEL_SETTINGS']; ?></span>
            </a>
            <a
              href="<?php echo DIR_HOST.'/main/rag'; ?>"
              class="menu-link">
              <?= $this->icon('knowledge') ?>
              <span><?php echo LANG['KNOWLEDGE']; ?></span>
            </a>            
            <a
              href="<?php echo DIR_HOST.'/auth/logout'; ?>"
              class="menu-link">
              <?= $this->icon('logout') ?>
              <span><?php echo LANG['LOGOUT']; ?></span>
            </a>
          </nav>
<!-- ========================================================
      MODEL SELECTOR
      ======================================================== -->
          <div class="sidebar-model">
            <div class="section-label">
              <?php echo LANG['AVAILABLE_MODELS']; ?>
            </div>
            <select
              class="model-selector"
              id="model_list">
              <?php echo $models; ?>
            </select>
          </div>
<!-- ========================================================
      CONVERSATIONS
      ======================================================== -->
          <div class="sidebar-conversations">
            <div class="section-label">
              <?php echo LANG['CHATS']; ?>
            </div>
            <ul class="chat-list" id="chat_list">
              <?php echo $chats; ?>
            </ul>
          </div>
        </div>
      </aside>
      <!-- Mobilon a sidebar mögötti sötét réteg -->
      <div
          class="sidebar-overlay"
          id="sidebar-overlay"
          onclick="toggleSidebar(false);">
      </div>