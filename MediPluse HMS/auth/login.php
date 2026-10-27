<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

if (isset($_SESSION['user'])) {
    redirect(roleDashboardPath((string) ($_SESSION['user']['role'] ?? '')));
}

$error = '';
$sections = appSections();
$loginRoles = [
    'admin' => ['name' => 'Administrator', 'description' => 'System administration and assigned access', 'mark' => 'AD'],
    'supervisor' => ['name' => 'Supervisor', 'description' => 'Oversight of assigned hospital areas', 'mark' => 'SV'],
    'doctor' => ['name' => 'Doctor', 'description' => 'Clinical work and patient care', 'mark' => 'DR'],
    'receptionist' => ['name' => 'Receptionist', 'description' => 'Patient registration and appointments', 'mark' => 'RE'],
    'lab_technician' => ['name' => 'Lab Technician', 'description' => 'Laboratory tests and results', 'mark' => 'LT'],
    'billing_officer' => ['name' => 'Billing Officer', 'description' => 'Bills and recorded payments', 'mark' => 'BI'],
    'patient' => ['name' => 'Patient', 'description' => 'Patient account access', 'mark' => 'PA'],
];
$selectedRole = '';
$requestedSectionKey = trim($_GET['section'] ?? '');
$requestedSection = $sections[$requestedSectionKey] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $selectedRole = trim($_POST['account_type'] ?? '');
    if (!isset($loginRoles[$selectedRole])) {
        $error = 'Choose your account type, then enter your username or email and password.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $user = attemptLogin(getDb(), $username, $password);
        if ($user && roleKey((string) $user['role']) === $selectedRole) {
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['user'] = $user;
            $_SESSION['authVersion'] = $user['sessionVersion'];
            redirect(roleDashboardPath($user['role']));
        }

        $error = 'The selected account type or sign-in details were not recognized.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <div class="auth-card login-card">
    <div class="brand-box">
      <div class="brand-mark">🩺</div>
      <h1><?php echo e(APP_NAME); ?></h1>
      <p>Hospital Management System</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert danger" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($requestedSection !== null): ?>
      <div class="alert info" role="status">
        You selected <strong><?php echo e($requestedSection['label']); ?></strong>.
        Sign in to continue. Access depends on the permissions assigned to your account.
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form" autocomplete="on">
      <h2>Sign in to your account</h2>
      <?php echo csrf_field(); ?>

      <label>
        Username or email
        <input type="text" name="username" autocomplete="username" required autofocus>
      </label>

      <label>
        Password
        <input type="password" name="password" autocomplete="current-password" required>
      </label>

      <fieldset class="role-picker">
        <legend>Choose your account type</legend>
        <p>Select the role assigned to your account. This selection checks your sign-in; it does not change your role or permissions.</p>
        <div class="role-picker-grid">
          <?php foreach ($loginRoles as $roleKey => $role): ?>
            <label class="role-option">
              <input type="radio" name="account_type" value="<?php echo e($roleKey); ?>" <?php echo $selectedRole === $roleKey ? 'checked' : ''; ?> required>
              <span class="role-option-content">
                <span class="role-option-mark" aria-hidden="true"><?php echo e($role['mark']); ?></span>
                <span class="role-option-copy">
                  <strong><?php echo e($role['name']); ?></strong>
                  <small><?php echo e($role['description']); ?></small>
                </span>
                <span class="role-option-check" aria-hidden="true">✓</span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button type="submit">Sign in</button>
    </form>

    <p><a href="<?php echo e(url('/auth/forgot_password.php')); ?>">Forgot password?</a></p>
    <p>New to MediPulse? <a href="<?php echo e(url('/auth/signup.php')); ?>">Create an account</a></p>
  </div>
</body>
</html>
