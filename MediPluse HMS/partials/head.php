<?php
/**
 * Shared <head>. Expects (optionally) $pageTitle to be set by the
 * including page before this file is required.
 */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($pageTitle); ?> | <?php echo e(APP_NAME); ?></title>
  <script>
    (function () {
      var theme = 'dark';
      try {
        var savedTheme = localStorage.getItem('medipulse-theme');
        if (savedTheme === 'light' || savedTheme === 'dark') theme = savedTheme;
      } catch (error) {
        theme = 'dark';
      }
      document.documentElement.dataset.theme = theme;
    })();
  </script>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🩺</text></svg>">
  <link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
  <link rel="stylesheet" href="<?php echo e(url('assets/css/style_theme.css')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/style_theme.css'); ?>">
  <script src="<?php echo e(url('assets/js/theme.js')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/theme.js'); ?>" defer></script>
</head>
