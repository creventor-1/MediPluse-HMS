<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

if (isset($_SESSION['user'])) {
    redirect('/dashboard/index.php');
}

$error = '';
$name = '';
$email = '';
$doctorSpecialty = '';
$doctorSpecialties = doctorSpecialties();
$signupRoles = publicSignupRoles(getDb());
$requestedRole = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $requestedRole = trim($_POST['requested_role'] ?? '');
    $doctorSpecialty = trim($_POST['doctor_specialty'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Enter a name between 2 and 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Enter a valid email address.';
    } elseif (!isset($signupRoles[$requestedRole])) {
        $error = 'Choose a valid section or account type.';
    } elseif (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            createPendingAccount(getDb(), $name, $email, $password, $requestedRole, $doctorSpecialty);
            $pageTitle = 'Request received';
            require __DIR__ . '/../partials/head.php';
            ?>
            <body class="auth-body">
              <main class="auth-card">
                <h1>Account request received</h1>
                <p>Your request for the <?php echo e($signupRoles[$requestedRole]); ?> role has been received. An authorized manager must review it and assign access before you can use protected sections.</p>
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

$pageTitle = 'Create account';
require __DIR__ . '/../partials/head.php';
?>
<body class="auth-body">
  <main class="auth-card">
    <div class="brand-box">
      <div class="brand-mark">🩺</div>
      <h1><?php echo e(APP_NAME); ?></h1>
      <p>Request an account</p>
    </div>
    <?php if ($error !== ''): ?>
      <div class="alert danger" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>
    <form method="POST" class="auth-form signup-form" autocomplete="on">
      <?php echo csrf_field(); ?>
      <label>Full name
        <input name="name" maxlength="100" value="<?php echo e($name); ?>" autocomplete="name" required>
      </label>
      <label>Email
        <input type="email" name="email" maxlength="254" value="<?php echo e($email); ?>" autocomplete="email" required>
      </label>
      <label for="requested-role">Section or account type</label>
      <select id="requested-role" name="requested_role" required>
        <option value="">Choose the area you are applying to join</option>
        <?php foreach ($signupRoles as $roleKey => $roleName): ?>
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
    <p>Already registered? <a href="<?php echo e(url('/auth/login.php')); ?>">Sign in</a></p>
    <p><a href="<?php echo e(url('/index.php')); ?>">Back to MediPulse homepage</a></p>
  </main>
  <script>
    (function () {
      var form = document.querySelector('.signup-form');
      var password = document.getElementById('signup-password');
      var confirmation = document.getElementById('confirm-password');
      var passwordFeedback = document.getElementById('password-feedback');
      var confirmationFeedback = document.getElementById('confirm-password-feedback');
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

      function updatePasswordFeedback() {
        var valid = password.value.length >= 12;
        passwordFeedback.textContent = valid
          ? 'Password meets the 12-character minimum.'
          : 'Use at least 12 characters (' + password.value.length + '/12).';
        passwordFeedback.classList.toggle('is-valid', valid);
        passwordFeedback.classList.toggle('is-invalid', password.value.length > 0 && !valid);
        password.setAttribute('aria-invalid', password.value.length > 0 && !valid ? 'true' : 'false');
      }

      function updateConfirmationFeedback() {
        if (confirmation.value === '') {
          confirmationFeedback.textContent = 'Re-enter your password to confirm it.';
          confirmationFeedback.classList.remove('is-valid', 'is-invalid');
          confirmation.setAttribute('aria-invalid', 'false');
          return;
        }

        var matches = confirmation.value === password.value;
        confirmationFeedback.textContent = matches ? 'Passwords match.' : 'Passwords do not match yet.';
        confirmationFeedback.classList.toggle('is-valid', matches);
        confirmationFeedback.classList.toggle('is-invalid', !matches);
        confirmation.setAttribute('aria-invalid', matches ? 'false' : 'true');
      }

      password.addEventListener('input', function () {
        updatePasswordFeedback();
        updateConfirmationFeedback();
      });
      confirmation.addEventListener('input', updateConfirmationFeedback);
      requestedRole.addEventListener('change', updateDoctorSpecialtyField);
      updateDoctorSpecialtyField();

      document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          var input = document.getElementById(button.getAttribute('data-password-toggle'));
          var reveal = input.type === 'password';
          input.type = reveal ? 'text' : 'password';
          button.textContent = reveal ? 'Hide' : 'Show';
          button.setAttribute('aria-label', (reveal ? 'Hide ' : 'Show ') + (input.id === 'signup-password' ? 'password' : 'confirm password'));
          button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
          input.focus();
        });
      });

      form.addEventListener('submit', function (event) {
        updatePasswordFeedback();
        updateConfirmationFeedback();
        if (password.value.length < 12 || password.value !== confirmation.value) {
          event.preventDefault();
          (password.value.length < 12 ? password : confirmation).focus();
        }
      });
    })();
  </script>
</body>
</html>
