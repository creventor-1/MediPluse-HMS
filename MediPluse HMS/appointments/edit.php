<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('appointments', 'update');

$pdo = getDb();
$roleKey = currentRoleKey();
$isDoctor = $roleKey === 'doctor';
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');

if ($id === '') {
    redirect('/appointments/index.php');
}

$appointmentSql = 'SELECT a.*, p.fullName AS patientName FROM tblAppointments a
                   JOIN tblPatients p ON p.patientID = a.patientID WHERE a.appointmentID = ?';
$appointmentParameters = [$id];
if ($isDoctor) {
    $appointmentSql .= ' AND a.doctorID = ?';
    $appointmentParameters[] = currentDoctorId($pdo) ?? '';
}
$stmt = $pdo->prepare($appointmentSql);
$stmt->execute($appointmentParameters);
$appointment = $stmt->fetch();

// A doctor may only update appointments assigned to their profile.
if (!$appointment || ($isDoctor && $appointment['doctorID'] !== currentDoctorId($pdo))) {
    redirect('/appointments/index.php');
}

$statuses = ['Booked', 'Completed', 'Cancelled'];
$doctorsList = $pdo->query('SELECT doctorID, fullName, specialty FROM tblDoctors ORDER BY fullName')->fetchAll();
$myDoctorId = $isDoctor ? currentDoctorId($pdo) : null;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    verify_csrf();

    $status = trim($_POST['status'] ?? '');
    $attendedByDoctorId = trim($_POST['attended_by_doctor_id'] ?? '');
    $attendedByDoctorId = $attendedByDoctorId === '' ? null : $attendedByDoctorId;

    if (!in_array($status, $statuses, true)) {
        $message = 'Choose a valid status.';
        $messageType = 'danger';
    } elseif ($isDoctor) {
        if ($status === 'Completed' && $attendedByDoctorId === null) {
            $message = 'Select the doctor who attended the patient before completing the appointment.';
            $messageType = 'danger';
        } elseif ($attendedByDoctorId !== null && !in_array($attendedByDoctorId, array_column($doctorsList, 'doctorID'), true)) {
            $message = 'Choose a valid doctor who attended the patient.';
            $messageType = 'danger';
        } else {
            $pdo->prepare(
                'UPDATE tblAppointments SET status = ?, attendedByDoctorID = ? WHERE appointmentID = ? AND doctorID = ?'
            )->execute([$status, $attendedByDoctorId, $id, $myDoctorId ?? '']);
            redirect('/appointments/index.php');
        }
    } else {
        $doctorId = trim($_POST['doctor_id'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');

        if ($status === 'Completed' && $attendedByDoctorId === null) {
            $message = 'Select the doctor who attended the patient before completing the appointment.';
            $messageType = 'danger';
        } elseif ($attendedByDoctorId !== null && !in_array($attendedByDoctorId, array_column($doctorsList, 'doctorID'), true)) {
            $message = 'Choose a valid doctor who attended the patient.';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM tblAppointments
                 WHERE doctorID = ? AND appointmentDate = ? AND appointmentTime = ? AND status = 'Booked' AND appointmentID <> ?"
            );
            $stmt->execute([$doctorId, $date, $time, $id]);

            if ($doctorId === '' || $date === '' || $time === '') {
                $message = 'Doctor, date and time are required.';
                $messageType = 'danger';
            } elseif ($status === 'Booked' && (int) $stmt->fetchColumn() > 0) {
                $message = 'That doctor already has an appointment at the selected date and time.';
                $messageType = 'danger';
            } else {
                $pdo->prepare(
                    'UPDATE tblAppointments SET doctorID = ?, appointmentDate = ?, appointmentTime = ?, status = ?, attendedByDoctorID = ? WHERE appointmentID = ?'
                )->execute([$doctorId, $date, $time, $status, $attendedByDoctorId, $id]);
                redirect('/appointments/index.php');
            }
        }

        $appointment['doctorID'] = $doctorId;
        $appointment['appointmentDate'] = $date;
        $appointment['appointmentTime'] = $time;
    }

    $appointment['status'] = $status;
}

$pageTitle = 'Edit Appointment';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'appointments'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Scheduling</p>
          <h2>Edit Appointment</h2>
        </div>
        <a href="<?php echo e(url('/appointments/index.php')); ?>" class="button-link secondary">Back to appointments</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3><?php echo e($appointment['appointmentID']); ?> - <?php echo e($appointment['patientName']); ?></h3>
        <p class="hint"><?php echo $isDoctor ? 'Update the status and record which doctor attended the patient.' : 'Reschedule or update this appointment and record the attending doctor.'; ?></p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo e($appointment['appointmentID']); ?>">
          <label>
            Doctor
            <select name="doctor_id" <?php echo $isDoctor ? 'disabled' : ''; ?>>
              <?php foreach ($doctorsList as $d): ?>
                <option value="<?php echo e($d['doctorID']); ?>" <?php echo $appointment['doctorID'] === $d['doctorID'] ? 'selected' : ''; ?>>
                  <?php echo e($d['fullName'] . ($d['specialty'] ? ' - ' . $d['specialty'] : '')); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Date
            <input type="date" name="date" value="<?php echo e($appointment['appointmentDate']); ?>" <?php echo $isDoctor ? 'disabled' : 'required'; ?>>
          </label>
          <label>
            Time
            <input type="time" name="time" value="<?php echo e(substr($appointment['appointmentTime'], 0, 5)); ?>" <?php echo $isDoctor ? 'disabled' : 'required'; ?>>
          </label>
          <label>
            Status
            <select name="status">
              <?php foreach ($statuses as $s): ?>
                <option value="<?php echo e($s); ?>" <?php echo $appointment['status'] === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Doctor who attended the patient
            <select name="attended_by_doctor_id">
              <option value="">Not recorded</option>
              <?php foreach ($doctorsList as $d): ?>
                <?php $selectedAttendingDoctor = $appointment['attendedByDoctorID'] ?? ($isDoctor ? $myDoctorId : null); ?>
                <option value="<?php echo e($d['doctorID']); ?>" <?php echo $selectedAttendingDoctor === $d['doctorID'] ? 'selected' : ''; ?>>
                  <?php echo e($d['fullName'] . ($d['specialty'] ? ' - ' . $d['specialty'] : '')); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="form-actions">
            <button type="submit" name="update_appointment">Save Changes</button>
            <a href="<?php echo e(url('/appointments/index.php')); ?>" class="button-link secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
