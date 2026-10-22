<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireAdmin();

$pdo = getDb();
$roles = ['Administrator', 'Receptionist', 'Lab Technician', 'Billing Officer'];
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');

if ($id === '') {
    redirect('/staff/index.php');
}

$stmt = $pdo->prepare(
    'SELECT s.*, u.username, u.role FROM tblStaff s LEFT JOIN tblUsers u ON u.userID = s.userID WHERE s.staffID = ?'
);
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    redirect('/staff/index.php');
}

$isSelf = $staff['userID'] && $staff['userID'] === (currentUser()['id'] ?? null);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $role = $isSelf ? $staff['role'] : trim($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || !in_array($role, $roles, true)) {
        $message = 'Full name and a valid role are required.';
        $messageType = 'danger';
    } elseif ($password !== '' && strlen($password) < 8) {
        $message = 'A new password must be at least 8 characters.';
        $messageType = 'danger';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE tblStaff SET fullName = ?, designation = ? WHERE staffID = ?')
            ->execute([$name, $designation ?: null, $id]);

        if ($staff['userID']) {
            $pdo->prepare('UPDATE tblUsers SET role = ? WHERE userID = ?')->execute([$role, $staff['userID']]);
            if ($password !== '') {
                $pdo->prepare('UPDATE tblUsers SET passwordHash = ? WHERE userID = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $staff['userID']]);
            }
        }

        $pdo->commit();
        redirect('/staff/index.php');
    }

    $staff['fullName'] = $name;
    $staff['designation'] = $designation;
    $staff['role'] = $role;
}

$pageTitle = 'Edit Staff';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'staff'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Administration</p>
          <h2>Edit Staff Account</h2>
        </div>
        <a href="<?php echo e(url('/staff/index.php')); ?>" class="button-link secondary">Back to staff</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3><?php echo e($staff['fullName']); ?> (<?php echo e($staff['staffID']); ?>)</h3>
        <p class="hint">Username: <?php echo e($staff['username'] ?: 'N/A'); ?></p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo e($staff['staffID']); ?>">
          <label>
            Full name
            <input type="text" name="name" maxlength="100" value="<?php echo e($staff['fullName']); ?>" required>
          </label>
          <label>
            Designation
            <input type="text" name="designation" maxlength="50" value="<?php echo e($staff['designation']); ?>">
          </label>
          <label>
            Role
            <select name="role" <?php echo $isSelf ? 'disabled' : ''; ?>>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo e($r); ?>" <?php echo $staff['role'] === $r ? 'selected' : ''; ?>><?php echo e($r); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Reset password
            <input type="password" name="password" placeholder="Leave blank to keep current" autocomplete="new-password">
          </label>
          <div class="form-actions">
            <button type="submit" name="update_staff">Save Changes</button>
            <a href="<?php echo e(url('/staff/index.php')); ?>" class="button-link secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
