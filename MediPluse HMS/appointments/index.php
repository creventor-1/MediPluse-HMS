<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('appointments', 'view');

$pdo = getDb();
$roleKey = currentRoleKey();
$myDoctorId = $roleKey === 'doctor' ? currentDoctorId($pdo) : null;
$canBook = can('appointments', 'create') && ($roleKey !== 'doctor' || $myDoctorId !== null);
$canUpdate = can('appointments', 'update');
$canDelete = can('appointments', 'delete');
$canViewAssignedPatientDetails = $roleKey === 'doctor' && $myDoctorId !== null && can('patients', 'view');
$message = '';
$messageType = '';
$doctorProfileMissing = $roleKey === 'doctor' && $myDoctorId === null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['save_appointment'])) {
        requireSectionPermission('appointments', 'create');
        $patientId = trim($_POST['patient_id'] ?? '');
        $doctorId = trim($_POST['doctor_id'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');

        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateValid = $dateValue !== false && $dateValue->format('Y-m-d') === $date;
        $timeValid = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;

        if ($patientId === '' || $doctorId === '' || !$dateValid || !$timeValid) {
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
                    $bookedByDoctorId = $roleKey === 'doctor' ? $myDoctorId : null;
                    $pdo->prepare(
                        "INSERT INTO tblAppointments
                            (appointmentID, patientID, doctorID, appointmentDate, appointmentTime, status, bookedByDoctorID)
                         VALUES (?, ?, ?, ?, ?, 'Booked', ?)"
                    )->execute([$appointmentId, $patientId, $doctorId, $date, $time, $bookedByDoctorId]);
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
        requireSectionPermission('appointments', 'delete');
        try {
            $appointmentId = trim($_POST['delete_appointment']);
            if ($roleKey === 'doctor') {
                $statement = $pdo->prepare(
                    'DELETE FROM tblAppointments WHERE appointmentID = ? AND doctorID = ?'
                );
                $statement->execute([$appointmentId, $myDoctorId ?? '']);
            } else {
                $statement = $pdo->prepare('DELETE FROM tblAppointments WHERE appointmentID = ?');
                $statement->execute([$appointmentId]);
            }

            if ($statement->rowCount() === 1) {
                $message = 'Appointment deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'The appointment was not found or you do not have permission to delete it.';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'This appointment has a bill and cannot be deleted. Mark it as Cancelled instead.';
            $messageType = 'danger';
        }
    }
}

$patientsList = $canBook ? $pdo->query('SELECT patientID, fullName FROM tblPatients ORDER BY fullName')->fetchAll() : [];
$doctorsList = $canBook
    ? $pdo->query('SELECT doctorID, fullName, specialty FROM tblDoctors ORDER BY fullName')->fetchAll()
    : [];

$patientDetailColumns = $canViewAssignedPatientDetails
    ? ', p.patientID AS patientRecordID, p.gender AS patientGender,
       p.dateOfBirth AS patientDateOfBirth, p.phoneNumber AS patientPhone, p.address AS patientAddress'
    : '';
$sql = 'SELECT a.*, p.fullName AS patientName' . $patientDetailColumns . ', d.fullName AS doctorName,
               attending.fullName AS attendedByName, bookingDoctor.fullName AS bookedByDoctorName
        FROM tblAppointments a
        JOIN tblPatients p ON p.patientID = a.patientID
        JOIN tblDoctors d ON d.doctorID = a.doctorID
        LEFT JOIN tblDoctors attending ON attending.doctorID = a.attendedByDoctorID
        LEFT JOIN tblDoctors bookingDoctor ON bookingDoctor.doctorID = a.bookedByDoctorID';
$params = [];

if ($roleKey === 'doctor') {
    $sql .= ' WHERE (a.doctorID = ? OR a.bookedByDoctorID = ?)';
    $params[] = $myDoctorId ?? '';
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
        <a href="<?php echo e(url(roleDashboardPath($roleKey))); ?>" class="button-link secondary"><?php echo icon('chevron-left', '', 16); ?> Back</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>
      <?php if ($doctorProfileMissing): ?>
        <div class="alert info" role="status">
          This Doctor login is not linked to a doctor profile, so appointments cannot be booked under your name yet.
          Ask a user manager to link your login to the correct profile on the Doctors page, then reload this page.
        </div>
      <?php endif; ?>

      <?php if ($canBook): ?>
      <section class="form-card">
        <h3>Book Appointment</h3>
        <p class="hint"><?php echo $roleKey === 'doctor' ? 'Select the doctor you are referring the patient to. Your name will be recorded under Booked by, and the appointment will appear in both doctors’ lists.' : 'The patient must already be registered.'; ?></p>
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
            <button type="submit" name="save_appointment"><?php echo icon('calendar', '', 16); ?> Book Appointment</button>
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
              <?php if ($canViewAssignedPatientDetails): ?><th>Patient details</th><?php endif; ?>
              <th>Booked by</th>
              <th>Booked with</th>
              <th>Doctor who attended</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <?php if ($canUpdate || $canDelete): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appointments)): ?>
              <tr>
                <td colspan="<?php echo 8 + (int) $canViewAssignedPatientDetails + (int) ($canUpdate || $canDelete); ?>">
                  <?php echo renderEmptyState('calendar', 'No appointments found', $canBook ? 'Book an appointment above to add a record.' : 'No appointments found.'); ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($appointments as $a): ?>
                <tr>
                  <td><?php echo e($a['appointmentID']); ?></td>
                  <td class="cell-primary"><?php echo e($a['patientName']); ?></td>
                  <?php if ($canViewAssignedPatientDetails): ?>
                  <td>
                    <?php if ($a['doctorID'] === $myDoctorId): ?>
                    <details>
                      <summary>View patient details</summary>
                      <dl>
                        <dt>Patient ID</dt><dd><?php echo e($a['patientRecordID']); ?></dd>
                        <dt>Gender</dt><dd><?php echo e($a['patientGender']); ?></dd>
                        <dt>Date of birth</dt><dd><?php echo e($a['patientDateOfBirth']); ?></dd>
                        <dt>Phone</dt><dd><?php echo e($a['patientPhone'] ?: 'Not recorded'); ?></dd>
                        <dt>Address</dt><dd><?php echo e($a['patientAddress'] ?: 'Not recorded'); ?></dd>
                      </dl>
                    </details>
                    <?php else: ?>
                    <span class="hint">Assigned doctor only</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  <td><?php echo e($a['bookedByDoctorName'] ?: 'Not recorded'); ?></td>
                  <td>
                    <?php echo e($a['doctorName']); ?>
                    <?php if ($roleKey === 'doctor' && $myDoctorId !== null && $a['doctorID'] !== $myDoctorId): ?>
                      <span class="hint">(referred)</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo e($a['attendedByName'] ?: 'Not recorded'); ?></td>
                  <td><?php echo e($a['appointmentDate']); ?></td>
                  <td><?php echo e(substr($a['appointmentTime'], 0, 5)); ?></td>
                  <td><?php echo statusBadge($a['status']); ?></td>
                  <?php if ($canUpdate || $canDelete): ?>
                  <td>
                    <div class="list-actions">
                      <?php $isAssignedDoctor = $roleKey !== 'doctor' || $a['doctorID'] === $myDoctorId; ?>
                      <?php if ($isAssignedDoctor && $canUpdate): ?>
                      <a class="button-link secondary small" href="<?php echo e(url('/appointments/edit.php?id=' . urlencode($a['appointmentID']))); ?>"><?php echo icon('edit', '', 14); ?> Edit</a>
                      <?php endif; ?>
                      <?php if ($isAssignedDoctor && $canDelete): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this appointment?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_appointment" value="<?php echo e($a['appointmentID']); ?>" class="button-link secondary small"><?php echo icon('trash-2', '', 14); ?> Delete</button>
                      </form>
                      <?php endif; ?>
                      <?php if (!$isAssignedDoctor): ?><span class="hint">View only</span><?php endif; ?>
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
