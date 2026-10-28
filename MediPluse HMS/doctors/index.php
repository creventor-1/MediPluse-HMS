<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

$pdo = getDb();
$canManageUsers = can('system', 'manage_users');
if (!can('doctors', 'view') && !$canManageUsers) {
    requireSectionPermission('doctors', 'view');
}
$canCreate = can('doctors', 'create');
$canUpdate = can('doctors', 'update');
$canDelete = can('doctors', 'delete');
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['link_doctor_account'])) {
        requireUserManagementPermission();
        $doctorId = trim($_POST['doctor_id'] ?? '');
        $userId = trim($_POST['user_id'] ?? '');

        try {
            $pdo->beginTransaction();
            $doctorStmt = $pdo->prepare('SELECT doctorID, userID FROM tblDoctors WHERE doctorID = ? FOR UPDATE');
            $doctorStmt->execute([$doctorId]);
            $doctor = $doctorStmt->fetch();
            $userStmt = $pdo->prepare('SELECT userID, role, accountStatus FROM tblUsers WHERE userID = ? FOR UPDATE');
            $userStmt->execute([$userId]);
            $account = $userStmt->fetch();

            if (!$doctor || !$account) {
                throw new RuntimeException('Select an existing doctor profile and doctor account.');
            }
            if ($doctor['userID'] !== null) {
                throw new RuntimeException('This doctor profile is already linked to an account.');
            }
            if (roleKey((string) $account['role']) !== 'doctor' || $account['accountStatus'] !== 'active') {
                throw new RuntimeException('Only an active Doctor account can be linked.');
            }

            $existingLink = $pdo->prepare('SELECT doctorID FROM tblDoctors WHERE userID = ? FOR UPDATE');
            $existingLink->execute([$userId]);
            if ($existingLink->fetchColumn()) {
                throw new RuntimeException('This Doctor account is already linked to another profile.');
            }

            $pdo->prepare('UPDATE tblDoctors SET userID = ? WHERE doctorID = ? AND userID IS NULL')
                ->execute([$userId, $doctorId]);
            recordAuditEvent($pdo, 'system', 'doctor_account_linked', $doctorId . ':' . $userId);
            $pdo->commit();
            $message = 'Doctor login linked to the profile. Appointments assigned to this doctor will now appear in their list.';
            $messageType = 'success';
        } catch (RuntimeException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $error->getMessage();
            $messageType = 'danger';
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Doctor account linking failed: ' . $error->getMessage());
            $message = 'The doctor account could not be linked. Refresh the page and try again.';
            $messageType = 'danger';
        }
    }

    if (isset($_POST['save_doctor'])) {
        requireSectionPermission('doctors', 'create');
        $name = trim($_POST['name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $wantsLogin = $username !== '' || $password !== '';

        if ($name === '') {
            $message = 'Doctor name is required.';
            $messageType = 'danger';
        } elseif ($wantsLogin && (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12)) {
            $message = 'For a login, enter a valid username, email, and a password of at least 12 characters.';
            $messageType = 'danger';
        } else {
            if ($wantsLogin) {
                requireUserManagementPermission();
            }
            try {
                $pdo->beginTransaction();
                $userId = null;

                if ($wantsLogin) {
                    $userId = nextId($pdo, 'tblUsers', 'userID', 'USR');
                    $pdo->prepare(
                        'INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$userId, $username, $email, $name, password_hash($password, PASSWORD_DEFAULT), 'Doctor', 'active']);
                }

                $doctorId = nextId($pdo, 'tblDoctors', 'doctorID', 'DOC');
                $pdo->prepare('INSERT INTO tblDoctors (doctorID, fullName, specialty, phoneNumber, userID) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$doctorId, $name, $specialty ?: null, $phone ?: null, $userId]);

                $pdo->commit();
                $message = 'Doctor added with ID ' . $doctorId . '.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = $e->getCode() === '23000' ? 'That username is already taken.' : 'The doctor could not be saved.';
                $messageType = 'danger';
            }
        }
    }

    if (isset($_POST['delete_doctor'])) {
        requireSectionPermission('doctors', 'delete');
        $doctorId = trim($_POST['delete_doctor']);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT userID FROM tblDoctors WHERE doctorID = ?');
            $stmt->execute([$doctorId]);
            $userId = $stmt->fetchColumn();

            if ($userId && !can('system', 'manage_users')) {
                $pdo->rollBack();
                $message = 'This doctor is linked to a user account. User-management permission is required to delete it.';
                $messageType = 'danger';
            } else {
                $pdo->prepare('DELETE FROM tblDoctors WHERE doctorID = ?')->execute([$doctorId]);
                if ($userId) {
                    $pdo->prepare('DELETE FROM tblUsers WHERE userID = ?')->execute([$userId]);
                }
                $pdo->commit();
                $message = 'Doctor deleted successfully.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'This doctor has appointments or lab requests and cannot be deleted.';
            $messageType = 'danger';
        }
    }
}

$doctors = $pdo->query(
    'SELECT d.*, u.username
     FROM tblDoctors d
     LEFT JOIN tblUsers u ON u.userID = d.userID
     ORDER BY d.doctorID'
)->fetchAll();
$availableDoctorAccounts = $canManageUsers
    ? $pdo->query(
        "SELECT u.userID, u.displayName, u.username
         FROM tblUsers u
         WHERE LOWER(REPLACE(u.role, ' ', '_')) = 'doctor'
           AND u.accountStatus = 'active'
           AND NOT EXISTS (SELECT 1 FROM tblDoctors d WHERE d.userID = u.userID)
         ORDER BY COALESCE(u.displayName, u.username)"
    )->fetchAll()
    : [];

$pageTitle = 'Doctors';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'doctors'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Staff</p>
          <h2>Doctors</h2>
        </div>
        <a href="<?php echo e(url('/dashboard/index.php')); ?>" class="button-link secondary"><?php echo icon('chevron-left', '', 16); ?> Back to dashboard</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if ($canCreate): ?>
      <section class="form-card">
        <h3>Add Doctor</h3>
        <p class="hint">Fill in a username and password only if this doctor should be able to log in.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <label>
            Full name
            <input type="text" name="name" maxlength="100" placeholder="Dr. Jane Doe" required>
          </label>
          <label>
            Specialty
            <input type="text" name="specialty" maxlength="50" placeholder="e.g. Pediatrics">
          </label>
          <label>
            Phone number
            <input type="text" name="phone" maxlength="15">
          </label>
          <label>
            Username (optional)
            <input type="text" name="username" maxlength="50" autocomplete="off">
          </label>
          <label>
            Email (required for a login)
            <input type="email" name="email" maxlength="254" autocomplete="email">
          </label>
          <label>
            Password (optional)
            <input type="password" name="password" autocomplete="new-password">
          </label>
          <div class="form-actions">
            <button type="submit" name="save_doctor"><?php echo icon('user-plus', '', 16); ?> Save Doctor</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-header">
          <h3>Doctor List</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Doctor ID</th>
              <th>Name</th>
              <th>Specialty</th>
              <th>Phone</th>
              <th>Login</th>
              <?php if ($canUpdate || $canDelete || $canManageUsers): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="<?php echo $canUpdate || $canDelete || $canManageUsers ? 6 : 5; ?>">
                  <?php echo renderEmptyState('stethoscope', 'No doctors found', 'Add a doctor profile above to build your medical directory.'); ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($doctors as $doctor): ?>
                <tr>
                  <td><?php echo e($doctor['doctorID']); ?></td>
                  <td class="cell-primary"><?php echo e($doctor['fullName']); ?></td>
                  <td><?php echo e($doctor['specialty'] ?: 'N/A'); ?></td>
                  <td><?php echo e($doctor['phoneNumber'] ?: 'N/A'); ?></td>
                  <td><?php echo e($doctor['username'] ?: 'No login'); ?></td>
                  <?php if ($canUpdate || $canDelete || $canManageUsers): ?>
                  <td>
                    <div class="list-actions">
                      <?php if ($canManageUsers && empty($doctor['userID']) && $availableDoctorAccounts !== []): ?>
                      <form method="POST" class="form-grid">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="doctor_id" value="<?php echo e($doctor['doctorID']); ?>">
                        <label>
                          Link login
                          <select name="user_id" required>
                            <option value="">Select Doctor account</option>
                            <?php foreach ($availableDoctorAccounts as $account): ?>
                              <option value="<?php echo e($account['userID']); ?>">
                                <?php echo e(($account['displayName'] ?: $account['username']) . ' (' . $account['username'] . ')'); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <button type="submit" name="link_doctor_account" value="1">Link account</button>
                      </form>
                      <?php endif; ?>
                      <?php if ($canUpdate): ?>
                      <a class="button-link secondary small" href="<?php echo e(url('/doctors/edit.php?id=' . urlencode($doctor['doctorID']))); ?>"><?php echo icon('edit', '', 14); ?> Edit</a>
                      <?php endif; ?>
                      <?php if ($canDelete): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this doctor?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_doctor" value="<?php echo e($doctor['doctorID']); ?>" class="button-link secondary small"><?php echo icon('trash-2', '', 14); ?> Delete</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                  <?php endif; ?>
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
