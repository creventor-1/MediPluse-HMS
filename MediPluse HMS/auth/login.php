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

$staffLoginRoles = array_filter($loginRoles, static function ($key) {
    return $key !== 'patient';
}, ARRAY_FILTER_USE_KEY);

$requestedSectionKey = trim($_GET['section'] ?? '');
$requestedSection = $sections[$requestedSectionKey] ?? null;

// Determine active tab: patient or staff
$initialType = trim($_GET['type'] ?? '');
if ($initialType === '') {
    $initialType = ($requestedSection !== null) ? 'staff' : 'patient';
}
$activeTab = ($initialType === 'staff') ? 'staff' : 'patient';
$selectedRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $portalType = trim($_POST['portal_type'] ?? 'patient');
    $activeTab = ($portalType === 'staff') ? 'staff' : 'patient';
    $selectedRole = trim($_POST['account_type'] ?? '');

    if ($portalType === 'patient') {
        $selectedRole = 'patient';
    }

    if ($username === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } elseif ($selectedRole === '' || !isset($loginRoles[$selectedRole])) {
        $error = 'Choose your account type, then enter your username or email and password.';
    } else {
        $user = attemptLogin(getDb(), $username, $password);
        if ($user) {
            $userRoleKey = roleKey((string) $user['role']);

            if ($portalType === 'patient' && $userRoleKey !== 'patient') {
                $error = 'This account has a staff role (' . e($user['role']) . '). Please switch to the Staff Workspace tab to sign in.';
            } elseif ($portalType === 'staff' && $userRoleKey === 'patient') {
                $error = 'This is a Patient account. Please switch to the Patient Portal tab to sign in.';
            } elseif ($userRoleKey === $selectedRole || ($portalType === 'patient' && $userRoleKey === 'patient')) {
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user'] = $user;
                $_SESSION['authVersion'] = $user['sessionVersion'];
                redirect(roleDashboardPath($user['role']));
            } else {
                $error = 'The selected account type does not match your assigned role.';
            }
        } else {
            $error = 'The selected account type or sign-in details were not recognized.';
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <div class="auth-card login-card" style="width: min(540px, 100%);">
    <div class="brand-box">
      <div class="brand-mark">🩺</div>
      <h1><?php echo e(APP_NAME); ?></h1>
      <p>Healthcare &amp; Hospital Management</p>
    </div>

    <!-- Portal Mode Selector Tabs -->
    <div class="login-tabs" role="tablist" style="display:flex; gap:8px; margin: 16px 0 20px; background:#f1f5f9; padding:6px; border-radius:12px;">
      <button type="button" class="tab-trigger <?php echo $activeTab === 'patient' ? 'active' : ''; ?>" id="tab-patient-login" role="tab" aria-selected="<?php echo $activeTab === 'patient' ? 'true' : 'false'; ?>" aria-controls="pane-patient-login" style="flex:1; padding:10px 14px; border-radius:8px; border:none; font-weight:600; cursor:pointer; background:<?php echo $activeTab === 'patient' ? '#ffffff' : 'transparent'; ?>; color:<?php echo $activeTab === 'patient' ? '#0284c7' : '#64748b'; ?>; box-shadow:<?php echo $activeTab === 'patient' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none'; ?>;">
        👤 Patient Portal
      </button>
      <button type="button" class="tab-trigger <?php echo $activeTab === 'staff' ? 'active' : ''; ?>" id="tab-staff-login" role="tab" aria-selected="<?php echo $activeTab === 'staff' ? 'true' : 'false'; ?>" aria-controls="pane-staff-login" style="flex:1; padding:10px 14px; border-radius:8px; border:none; font-weight:600; cursor:pointer; background:<?php echo $activeTab === 'staff' ? '#ffffff' : 'transparent'; ?>; color:<?php echo $activeTab === 'staff' ? '#0284c7' : '#64748b'; ?>; box-shadow:<?php echo $activeTab === 'staff' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none'; ?>;">
        🩺 Staff Workspace
      </button>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert danger" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($requestedSection !== null): ?>
      <div class="alert info" role="status">
        You selected <strong><?php echo e($requestedSection['label']); ?></strong>.
        Sign in with your staff account to continue. Access depends on your permissions.
      </div>
    <?php endif; ?>

    <!-- Pane 1: Patient Sign In -->
    <section id="pane-patient-login" class="login-pane" role="tabpanel" aria-labelledby="tab-patient-login" style="<?php echo $activeTab === 'patient' ? '' : 'display:none;'; ?>">
      <form method="POST" class="auth-form" autocomplete="on">
        <input type="hidden" name="portal_type" value="patient">
        <input type="hidden" name="account_type" value="patient">
        <?php echo csrf_field(); ?>

        <h2>Sign in to Patient Portal</h2>
        <p style="margin: -6px 0 16px; font-size: 0.9rem; color: #64748b;">
          Access your medical records, laboratory results, bills, and doctor appointments.
        </p>

        <label>
          Email address or username
          <input type="text" name="username" autocomplete="username" required <?php echo $activeTab === 'patient' ? 'autofocus' : ''; ?> placeholder="patient@example.com or username">
        </label>

        <label>
          Password
          <input type="password" name="password" autocomplete="current-password" required placeholder="Enter your password">
        </label>

        <button type="submit" style="background: linear-gradient(135deg, #0284c7, #0369a1);">Sign in to Patient Portal</button>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:0.9rem;">
          <a href="<?php echo e(url('/auth/forgot_password.php')); ?>">Forgot password?</a>
          <span>New patient? <a href="<?php echo e(url('/auth/signup.php?type=patient')); ?>"><strong>Register here</strong></a></span>
        </div>
      </form>
    </section>

    <!-- Pane 2: Staff Sign In -->
    <section id="pane-staff-login" class="login-pane" role="tabpanel" aria-labelledby="tab-staff-login" style="<?php echo $activeTab === 'staff' ? '' : 'display:none;'; ?>">
      <form method="POST" class="auth-form" autocomplete="on">
        <input type="hidden" name="portal_type" value="staff">
        <?php echo csrf_field(); ?>

        <h2>Staff &amp; Clinical Sign In</h2>
        <p style="margin: -6px 0 16px; font-size: 0.9rem; color: #64748b;">
          Authorised workspace for hospital medical, administrative, and laboratory teams.
        </p>

        <label>
          Staff Username or email
          <input type="text" name="username" autocomplete="username" required <?php echo $activeTab === 'staff' ? 'autofocus' : ''; ?> placeholder="username or hospital email">
        </label>

        <label>
          Password
          <input type="password" name="password" autocomplete="current-password" required placeholder="Enter your staff password">
        </label>

        <fieldset class="role-picker">
          <legend>Choose your account type</legend>
          <p>Select the role assigned to your account. This selection checks your sign-in; it does not change your role or permissions.</p>
          <div class="role-picker-grid">
            <?php foreach ($staffLoginRoles as $roleKey => $role): ?>
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

        <button type="submit">Sign in to Staff Workspace</button>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:0.9rem;">
          <a href="<?php echo e(url('/auth/forgot_password.php')); ?>">Forgot password?</a>
          <span>Staff access request? <a href="<?php echo e(url('/auth/signup.php?type=staff')); ?>"><strong>Apply here</strong></a></span>
        </div>
      </form>
    </section>

    <p style="margin-top: 24px; text-align: center;"><a href="<?php echo e(url('/index.php')); ?>">← Back to MediPulse homepage</a></p>
  </div>

  <script>
    (function () {
      var tabPatient = document.getElementById('tab-patient-login');
      var tabStaff = document.getElementById('tab-staff-login');
      var panePatient = document.getElementById('pane-patient-login');
      var paneStaff = document.getElementById('pane-staff-login');

      function switchLoginTab(toStaff) {
        tabPatient.classList.toggle('active', !toStaff);
        tabStaff.classList.toggle('active', toStaff);
        tabPatient.setAttribute('aria-selected', !toStaff ? 'true' : 'false');
        tabStaff.setAttribute('aria-selected', toStaff ? 'true' : 'false');

        tabPatient.style.background = !toStaff ? '#ffffff' : 'transparent';
        tabPatient.style.color = !toStaff ? '#0284c7' : '#64748b';
        tabPatient.style.boxShadow = !toStaff ? '0 1px 3px rgba(0,0,0,0.1)' : 'none';

        tabStaff.style.background = toStaff ? '#ffffff' : 'transparent';
        tabStaff.style.color = toStaff ? '#0284c7' : '#64748b';
        tabStaff.style.boxShadow = toStaff ? '0 1px 3px rgba(0,0,0,0.1)' : 'none';

        panePatient.style.display = !toStaff ? 'block' : 'none';
        paneStaff.style.display = toStaff ? 'block' : 'none';
      }

      tabPatient.addEventListener('click', function () { switchLoginTab(false); });
      tabStaff.addEventListener('click', function () { switchLoginTab(true); });
    })();
  </script>
</body>
</html>
