<!DOCTYPE html>
<html lang="hu">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mara - <?php echo LANG['LOGIN']; ?></title>
    <link rel="stylesheet" href="<?php echo DIR_CSS.'/basic.css'; ?>">
  </head>
  <body>
    <div class="login-page">
      <div class="login-box">
        <div class="login-title">
            Mara
        </div>
        <div class="login-subtitle">
            <?php echo LANG['LOGIN']; ?>
        </div>
    <?php if (!empty($error)): ?>
        <div class="login-error">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
        <form
          method="POST"
          action="<?php echo DIR_HOST.'/auth/dologin'; ?>"
          class="login-form"
        >
          <div class="form-group">
              <label for="email">
                  <?php echo LANG['EMAIL']; ?>
              </label>
              <input
                  type="email"
                  name="email"
                  id="email"
                  autocomplete="username"
                  required
                  autofocus
              >
          </div>
          <div class="form-group">
              <label for="pass">
                  <?php echo LANG['PASSWORD']; ?>
              </label>
              <input
                  type="password"
                  name="pass"
                  id="pass"
                  autocomplete="current-password"
                  required
              >
          </div>
          <button
              type="submit"
              class="save-btn"
              style="width:100%; margin-top:15px;"
          >
              <?php echo LANG['LOGIN']; ?>
          </button>
        </form>
      </div>
    </div>
  </body>
</html>