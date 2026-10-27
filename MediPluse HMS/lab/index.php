<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('lab', 'view');

$pdo = getDb();
$roleKey = currentRoleKey();
$canRequest = can('lab', 'create');
$canEnter = can('lab', 'update');
$canDelete = can('lab', 'delete');
$myDoctorId = $roleKey === 'doctor' ? currentDoctorId($pdo) : null;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['request_test'])) {
        requireSectionPermission('lab', 'create');
        $patientId = trim($_POST['patient_id'] ?? '');
        $doctorId = $roleKey === 'doctor' ? (string) $myDoctorId : trim($_POST['doctor_id'] ?? '');
        $testType = trim($_POST['test_type'] ?? '');

        if ($patientId === '' || $doctorId === '' || $testType === '') {
            $message = 'Patient, requesting doctor and test type are required.';
            $messageType = 'danger';
        } else {
            try {
                $labId = nextId($pdo, 'tblLabResults', 'labResultID', 'LAB');
                $pdo->prepare(
                    'INSERT INTO tblLabResults (labResultID, patientID, requestedBy, testType) VALUES (?, ?, ?, ?)'
                )->execute([$labId, $patientId, $doctorId, $testType]);
                $message = 'Laboratory test ' . $labId . ' requested.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'The request could not be saved. Check the patient and doctor.';
                $messageType = 'danger';
            }
        }
    }

    if (isset($_POST['delete_lab'])) {
        requireSectionPermission('lab', 'delete');
        if ($roleKey === 'doctor') {
            $statement = $pdo->prepare(
                'DELETE FROM tblLabResults WHERE labResultID = ? AND requestedBy = ?'
            );
            $statement->execute([trim($_POST['delete_lab']), $myDoctorId ?? '']);
        } else {
            $statement = $pdo->prepare('DELETE FROM tblLabResults WHERE labResultID = ?');
            $statement->execute([trim($_POST['delete_lab'])]);
        }

        if ($statement->rowCount() === 1) {
            $message = 'Laboratory record deleted.';
            $messageType = 'success';
        } else {
            $message = 'The laboratory record was not found or you do not have permission to delete it.';
            $messageType = 'danger';
        }
    }
}

$patientsList = $canRequest ? $pdo->query('SELECT patientID, fullName FROM tblPatients ORDER BY fullName')->fetchAll() : [];
$doctorsList = $canRequest && $myDoctorId === null
    ? $pdo->query('SELECT doctorID, fullName FROM tblDoctors ORDER BY fullName')->fetchAll()
    : [];

$sql = 'SELECT l.*, p.fullName AS patientName, d.fullName AS doctorName
        FROM tblLabResults l
        JOIN tblPatients p ON p.patientID = l.patientID
        JOIN tblDoctors d ON d.doctorID = l.requestedBy';
$params = [];

if ($roleKey === 'doctor') {
    $sql .= ' WHERE l.requestedBy = ?';
    $params[] = $myDoctorId ?? '';
}

$sql .= ' ORDER BY l.labResultID DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$pageTitle = 'Lab Results';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'lab'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Laboratory</p>
          <h2>Lab Results</h2>
        </div>
        <a href="<?php echo e(url(roleDashboardPath($roleKey))); ?>" class="button-link secondary">Back</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if ($canRequest): ?>
      <section class="form-card">
        <h3>Request Laboratory Test</h3>
        <p class="hint">The laboratory technician will enter the result once the test is done.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <label>
            Patient
            <select name="patient_id" required>
              <option value="">Select patient</option>
              <?php foreach ($patientsList as $p): ?>
                <option value="<?php echo e($p['patientID']); ?>"><?php echo e($p['fullName'] . ' (' . $p['patientID'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if ($myDoctorId === null): ?>
          <label>
            Requesting doctor
            <select name="doctor_id" required>
              <option value="">Select doctor</option>
              <?php foreach ($doctorsList as $d): ?>
                <option value="<?php echo e($d['doctorID']); ?>"><?php echo e($d['fullName']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>
          <label>
            Test type
            <input type="text" name="test_type" maxlength="100" placeholder="e.g. Full blood count" required>
          </label>
          <div class="form-actions">
            <button type="submit" name="request_test">Request Test</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-header">
          <h3>Laboratory Records</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Patient</th>
              <th>Test</th>
              <th>Requested by</th>
              <th>Result</th>
              <th>Date recorded</th>
              <?php if ($canEnter || $canDelete): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($results)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <span class="emoji">🧪</span>
                    No laboratory records found.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td><?php echo e($r['labResultID']); ?></td>
                  <td class="cell-primary"><?php echo e($r['patientName']); ?></td>
                  <td><?php echo e($r['testType']); ?></td>
                  <td><?php echo e($r['doctorName']); ?></td>
                  <td><?php echo $r['resultValue'] ? e($r['resultValue']) : '<span class="status pending">Pending</span>'; ?></td>
                  <td><?php echo e($r['dateRecorded'] ?: 'N/A'); ?></td>
                  <?php if ($canEnter || $canDelete): ?>
                  <td>
                    <div class="list-actions">
                      <?php if ($canEnter): ?>
                      <a class="button-link secondary small" href="<?php echo e(url('/lab/edit.php?id=' . urlencode($r['labResultID']))); ?>"><?php echo $r['resultValue'] ? 'Edit result' : 'Enter result'; ?></a>
                      <?php endif; ?>
                      <?php if ($canDelete): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_lab" value="<?php echo e($r['labResultID']); ?>" class="button-link secondary small">Delete</button>
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
