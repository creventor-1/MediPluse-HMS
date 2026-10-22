<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

if (isset($_SESSION['user'])) {
    redirect(roleDashboardPath((string) ($_SESSION['user']['role'] ?? '')));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $user = attemptLogin(getDb(), $username, $password);

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            redirect(roleDashboardPath($user['role']));
        }

        $error = 'Invalid credentials. Please try again.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <div class="auth-card">
    <div class="brand-box">
      <div class="brand-mark">🩺</div>
      <h1><?php echo e(APP_NAME); ?></h1>
      <p>Hospital Management System</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form" autocomplete="off">
      <h2>Sign in to your account</h2>
      <?php echo csrf_field(); ?>

      <label>
        Username
        <input type="text" name="username" placeholder="Enter your username" required autofocus>
      </label>

      <label>
        Password
        <input type="password" name="password" placeholder="Enter your password" required>
      </label>

      <button type="submit">Login</button>
    </form>

    <div class="auth-hint">
      Access is restricted to authorised hospital staff.
    </div>
    <a class="auth-back" href="<?php echo e(url('/index.php')); ?>">&larr; Back to home</a>
  </div>
</body>
</html>
