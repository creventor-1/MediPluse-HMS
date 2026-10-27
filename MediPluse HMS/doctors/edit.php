<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('doctors', 'update');

$pdo = getDb();
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');

if ($id === '') {
    redirect('/doctors/index.php');
}

$stmt = $pdo->prepare(
    'SELECT d.*, u.username FROM tblDoctors d LEFT JOIN tblUsers u ON u.userID = d.userID WHERE d.doctorID = ?'
);
$stmt->execute([$id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    redirect('/doctors/index.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor'])) {
    verify_csrf();
    requireSectionPermission('doctors', 'update');

    $name = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {
        $message = 'Doctor name is required.';
        $messageType = 'danger';
    } else {
        $pdo->prepare('UPDATE tblDoctors SET fullName = ?, specialty = ?, phoneNumber = ? WHERE doctorID = ?')
            ->execute([$name, $specialty ?: null, $phone ?: null, $id]);

        redirect('/doctors/index.php');
    }

    $doctor['fullName'] = $name;
    $doctor['specialty'] = $specialty;
    $doctor['phoneNumber'] = $phone;
}

$pageTitle = 'Edit Doctor';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'doctors'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Staff</p>
          <h2>Edit Doctor</h2>
        </div>
        <a href="<?php echo e(url('/doctors/index.php')); ?>" class="button-link secondary">Back to doctors</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3><?php echo e($doctor['fullName']); ?> (<?php echo e($doctor['doctorID']); ?>)</h3>
        <p class="hint">Update this doctor's details.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo e($doctor['doctorID']); ?>">
          <label>
            Full name
            <input type="text" name="name" maxlength="100" value="<?php echo e($doctor['fullName']); ?>" required>
          </label>
          <label>
            Specialty
            <input type="text" name="specialty" maxlength="50" value="<?php echo e($doctor['specialty']); ?>">
          </label>
          <label>
            Phone number
            <input type="text" name="phone" maxlength="15" value="<?php echo e($doctor['phoneNumber']); ?>">
          </label>
          <?php if ($doctor['userID']): ?>
          <?php endif; ?>
          <div class="form-actions">
            <button type="submit" name="update_doctor">Save Changes</button>
            <a href="<?php echo e(url('/doctors/index.php')); ?>" class="button-link secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
