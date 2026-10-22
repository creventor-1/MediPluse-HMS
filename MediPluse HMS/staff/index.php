<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireAdmin();

$pdo = getDb();
$roles = ['Administrator', 'Receptionist', 'Lab Technician', 'Billing Officer'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['save_staff'])) {
        $name = trim($_POST['name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($name === '' || !in_array($role, $roles, true)) {
            $message = 'Full name and role are required.';
            $messageType = 'danger';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $message = 'Username must be 3-50 letters, numbers, dots, dashes or underscores.';
            $messageType = 'danger';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters.';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $userId = nextId($pdo, 'tblUsers', 'userID', 'USR');
                $pdo->prepare('INSERT INTO tblUsers (userID, username, passwordHash, role) VALUES (?, ?, ?, ?)')
                    ->execute([$userId, $username, password_hash($password, PASSWORD_DEFAULT), $role]);

                $staffId = nextId($pdo, 'tblStaff', 'staffID', 'STF');
                $pdo->prepare('INSERT INTO tblStaff (staffID, fullName, designation, userID) VALUES (?, ?, ?, ?)')
                    ->execute([$staffId, $name, $designation ?: null, $userId]);
                $pdo->commit();

                $message = 'Staff account created (' . $staffId . ').';
                $messageType = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = $e->getCode() === '23000' ? 'That username is already taken.' : 'The account could not be saved.';
                $messageType = 'danger';
            }
        }
    }

    if (isset($_POST['delete_staff'])) {
        $staffId = trim($_POST['delete_staff']);
        $stmt = $pdo->prepare('SELECT userID FROM tblStaff WHERE staffID = ?');
        $stmt->execute([$staffId]);
        $userId = $stmt->fetchColumn();

        if ($userId && $userId === (currentUser()['id'] ?? null)) {
            $message = 'You cannot delete your own account.';
            $messageType = 'danger';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM tblStaff WHERE staffID = ?')->execute([$staffId]);
            if ($userId) {
                $pdo->prepare('DELETE FROM tblUsers WHERE userID = ?')->execute([$userId]);
            }
            $pdo->commit();
            $message = 'Staff account deleted.';
            $messageType = 'success';
        }
    }
}

$staffList = $pdo->query(
    'SELECT s.*, u.username, u.role
     FROM tblStaff s
     LEFT JOIN tblUsers u ON u.userID = s.userID
     ORDER BY s.staffID'
)->fetchAll();

$pageTitle = 'Staff';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'staff'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Administration</p>
          <h2>Staff Accounts</h2>
        </div>
        <a href="<?php echo e(url('/dashboard/index.php')); ?>" class="button-link secondary">Back to dashboard</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3>Create Staff Account</h3>
        <p class="hint">Doctor accounts are created from the Doctors page.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <label>
            Full name
            <input type="text" name="name" maxlength="100" required>
          </label>
          <label>
            Designation
            <input type="text" name="designation" maxlength="50" placeholder="e.g. Front Desk Officer">
          </label>
          <label>
            Role
            <select name="role" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo e($r); ?>"><?php echo e($r); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Username
            <input type="text" name="username" maxlength="50" autocomplete="off" required>
          </label>
          <label>
            Password
            <input type="password" name="password" autocomplete="new-password" required>
          </label>
          <div class="form-actions">
            <button type="submit" name="save_staff">Create Account</button>
          </div>
        </form>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h3>Staff List</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Staff ID</th>
              <th>Name</th>
              <th>Designation</th>
              <th>Username</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($staffList)): ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <span class="emoji">👥</span>
                    No staff accounts found.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($staffList as $s): ?>
                <tr>
                  <td><?php echo e($s['staffID']); ?></td>
                  <td class="cell-primary"><?php echo e($s['fullName']); ?></td>
                  <td><?php echo e($s['designation'] ?: 'N/A'); ?></td>
                  <td><?php echo e($s['username'] ?: 'N/A'); ?></td>
                  <td><?php echo e($s['role'] ?: 'N/A'); ?></td>
                  <td>
                    <div class="list-actions">
                      <a class="button-link secondary small" href="<?php echo e(url('/staff/edit.php?id=' . urlencode($s['staffID']))); ?>">Edit</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this staff account?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_staff" value="<?php echo e($s['staffID']); ?>" class="button-link secondary small">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
