<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('patients', 'update');

$pdo = getDb();
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');

if ($id === '') {
    redirect('/patients/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM tblPatients WHERE patientID = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    redirect('/patients/index.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');

    if ($name !== '' && $dob !== '' && in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $pdo->prepare(
            'UPDATE tblPatients SET fullName = ?, gender = ?, dateOfBirth = ?, phoneNumber = ?, address = ? WHERE patientID = ?'
        )->execute([$name, $gender, $dob, $phone ?: null, $address ?: null, $id]);
        redirect('/patients/index.php');
    }

    $message = 'Full name, gender and date of birth are required.';
    $messageType = 'danger';
    $patient['fullName'] = $name;
    $patient['phoneNumber'] = $phone;
    $patient['address'] = $address;
    $patient['gender'] = $gender;
    $patient['dateOfBirth'] = $dob;
}

$pageTitle = 'Edit Patient';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'patients'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Records</p>
          <h2>Edit Patient</h2>
        </div>
        <a href="<?php echo e(url('/patients/index.php')); ?>" class="button-link secondary">Back to patients</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3><?php echo e($patient['fullName']); ?> (<?php echo e($patient['patientID']); ?>)</h3>
        <p class="hint">Update this patient's details.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo e($patient['patientID']); ?>">
          <label>
            Full name
            <input type="text" name="name" maxlength="100" value="<?php echo e($patient['fullName']); ?>" required>
          </label>
          <label>
            Phone number
            <input type="text" name="phone" maxlength="15" value="<?php echo e($patient['phoneNumber']); ?>">
          </label>
          <label>
            Gender
            <select name="gender">
              <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                <option value="<?php echo e($g); ?>" <?php echo $patient['gender'] === $g ? 'selected' : ''; ?>><?php echo e($g); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Date of birth
            <input type="date" name="dob" max="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($patient['dateOfBirth']); ?>" required>
          </label>
          <label style="grid-column: 1 / -1;">
            Address
            <textarea name="address" rows="3" maxlength="150"><?php echo e($patient['address']); ?></textarea>
          </label>
          <div class="form-actions">
            <button type="submit" name="update_patient">Save Changes</button>
            <a href="<?php echo e(url('/patients/index.php')); ?>" class="button-link secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
