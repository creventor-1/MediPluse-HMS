<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireRole(['admin', 'receptionist', 'doctor']);

$pdo = getDb();
$roleKey = currentRoleKey();
$canEdit = in_array($roleKey, ['admin', 'receptionist'], true);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verify_csrf();

    if (isset($_POST['save_patient'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $dob = trim($_POST['dob'] ?? '');

        if ($name === '' || $dob === '' || !in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $message = 'Full name, gender and date of birth are required.';
            $messageType = 'danger';
        } else {
            $patientId = nextId($pdo, 'tblPatients', 'patientID', 'PAT');
            $pdo->prepare(
                'INSERT INTO tblPatients (patientID, fullName, gender, dateOfBirth, phoneNumber, address, registrationDate)
                 VALUES (?, ?, ?, ?, ?, ?, CURDATE())'
            )->execute([$patientId, $name, $gender, $dob, $phone ?: null, $address ?: null]);
            $message = 'Patient registered with ID ' . $patientId . '.';
            $messageType = 'success';
        }
    }

    if (isset($_POST['delete_patient'])) {
        try {
            $pdo->prepare('DELETE FROM tblPatients WHERE patientID = ?')->execute([trim($_POST['delete_patient'])]);
            $message = 'Patient deleted successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'This patient has appointments, lab results or bills and cannot be deleted.';
            $messageType = 'danger';
        }
    }
}

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM tblPatients
         WHERE fullName LIKE :q1 OR phoneNumber LIKE :q2 OR patientID LIKE :q3
         ORDER BY registrationDate DESC, patientID DESC'
    );
    $like = '%' . $search . '%';
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $patients = $stmt->fetchAll();
} else {
    $patients = $pdo->query('SELECT * FROM tblPatients ORDER BY registrationDate DESC, patientID DESC')->fetchAll();
}

$pageTitle = 'Patients';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'patients'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Records</p>
          <h2>Patients</h2>
        </div>
        <a href="<?php echo e(url(roleDashboardPath($roleKey))); ?>" class="button-link secondary">Back</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if ($canEdit): ?>
      <section class="form-card">
        <h3>Register Patient</h3>
        <p class="hint">A unique Patient ID is generated automatically.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <label>
            Full name
            <input type="text" name="name" maxlength="100" placeholder="John Doe" required>
          </label>
          <label>
            Phone number
            <input type="text" name="phone" maxlength="15" placeholder="08000000000">
          </label>
          <label>
            Gender
            <select name="gender" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </label>
          <label>
            Date of birth
            <input type="date" name="dob" max="<?php echo e(date('Y-m-d')); ?>" required>
          </label>
          <label style="grid-column: 1 / -1;">
            Address
            <textarea name="address" rows="3" maxlength="150" placeholder="Residential address"></textarea>
          </label>
          <div class="form-actions">
            <button type="submit" name="save_patient">Save Patient</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-header">
          <h3>Patient List</h3>
        </div>

        <form method="GET" class="search-bar">
          <input type="text" name="q" placeholder="Search by ID, name or phone…" value="<?php echo e($search); ?>">
          <button type="submit" class="button-link small">Search</button>
          <?php if ($search !== ''): ?>
            <a class="button-link secondary small" href="<?php echo e(url('/patients/index.php')); ?>">Clear</a>
          <?php endif; ?>
        </form>

        <table>
          <thead>
            <tr>
              <th>Patient ID</th>
              <th>Name</th>
              <th>Gender</th>
              <th>Date of Birth</th>
              <th>Phone</th>
              <th>Registered</th>
              <?php if ($canEdit): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($patients)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <span class="emoji">🧑‍⚕️</span>
                    No patients found.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($patients as $patient): ?>
                <tr>
                  <td><?php echo e($patient['patientID']); ?></td>
                  <td class="cell-primary"><?php echo e($patient['fullName']); ?></td>
                  <td><?php echo e($patient['gender']); ?></td>
                  <td><?php echo e($patient['dateOfBirth']); ?></td>
                  <td><?php echo e($patient['phoneNumber'] ?: 'N/A'); ?></td>
                  <td><?php echo e($patient['registrationDate']); ?></td>
                  <?php if ($canEdit): ?>
                  <td>
                    <div class="list-actions">
                      <a class="button-link secondary small" href="<?php echo e(url('/patients/edit.php?id=' . urlencode($patient['patientID']))); ?>">Edit</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this patient?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_patient" value="<?php echo e($patient['patientID']); ?>" class="button-link secondary small">Delete</button>
                      </form>
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
