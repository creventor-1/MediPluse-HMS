<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireAdmin();

$pdo = getDb();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['save_doctor'])) {
        $name = trim($_POST['name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $wantsLogin = $username !== '' || $password !== '';

        if ($name === '') {
            $message = 'Doctor name is required.';
            $messageType = 'danger';
        } elseif ($wantsLogin && (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen($password) < 8)) {
            $message = 'For a login, enter a username (3-50 letters/numbers) and a password of at least 8 characters.';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $userId = null;

                if ($wantsLogin) {
                    $userId = nextId($pdo, 'tblUsers', 'userID', 'USR');
                    $pdo->prepare('INSERT INTO tblUsers (userID, username, passwordHash, role) VALUES (?, ?, ?, ?)')
                        ->execute([$userId, $username, password_hash($password, PASSWORD_DEFAULT), 'Doctor']);
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
        $doctorId = trim($_POST['delete_doctor']);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT userID FROM tblDoctors WHERE doctorID = ?');
            $stmt->execute([$doctorId]);
            $userId = $stmt->fetchColumn();

            $pdo->prepare('DELETE FROM tblDoctors WHERE doctorID = ?')->execute([$doctorId]);
            if ($userId) {
                $pdo->prepare('DELETE FROM tblUsers WHERE userID = ?')->execute([$userId]);
            }
            $pdo->commit();
            $message = 'Doctor deleted successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
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
        <a href="<?php echo e(url('/dashboard/index.php')); ?>" class="button-link secondary">Back to dashboard</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

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
            Password (optional)
            <input type="password" name="password" autocomplete="new-password">
          </label>
          <div class="form-actions">
            <button type="submit" name="save_doctor">Save Doctor</button>
          </div>
        </form>
      </section>

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
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <span class="emoji">🩺</span>
                    No doctors found.
                  </div>
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
                  <td>
                    <div class="list-actions">
                      <a class="button-link secondary small" href="<?php echo e(url('/doctors/edit.php?id=' . urlencode($doctor['doctorID']))); ?>">Edit</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this doctor?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_doctor" value="<?php echo e($doctor['doctorID']); ?>" class="button-link secondary small">Delete</button>
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
