<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token"
        content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <title>MaraImg</title>
  <link rel="stylesheet"
        href="<?= htmlspecialchars(DIR_CSS . '/image.css', ENT_QUOTES, 'UTF-8') ?>">
  <script defer
          src="<?= htmlspecialchars(DIR_JS . '/image.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="mara-image"
      data-backend="<?= htmlspecialchars($backend, ENT_QUOTES, 'UTF-8') ?>"
      data-api="<?= htmlspecialchars(DIR_HOST . '/image_ajax', ENT_QUOTES, 'UTF-8') ?>">
