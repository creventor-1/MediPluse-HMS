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
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230284c7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3'/><path d='M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4'/><circle cx='20' cy='10' r='2'/></svg>">
  <link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
  <link rel="stylesheet" href="<?php echo e(url('assets/css/style_theme.css')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/style_theme.css'); ?>">
  <script src="<?php echo e(url('assets/js/theme.js')); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/theme.js'); ?>" defer></script>
</head>
