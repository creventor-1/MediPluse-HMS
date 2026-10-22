<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireAdmin();

$user = currentUser();
$pdo = getDb();

$totalPatients = (int) $pdo->query('SELECT COUNT(*) FROM tblPatients')->fetchColumn();
$totalDoctors = (int) $pdo->query('SELECT COUNT(*) FROM tblDoctors')->fetchColumn();
$totalAppointments = (int) $pdo->query('SELECT COUNT(*) FROM tblAppointments')->fetchColumn();
$todayAppointments = (int) $pdo->query(
    "SELECT COUNT(*) FROM tblAppointments WHERE appointmentDate = CURDATE() AND status <> 'Cancelled'"
)->fetchColumn();

$appointments = $pdo->query(
    "SELECT a.*, p.fullName AS patientName, d.fullName AS doctorName
     FROM tblAppointments a
     JOIN tblPatients p ON p.patientID = a.patientID
     JOIN tblDoctors d ON d.doctorID = a.doctorID
     WHERE a.status = 'Booked' AND a.appointmentDate >= CURDATE()
     ORDER BY a.appointmentDate ASC, a.appointmentTime ASC
     LIMIT 6"
)->fetchAll();

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Dashboard';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'dashboard'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="eyebrow">Overview</p>
          <h2><?php echo e($greeting); ?>, <?php echo e($user['name'] ?? 'Admin'); ?></h2>
        </div>
        <span class="pill"><?php echo e($user['role'] ?? ''); ?></span>
      </header>

      <section class="stats-grid">
        <div class="stat-card">
          <div>
            <span class="label">Total Patients</span>
            <strong><?php echo $totalPatients; ?></strong>
          </div>
          <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0116 0v1"/></svg>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <span class="label">Total Appointments</span>
            <strong><?php echo $totalAppointments; ?></strong>
          </div>
          <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <span class="label">Today's Appointments</span>
            <strong><?php echo $todayAppointments; ?></strong>
          </div>
          <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <span class="label">Doctors on Staff</span>
            <strong><?php echo $totalDoctors; ?></strong>
          </div>
          <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2v4a3 3 0 006 0V2M6 8v4a6 6 0 0012 0V8M12 18v4"/></svg>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h3>Upcoming Appointments</h3>
          <a href="<?php echo e(url('/appointments/index.php')); ?>" class="button-link small">Manage all</a>
        </div>

        <table>
          <thead>
            <tr>
              <th>Patient</th>
              <th>Doctor</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appointments)): ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <span class="emoji">📅</span>
                    No upcoming appointments.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($appointments as $appointment): ?>
                <tr>
                  <td class="cell-primary"><?php echo e($appointment['patientName']); ?></td>
                  <td><?php echo e($appointment['doctorName']); ?></td>
                  <td class="cell-muted"><?php echo e(date('M d, Y · h:i A', strtotime($appointment['appointmentDate'] . ' ' . $appointment['appointmentTime']))); ?></td>
                  <td><span class="status <?php echo e(strtolower($appointment['status'])); ?>"><?php echo e($appointment['status']); ?></span></td>
                  <td>
                    <a href="<?php echo e(url('/appointments/edit.php?id=' . urlencode($appointment['appointmentID']))); ?>" class="button-link secondary small">Edit</a>
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