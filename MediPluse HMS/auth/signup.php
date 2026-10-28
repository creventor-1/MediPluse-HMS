<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

if (isset($_SESSION['user'])) {
    redirect('/dashboard/index.php');
}

$error = '';
$activeTab = ($_GET['type'] ?? '') === 'staff' ? 'staff' : 'patient';

// Patient form fields
$patientName = '';
$patientEmail = '';
$patientGender = 'Male';
$patientDob = '';
$patientPhone = '';
$patientAddress = '';

// Staff form fields
$staffName = '';
$staffEmail = '';
$requestedRole = '';
$doctorSpecialty = '';
$doctorSpecialties = doctorSpecialties();
$signupRoles = publicSignupRoles(getDb());
$staffRoles = array_filter($signupRoles, static function ($key) {
    return $key !== 'patient';
}, ARRAY_FILTER_USE_KEY);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accountType = trim($_POST['account_type'] ?? 'patient');
    $activeTab = $accountType === 'staff' ? 'staff' : 'patient';

    if ($accountType === 'patient') {
        $patientName = trim($_POST['name'] ?? '');
        $patientEmail = strtolower(trim($_POST['email'] ?? ''));
        $patientGender = trim($_POST['gender'] ?? '');
        $patientDob = trim($_POST['date_of_birth'] ?? '');
        $patientPhone = trim($_POST['phone_number'] ?? '');
        $patientAddress = trim($_POST['address'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (mb_strlen($patientName) < 2 || mb_strlen($patientName) > 100) {
            $error = 'Enter a name between 2 and 100 characters.';
        } elseif (!filter_var($patientEmail, FILTER_VALIDATE_EMAIL) || strlen($patientEmail) > 254) {
            $error = 'Enter a valid email address.';
        } elseif (!in_array($patientGender, ['Male', 'Female', 'Other'], true)) {
            $error = 'Choose a valid gender option.';
        } elseif ($patientDob === '') {
            $error = 'Enter your date of birth.';
        } elseif (strlen($password) < 12) {
            $error = 'Choose a password with at least 12 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $user = registerDirectPatientAccount(
                    getDb(),
                    $patientName,
                    $patientEmail,
                    $password,
                    $patientGender,
                    $patientDob,
                    $patientPhone,
                    $patientAddress
                );

                // Instantly authenticate and redirect to Patient Portal
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user'] = [
                    'id'            => $user['id'],
                    'username'      => $user['username'],
                    'email'         => $user['email'],
                    'name'          => $user['name'],
                    'role'          => $user['role'],
                    'accountStatus' => $user['accountStatus'],
                    'sessionVersion'=> $user['sessionVersion'],
                ];
                $_SESSION['authVersion'] = $user['sessionVersion'];

                redirect('/patient/portal.php');
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That email address is already associated with an account.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($accountType === 'staff') {
        $staffName = trim($_POST['name'] ?? '');
        $staffEmail = strtolower(trim($_POST['email'] ?? ''));
        $requestedRole = trim($_POST['requested_role'] ?? '');
        $doctorSpecialty = trim($_POST['doctor_specialty'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (mb_strlen($staffName) < 2 || mb_strlen($staffName) > 100) {
            $error = 'Enter a name between 2 and 100 characters.';
        } elseif (!filter_var($staffEmail, FILTER_VALIDATE_EMAIL) || strlen($staffEmail) > 254) {
            $error = 'Enter a valid email address.';
        } elseif (!isset($staffRoles[$requestedRole])) {
            $error = 'Choose a valid hospital staff role.';
        } elseif ($requestedRole === 'doctor' && $doctorSpecialty === '') {
            $error = 'Choose your medical specialty.';
        } elseif (strlen($password) < 12) {
            $error = 'Choose a password with at least 12 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                createPendingAccount(getDb(), $staffName, $staffEmail, $password, $requestedRole, $doctorSpecialty);
                $pageTitle = 'Request received';
                require __DIR__ . '/../partials/head.php';
                ?>
                <body class="auth-body">
                  <main class="auth-card">
                    <h1>Account request received</h1>
                    <p>Your request for the <?php echo e($staffRoles[$requestedRole] ?? $requestedRole); ?> role has been received. An authorized manager must review it and assign access before you can use protected sections.</p>
                    <?php if ($requestedRole === 'doctor'): ?>
                      <p>After approval, your name and specialty will appear in the Doctors directory and appointment booking list.</p>
                    <?php endif; ?>
                    <a class="button-link" href="<?php echo e(url('/auth/login.php')); ?>">Return to sign in</a>
                  </main>
                </body>
                </html>
                <?php
                exit;
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That email address is already associated with an account.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <main class="auth-card signup-card" style="width: min(560px, 100%);">
    <div class="brand-box">
      <div class="brand-mark"><?php echo icon('stethoscope', 'brand-icon-svg', 28); ?></div>
      <h1><?php echo e(APP_NAME); ?></h1>
      <p>Create an account</p>
    </div>

    <!-- Mode Selection Tabs -->
    <div class="account-type-tabs" role="tablist" style="display:flex; gap:8px; margin: 16px 0 20px; background:#f1f5f9; padding:6px; border-radius:12px;">
      <button type="button" class="tab-trigger <?php echo $activeTab === 'patient' ? 'active' : ''; ?>" id="tab-patient" role="tab" aria-selected="<?php echo $activeTab === 'patient' ? 'true' : 'false'; ?>" aria-controls="pane-patient" style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:8px; border:none; font-weight:600; cursor:pointer; background:<?php echo $activeTab === 'patient' ? '#ffffff' : 'transparent'; ?>; color:<?php echo $activeTab === 'patient' ? '#0284c7' : '#64748b'; ?>; box-shadow:<?php echo $activeTab === 'patient' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none'; ?>;">
        <?php echo icon('user', 'tab-icon', 18); ?> <span>Patient Registration</span>
      </button>
      <button type="button" class="tab-trigger <?php echo $activeTab === 'staff' ? 'active' : ''; ?>" id="tab-staff" role="tab" aria-selected="<?php echo $activeTab === 'staff' ? 'true' : 'false'; ?>" aria-controls="pane-staff" style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:8px; border:none; font-weight:600; cursor:pointer; background:<?php echo $activeTab === 'staff' ? '#ffffff' : 'transparent'; ?>; color:<?php echo $activeTab === 'staff' ? '#0284c7' : '#64748b'; ?>; box-shadow:<?php echo $activeTab === 'staff' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none'; ?>;">
        <?php echo icon('stethoscope', 'tab-icon', 18); ?> <span>Staff Request</span>
      </button>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert danger" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <!-- Pane 1: Direct Patient Registration -->
    <section id="pane-patient" class="tab-pane" role="tabpanel" aria-labelledby="tab-patient" style="<?php echo $activeTab === 'patient' ? '' : 'display:none;'; ?>">
      <div class="alert info" style="margin-bottom: 16px; font-size: 0.9rem;">
        Direct patient registration — no approval needed. You can log in and access your portal immediately.
      </div>
      <form method="POST" class="auth-form signup-form" id="patient-form" autocomplete="on">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="account_type" value="patient">

        <label>Full name
          <input name="name" maxlength="100" value="<?php echo e($patientName); ?>" autocomplete="name" required placeholder="e.g. Chukwuemeka Okonkwo">
        </label>

        <label>Email address
          <input type="email" name="email" maxlength="254" value="<?php echo e($patientEmail); ?>" autocomplete="email" required placeholder="name@example.com">
        </label>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <label>Gender
            <select name="gender" required>
              <option value="Male" <?php echo $patientGender === 'Male' ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo $patientGender === 'Female' ? 'selected' : ''; ?>>Female</option>
              <option value="Other" <?php echo $patientGender === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </label>
          <label>Date of birth
            <input type="date" name="date_of_birth" value="<?php echo e($patientDob); ?>" max="<?php echo date('Y-m-d'); ?>" required>
          </label>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <label>Phone number
            <input type="tel" name="phone_number" maxlength="20" value="<?php echo e($patientPhone); ?>" placeholder="08012345678">
          </label>
          <label>Residential address
            <input type="text" name="address" maxlength="150" value="<?php echo e($patientAddress); ?>" placeholder="e.g. Victoria Island, Lagos">
          </label>
        </div>

        <label for="signup-patient-password">Password</label>
        <div class="password-field">
          <input id="signup-patient-password" type="password" name="password" minlength="12" autocomplete="new-password" aria-describedby="patient-password-feedback" required>
          <button class="password-visibility" type="button" data-password-toggle="signup-patient-password" aria-label="Show password" aria-pressed="false">Show</button>
        </div>
        <p class="password-feedback" id="patient-password-feedback" aria-live="polite">Use at least 12 characters.</p>

        <label for="confirm-patient-password">Confirm password</label>
        <div class="password-field">
          <input id="confirm-patient-password" type="password" name="confirm_password" minlength="12" autocomplete="new-password" aria-describedby="confirm-patient-password-feedback" required>
          <button class="password-visibility" type="button" data-password-toggle="confirm-patient-password" aria-label="Show confirm password" aria-pressed="false">Show</button>
        </div>
        <p class="password-feedback" id="confirm-patient-password-feedback" aria-live="polite">Re-enter your password to confirm it.</p>

        <button type="submit">Create Patient Account &amp; Sign in</button>
      </form>
    </section>

    <!-- Pane 2: Staff Account Request -->
    <section id="pane-staff" class="tab-pane" role="tabpanel" aria-labelledby="tab-staff" style="<?php echo $activeTab === 'staff' ? '' : 'display:none;'; ?>">
      <div class="alert info" style="margin-bottom: 16px; font-size: 0.9rem;">
        Staff registrations require administrative review and verification before account activation.
      </div>
      <form method="POST" class="auth-form signup-form" id="staff-form" autocomplete="on">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="account_type" value="staff">

        <label>Full name
          <input name="name" maxlength="100" value="<?php echo e($staffName); ?>" autocomplete="name" required placeholder="e.g. Dr. Amina Bello">
        </label>

        <label>Email address
          <input type="email" name="email" maxlength="254" value="<?php echo e($staffEmail); ?>" autocomplete="email" required placeholder="staff@medipulse.com">
        </label>

        <label for="requested-role">Section or account type</label>
        <select id="requested-role" name="requested_role" required>
          <option value="">Choose the area you are applying to join</option>
          <?php foreach ($staffRoles as $roleKey => $roleName): ?>
            <option value="<?php echo e($roleKey); ?>" <?php echo $requestedRole === $roleKey ? 'selected' : ''; ?>>
              <?php echo e($roleName); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label id="doctor-specialty-field" hidden>Medical specialty
          <select id="doctor-specialty" name="doctor_specialty" autocomplete="organization-title">
            <option value="">Choose your medical specialty</option>
            <?php foreach ($doctorSpecialties as $specialty): ?>
              <option value="<?php echo e($specialty); ?>" <?php echo $doctorSpecialty === $specialty ? 'selected' : ''; ?>>
                <?php echo e($specialty); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <p class="hint">This is a request only. Your account stays pending until an authorized manager reviews it.</p>

        <label for="signup-password">Password</label>
        <div class="password-field">
          <input id="signup-password" type="password" name="password" minlength="12" autocomplete="new-password" aria-describedby="password-feedback" required>
          <button class="password-visibility" type="button" data-password-toggle="signup-password" aria-label="Show password" aria-pressed="false">Show</button>
        </div>
        <p class="password-feedback" id="password-feedback" aria-live="polite">Use at least 12 characters.</p>

        <label for="confirm-password">Confirm password</label>
        <div class="password-field">
          <input id="confirm-password" type="password" name="confirm_password" minlength="12" autocomplete="new-password" aria-describedby="confirm-password-feedback" required>
          <button class="password-visibility" type="button" data-password-toggle="confirm-password" aria-label="Show confirm password" aria-pressed="false">Show</button>
        </div>
        <p class="password-feedback" id="confirm-password-feedback" aria-live="polite">Re-enter your password to confirm it.</p>

        <button type="submit">Request account</button>
      </form>
    </section>

    <p style="margin-top: 20px;">Already registered? <a href="<?php echo e(url('/auth/login.php')); ?>">Sign in</a></p>
    <p><a href="<?php echo e(url('/index.php')); ?>">Back to MediPulse homepage</a></p>
  </main>

  <script>
    (function () {
      // Tab Switching
      var tabPatient = document.getElementById('tab-patient');
      var tabStaff = document.getElementById('tab-staff');
      var panePatient = document.getElementById('pane-patient');
      var paneStaff = document.getElementById('pane-staff');

      function switchTab(toStaff) {
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

      tabPatient.addEventListener('click', function () { switchTab(false); });
      tabStaff.addEventListener('click', function () { switchTab(true); });

      // Staff Form & Specialty Toggler
      var requestedRole = document.getElementById('requested-role');
      var specialtyField = document.getElementById('doctor-specialty-field');
      var specialty = document.getElementById('doctor-specialty');

      function updateDoctorSpecialtyField() {
        var isDoctor = requestedRole.value === 'doctor';
        specialtyField.hidden = !isDoctor;
        specialty.required = isDoctor;
        if (!isDoctor) {
          specialty.value = '';
        }
      }

      if (requestedRole) {
        requestedRole.addEventListener('change', updateDoctorSpecialtyField);
        updateDoctorSpecialtyField();
      }

      // Password Feedback Handler Helper
      function setupPasswordFeedback(pwdId, confId, pwdFbId, confFbId, formId) {
        var pwd = document.getElementById(pwdId);
        var conf = document.getElementById(confId);
        var pwdFb = document.getElementById(pwdFbId);
        var confFb = document.getElementById(confFbId);
        var form = document.getElementById(formId);

        if (!pwd || !conf || !pwdFb || !confFb || !form) return;

        function updatePwd() {
          var valid = pwd.value.length >= 12;
          pwdFb.textContent = valid
            ? 'Password meets the 12-character minimum.'
            : 'Use at least 12 characters (' + pwd.value.length + '/12).';
          pwdFb.classList.toggle('is-valid', valid);
          pwdFb.classList.toggle('is-invalid', pwd.value.length > 0 && !valid);
          pwd.setAttribute('aria-invalid', pwd.value.length > 0 && !valid ? 'true' : 'false');
        }

        function updateConf() {
          if (conf.value === '') {
            confFb.textContent = 'Re-enter your password to confirm it.';
            confFb.classList.remove('is-valid', 'is-invalid');
            conf.setAttribute('aria-invalid', 'false');
            return;
          }
          var matches = conf.value === pwd.value;
          confFb.textContent = matches ? 'Passwords match.' : 'Passwords do not match yet.';
          confFb.classList.toggle('is-valid', matches);
          confFb.classList.toggle('is-invalid', !matches);
          conf.setAttribute('aria-invalid', matches ? 'false' : 'true');
        }

        pwd.addEventListener('input', function () {
          updatePwd();
          updateConf();
        });
        conf.addEventListener('input', updateConf);

        form.addEventListener('submit', function (event) {
          updatePwd();
          updateConf();
          if (pwd.value.length < 12 || pwd.value !== conf.value) {
            event.preventDefault();
            (pwd.value.length < 12 ? pwd : conf).focus();
          }
        });
      }

      setupPasswordFeedback('signup-patient-password', 'confirm-patient-password', 'patient-password-feedback', 'confirm-patient-password-feedback', 'patient-form');
      setupPasswordFeedback('signup-password', 'confirm-password', 'password-feedback', 'confirm-password-feedback', 'staff-form');

      // Password Toggle Handler
      document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          var input = document.getElementById(button.getAttribute('data-password-toggle'));
          if (!input) return;
          var reveal = input.type === 'password';
          input.type = reveal ? 'text' : 'password';
          button.textContent = reveal ? 'Hide' : 'Show';
          button.setAttribute('aria-label', (reveal ? 'Hide ' : 'Show ') + input.id);
          button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
          input.focus();
        });
      });
    })();
  </script>
</body>
</html>
