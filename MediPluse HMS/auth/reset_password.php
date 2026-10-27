<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$completed = false;
$validToken = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = getDb()->prepare(
        'SELECT 1 FROM tblPasswordResetTokens WHERE tokenHash = ?
         AND consumedAt IS NULL AND expiresAt > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $validToken = (bool) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } elseif (consumePasswordResetToken(getDb(), $token, $password)) {
        $completed = true;
    } else {
        $validToken = false;
    }
}

$pageTitle = 'Set a new password';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <main class="auth-card">
    <?php if ($completed): ?>
      <h1>Password updated</h1>
      <p>Your password has been changed. Please sign in with your new password.</p>
      <a class="button-link" href="<?php echo e(url('/auth/login.php')); ?>">Return to sign in</a>
    <?php elseif (!$validToken): ?>
      <h1>Reset link unavailable</h1>
      <p>This link is invalid, expired, or has already been used. Request a new reset link to continue.</p>
      <a class="button-link" href="<?php echo e(url('/auth/forgot_password.php')); ?>">Request another link</a>
    <?php else: ?>
      <h1>Choose a new password</h1>
      <?php if ($error !== ''): ?>
        <div class="alert danger" role="alert"><?php echo e($error); ?></div>
      <?php endif; ?>
      <form method="POST" class="auth-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo e($token); ?>">
        <label>New password (at least 12 characters)
          <input type="password" name="password" minlength="12" autocomplete="new-password" required>
        </label>
        <label>Confirm new password
          <input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required>
        </label>
        <button type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
