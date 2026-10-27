<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

$submitted = false;
$deliveryFailed = false;
$mailConfigured = APP_URL !== ''
    && MAIL_HOST !== ''
    && filter_var(MAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL) !== false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submitted = true;
    if ($mailConfigured) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $stmt = getDb()->prepare(
            "SELECT userID, email, displayName FROM tblUsers
             WHERE email = ? AND accountStatus <> 'disabled' LIMIT 1"
        );
        $stmt->execute([$email]);
        $account = filter_var($email, FILTER_VALIDATE_EMAIL) ? $stmt->fetch() : false;

        if ($account) {
            $token = issuePasswordResetToken(getDb(), $account['userID']);
            try {
                sendPasswordResetEmail($account['email'], $account['displayName'] ?: $account['email'], $token);
            } catch (Throwable $error) {
                getDb()->prepare(
                    'UPDATE tblPasswordResetTokens SET consumedAt = NOW() WHERE tokenHash = ?'
                )->execute([hash('sha256', $token)]);
                error_log('Password reset email delivery failed: ' . $error->getMessage());
                $deliveryFailed = true;
            }
        }
    } else {
        $deliveryFailed = true;
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <main class="auth-card">
    <h1>Forgot password?</h1>
    <?php if ($submitted && !$deliveryFailed): ?>
      <p>If an eligible account matches that email address, look for a password-reset link in your inbox or spam folder. Then:</p>
      <ol>
        <li>Open the link before it expires.</li>
        <li>Choose and confirm your new password.</li>
        <li>Return to sign in with the new password.</li>
      </ol>
      <p>If no link arrives, contact the system administrator.</p>
    <?php else: ?>
      <?php if ($deliveryFailed): ?>
        <div class="alert danger" role="alert">
          We couldn't send a password-reset email right now. Please try again later or contact the system administrator.
        </div>
        <p>For security, we can't confirm whether an email address matches an account. You can try sending the request again below.</p>
      <?php else: ?>
        <p>Enter the email address used for your account. If it matches an eligible account, we will email a secure link to create a new password.</p>
      <?php endif; ?>
      <form method="POST" class="auth-form">
        <?php echo csrf_field(); ?>
        <label>Email
          <input type="email" name="email" maxlength="254" autocomplete="email" required>
        </label>
        <button type="submit">Send reset link</button>
      </form>
    <?php endif; ?>
    <p><a href="<?php echo e(url('/auth/login.php')); ?>">Return to sign in</a></p>
  </main>
</body>
</html>
