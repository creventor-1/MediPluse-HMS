<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireRole(['admin', 'receptionist', 'doctor']);

$pdo = getDb();
$roleKey = currentRoleKey();
$canBook = in_array($roleKey, ['admin', 'receptionist'], true);
$myDoctorId = $roleKey === 'doctor' ? currentDoctorId($pdo) : null;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canBook) {
    verify_csrf();

    if (isset($_POST['save_appointment'])) {
        $patientId = trim($_POST['patient_id'] ?? '');
        $doctorId = trim($_POST['doctor_id'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');

        if ($patientId === '' || $doctorId === '' || $date === '' || $time === '') {
            $message = 'Patient, doctor, date and time are required.';
            $messageType = 'danger';
        } elseif ($date < date('Y-m-d')) {
            $message = 'Appointments cannot be booked in the past.';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM tblAppointments
                 WHERE doctorID = ? AND appointmentDate = ? AND appointmentTime = ? AND status = 'Booked'"
            );
            $stmt->execute([$doctorId, $date, $time]);

            if ((int) $stmt->fetchColumn() > 0) {
                $message = 'That doctor already has an appointment at the selected date and time.';
                $messageType = 'danger';
            } else {
                try {
                    $appointmentId = nextId($pdo, 'tblAppointments', 'appointmentID', 'APT');
                    $pdo->prepare(
                        "INSERT INTO tblAppointments (appointmentID, patientID, doctorID, appointmentDate, appointmentTime, status)
                         VALUES (?, ?, ?, ?, ?, 'Booked')"
                    )->execute([$appointmentId, $patientId, $doctorId, $date, $time]);
                    $message = 'Appointment ' . $appointmentId . ' booked successfully.';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'The appointment could not be saved. Check the patient and doctor.';
                    $messageType = 'danger';
                }
            }
        }
    }

    if (isset($_POST['delete_appointment'])) {
        try {
            $pdo->prepare('DELETE FROM tblAppointments WHERE appointmentID = ?')->execute([trim($_POST['delete_appointment'])]);
            $message = 'Appointment deleted successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'This appointment has a bill and cannot be deleted. Mark it as Cancelled instead.';
            $messageType = 'danger';
        }
    }
}

$patientsList = $canBook ? $pdo->query('SELECT patientID, fullName FROM tblPatients ORDER BY fullName')->fetchAll() : [];
$doctorsList = $canBook ? $pdo->query('SELECT doctorID, fullName, specialty FROM tblDoctors ORDER BY fullName')->fetchAll() : [];

$sql = 'SELECT a.*, p.fullName AS patientName, d.fullName AS doctorName
        FROM tblAppointments a
        JOIN tblPatients p ON p.patientID = a.patientID
        JOIN tblDoctors d ON d.doctorID = a.doctorID';
$params = [];

if ($roleKey === 'doctor') {
    $sql .= ' WHERE a.doctorID = ?';
    $params[] = $myDoctorId ?? '';
}

$sql .= ' ORDER BY a.appointmentDate DESC, a.appointmentTime DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'appointments'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Scheduling</p>
          <h2>Appointments</h2>
        </div>
        <a href="<?php echo e(url(roleDashboardPath($roleKey))); ?>" class="button-link secondary">Back</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if ($canBook): ?>
      <section class="form-card">
        <h3>Book Appointment</h3>
        <p class="hint">The patient must already be registered.</p>
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
          <label>
            Doctor
            <select name="doctor_id" required>
              <option value="">Select doctor</option>
              <?php foreach ($doctorsList as $d): ?>
                <option value="<?php echo e($d['doctorID']); ?>"><?php echo e($d['fullName'] . ($d['specialty'] ? ' - ' . $d['specialty'] : '')); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Date
            <input type="date" name="date" min="<?php echo e(date('Y-m-d')); ?>" required>
          </label>
          <label>
            Time
            <input type="time" name="time" required>
          </label>
          <div class="form-actions">
            <button type="submit" name="save_appointment">Book Appointment</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-header">
          <h3><?php echo $roleKey === 'doctor' ? 'My Appointments' : 'Appointment List'; ?></h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Patient</th>
              <th>Doctor</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appointments)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <span class="emoji">📅</span>
                    No appointments found.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($appointments as $a): ?>
                <tr>
                  <td><?php echo e($a['appointmentID']); ?></td>
                  <td class="cell-primary"><?php echo e($a['patientName']); ?></td>
                  <td><?php echo e($a['doctorName']); ?></td>
                  <td><?php echo e($a['appointmentDate']); ?></td>
                  <td><?php echo e(substr($a['appointmentTime'], 0, 5)); ?></td>
                  <td><span class="status <?php echo e(strtolower($a['status'])); ?>"><?php echo e($a['status']); ?></span></td>
                  <td>
                    <div class="list-actions">
                      <a class="button-link secondary small" href="<?php echo e(url('/appointments/edit.php?id=' . urlencode($a['appointmentID']))); ?>">Edit</a>
                      <?php if ($canBook): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this appointment?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_appointment" value="<?php echo e($a['appointmentID']); ?>" class="button-link secondary small">Delete</button>
                      </form>
                      <?php endif; ?>
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
