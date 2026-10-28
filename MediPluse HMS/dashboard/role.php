<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireLogin();

$user = currentUser();
$role = roleKey((string) ($user['role'] ?? ''));
$expectedRole = $expectedDashboardRole ?? '';

if ($expectedRole === '' || $role !== $expectedRole) {
    redirect(roleDashboardPath((string) ($user['role'] ?? '')));
}

if ($role === 'admin' && !can('system', 'manage_users')) {
    denyAccess('System administration permission is required to open this dashboard.');
}
if ($role === 'patient') {
    redirect('/patient/portal.php');
}

$dashboardContent = [
    'admin' => [
        'title' => 'Hospital data overview',
        'description' => 'View records from all operational sections and a summary of their totals.',
    ],
    'supervisor' => [
        'title' => 'Hospital operations',
        'description' => 'Review summaries from the work areas explicitly granted to your account.',
    ],
    'doctor' => [
        'title' => 'Clinical workspace',
        'description' => 'Review permitted patient, appointment, doctor-directory and laboratory areas.',
    ],
    'receptionist' => [
        'title' => 'Reception workspace',
        'description' => 'Open permitted patient and appointment workflows from one place.',
    ],
    'lab_technician' => [
        'title' => 'Laboratory workbench',
        'description' => 'Review existing laboratory result records available to your account.',
    ],
    'billing_officer' => [
        'title' => 'Finance workspace',
        'description' => 'Review billing records available to your account.',
    ],
    'patient' => [
        'title' => 'Patient portal',
        'description' => 'View personal records and bills linked to your approved patient account.',
    ],
    'pending' => [
        'title' => 'Account awaiting approval',
        'description' => 'Your account is pending review. Work areas stay locked until an authorized manager approves access.',
    ],
];

if (!isset($dashboardContent[$role])) {
    denyAccess('No dashboard has been configured for this account role.');
}

$pdo = getDb();
$sections = appSections();
$availableSections = [];
if ($role !== 'admin') {
    foreach ($sections as $key => $section) {
        if ($role === 'patient' && in_array($key, ['staff', 'reports'], true)) {
            continue;
        }
        if (can($key, 'view')) {
            $availableSections[$key] = $section;
        }
    }
}
$landingSectionKey = roleLandingSection($role);
if ($landingSectionKey !== null) {
    if (!isset($availableSections[$landingSectionKey])) {
        $landingSectionKey = array_key_first($availableSections);
    }
    if ($landingSectionKey !== null) {
        redirect($availableSections[$landingSectionKey]['path']);
    }
}

$metricCards = [];
if ($role !== 'admin' && can('patients', 'view')) {
    $metricCards[] = [
        'label' => 'Registered patients',
        'value' => number_format((int) $pdo->query('SELECT COUNT(*) FROM tblPatients')->fetchColumn()),
        'note' => 'Patient records',
        'icon' => 'users',
    ];
}
if ($role !== 'admin' && can('appointments', 'view')) {
    $statement = $pdo->query(
        "SELECT COUNT(*) FROM tblAppointments
         WHERE appointmentDate = CURDATE() AND status <> 'Cancelled'"
    );
    $metricCards[] = [
        'label' => "Today's appointments",
        'value' => number_format((int) $statement->fetchColumn()),
        'note' => 'Not cancelled',
        'icon' => 'calendar',
    ];
}
if ($role !== 'admin' && can('doctors', 'view')) {
    $metricCards[] = [
        'label' => 'Doctors on file',
        'value' => number_format((int) $pdo->query('SELECT COUNT(*) FROM tblDoctors')->fetchColumn()),
        'note' => 'Doctor directory',
        'icon' => 'stethoscope',
    ];
}
if ($role !== 'admin' && can('lab', 'view')) {
    $statement = $pdo->query(
        "SELECT COUNT(*) FROM tblLabResults WHERE resultValue IS NULL OR resultValue = ''"
    );
    $metricCards[] = [
        'label' => 'Results awaiting entry',
        'value' => number_format((int) $statement->fetchColumn()),
        'note' => 'Existing lab records',
        'icon' => 'flask-conical',
    ];
}
if ($role !== 'admin' && can('billing', 'view')) {
    $statement = $pdo->query(
        "SELECT COUNT(*) FROM tblBilling WHERE paymentStatus = 'Unpaid'"
    );
    $metricCards[] = [
        'label' => 'Unpaid bills',
        'value' => number_format((int) $statement->fetchColumn()),
        'note' => 'Current billing records',
        'icon' => 'credit-card',
    ];
}
if ($role !== 'admin' && can('staff', 'view')) {
    $metricCards[] = [
        'label' => 'Staff records',
        'value' => number_format((int) $pdo->query('SELECT COUNT(*) FROM tblStaff')->fetchColumn()),
        'note' => 'Staff directory',
        'icon' => 'user-plus',
    ];
}

$adminPatientCount = $role === 'admin'
    ? number_format((int) $pdo->query('SELECT COUNT(*) FROM tblPatients')->fetchColumn())
    : null;
$adminDataTables = [];
if ($role === 'admin') {
    $adminTableQueries = [
        'Patients' => [
            'columns' => ['Patient ID' => 'patientID', 'Name' => 'fullName', 'Gender' => 'gender', 'Date of birth' => 'dateOfBirth', 'Phone' => 'phoneNumber', 'Address' => 'address', 'Registered' => 'registrationDate'],
            'sql' => 'SELECT patientID, fullName, gender, dateOfBirth, phoneNumber, address, registrationDate FROM tblPatients ORDER BY registrationDate DESC, patientID',
        ],
        'Appointments' => [
            'columns' => ['Appointment ID' => 'appointmentID', 'Patient ID' => 'patientID', 'Doctor ID' => 'doctorID', 'Date' => 'appointmentDate', 'Time' => 'appointmentTime', 'Status' => 'status'],
            'sql' => 'SELECT appointmentID, patientID, doctorID, appointmentDate, appointmentTime, status FROM tblAppointments ORDER BY appointmentDate DESC, appointmentTime DESC, appointmentID',
        ],
        'Doctors' => [
            'columns' => ['Doctor ID' => 'doctorID', 'Name' => 'fullName', 'Specialty' => 'specialty', 'Phone' => 'phoneNumber', 'User ID' => 'userID'],
            'sql' => 'SELECT doctorID, fullName, specialty, phoneNumber, userID FROM tblDoctors ORDER BY fullName, doctorID',
        ],
        'Lab Results' => [
            'columns' => ['Result ID' => 'labResultID', 'Patient ID' => 'patientID', 'Requested by' => 'requestedBy', 'Test' => 'testType', 'Result' => 'resultValue', 'Recorded' => 'dateRecorded'],
            'sql' => 'SELECT labResultID, patientID, requestedBy, testType, resultValue, dateRecorded FROM tblLabResults ORDER BY dateRecorded DESC, labResultID',
        ],
        'Billing' => [
            'columns' => ['Bill ID' => 'billID', 'Patient ID' => 'patientID', 'Appointment ID' => 'appointmentID', 'Amount' => 'amount', 'Payment status' => 'paymentStatus', 'Issued' => 'dateIssued'],
            'sql' => 'SELECT billID, patientID, appointmentID, amount, paymentStatus, dateIssued FROM tblBilling ORDER BY dateIssued DESC, billID',
        ],
        'Staff' => [
            'columns' => ['Staff ID' => 'staffID', 'Name' => 'fullName', 'Designation' => 'designation', 'User ID' => 'userID'],
            'sql' => 'SELECT staffID, fullName, designation, userID FROM tblStaff ORDER BY fullName, staffID',
        ],
    ];

    foreach ($adminTableQueries as $title => $table) {
        $adminDataTables[$title] = [
            'columns' => $table['columns'],
            'rows' => $pdo->query($table['sql'])->fetchAll(),
        ];
    }

    $adminDataTables['Reports'] = [
        'columns' => ['Section' => 'section', 'Records' => 'total'],
        'rows' => $pdo->query(
            "SELECT 'Patients' AS section, COUNT(*) AS total FROM tblPatients
             UNION ALL SELECT 'Appointments', COUNT(*) FROM tblAppointments
             UNION ALL SELECT 'Doctors', COUNT(*) FROM tblDoctors
             UNION ALL SELECT 'Lab Results', COUNT(*) FROM tblLabResults
             UNION ALL SELECT 'Billing', COUNT(*) FROM tblBilling
             UNION ALL SELECT 'Staff', COUNT(*) FROM tblStaff"
        )->fetchAll(),
    ];
}

$appointments = [];
$canAppointments = $role !== 'admin' && can('appointments', 'view');
$canPatientDetails = $canAppointments && can('patients', 'view');
$canDoctorPatientDetails = $role === 'doctor' && $canPatientDetails && currentDoctorId($pdo) !== null;
$canDoctorDetails = $canAppointments && can('doctors', 'view');
if ($canAppointments) {
    $selectPatient = $canPatientDetails ? 'p.fullName' : "'Restricted'";
    $selectDoctor = $canDoctorDetails ? 'd.fullName' : "'Restricted'";
    $selectPatientDetails = $canDoctorPatientDetails
        ? ', p.patientID AS patientRecordID, p.gender AS patientGender,
           p.dateOfBirth AS patientDateOfBirth, p.phoneNumber AS patientPhone, p.address AS patientAddress'
        : '';
    $joins = '';
    if ($canPatientDetails) {
        $joins .= ' LEFT JOIN tblPatients p ON p.patientID = a.patientID';
    }
    if ($canDoctorDetails) {
        $joins .= ' LEFT JOIN tblDoctors d ON d.doctorID = a.doctorID';
    }

    $sql = "SELECT a.appointmentID, a.doctorID, a.appointmentDate, a.appointmentTime, a.status,
                   {$selectPatient} AS patientName, {$selectDoctor} AS doctorName{$selectPatientDetails}
            FROM tblAppointments a {$joins}
            WHERE a.status <> 'Cancelled' AND a.appointmentDate >= CURDATE()";
    $parameters = [];
    if ($role === 'doctor') {
        $sql .= ' AND a.doctorID = ?';
        $parameters[] = currentDoctorId($pdo) ?? '';
    }
    $sql .= ' ORDER BY a.appointmentDate, a.appointmentTime LIMIT 6';
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $appointments = $statement->fetchAll();
}

$pageTitle = $dashboardContent[$role]['title'];
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'dashboard'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content role-dashboard">
      <header class="topbar">
        <div>
          <p class="eyebrow"><?php echo e($user['role'] ?? ''); ?> workspace</p>
          <h2><?php echo e($dashboardContent[$role]['title']); ?></h2>
        </div>
        <span class="pill"><?php echo e($user['name'] ?? ''); ?></span>
      </header>

      <p class="dashboard-intro"><?php echo e($dashboardContent[$role]['description']); ?></p>

      <?php if (($user['accountStatus'] ?? '') === 'pending' || $role === 'pending'): ?>
        <section class="dashboard-notice" role="status">
          <span class="dashboard-notice-icon" aria-hidden="true">!</span>
          <div><strong>Approval required</strong><p>Your account is awaiting review. You can sign out while an authorized manager reviews your request.</p></div>
        </section>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
        <section class="stats-grid" aria-label="Total patient records">
          <article class="stat-card">
            <div>
              <span class="label">Total patient records</span>
              <strong><?php echo e($adminPatientCount); ?></strong>
              <small class="dashboard-metric-note">People recorded in the patient database</small>
            </div>
            <span class="dashboard-metric-icon" aria-hidden="true"><?php echo icon('users'); ?></span>
          </article>
        </section>
        <?php foreach ($adminDataTables as $tableTitle => $tableData): ?>
          <section class="panel dashboard-table-panel">
            <div class="panel-header">
              <div>
                <span class="dashboard-eyebrow">ADMIN RECORDS</span>
                <h3><?php echo e($tableTitle); ?></h3>
              </div>
              <span><?php echo number_format(count($tableData['rows'])); ?> record<?php echo count($tableData['rows']) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr><?php foreach ($tableData['columns'] as $label => $_column): ?><th><?php echo e($label); ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                  <?php if (!$tableData['rows']): ?>
                    <tr><td colspan="<?php echo count($tableData['columns']); ?>"><p class="dashboard-empty">No records are currently stored.</p></td></tr>
                  <?php else: ?>
                    <?php foreach ($tableData['rows'] as $row): ?>
                      <tr>
                        <?php foreach ($tableData['columns'] as $column): ?>
                          <td><?php echo e(($row[$column] ?? '') !== '' ? (string) $row[$column] : '—'); ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($role !== 'admin' && $metricCards): ?>
        <section class="stats-grid" aria-label="Authorized workspace summaries">
          <?php foreach ($metricCards as $metric): ?>
            <article class="stat-card">
              <div><span class="label"><?php echo e($metric['label']); ?></span><strong><?php echo e($metric['value']); ?></strong><small class="dashboard-metric-note"><?php echo e($metric['note']); ?></small></div>
              <span class="dashboard-metric-icon" aria-hidden="true"><?php echo icon($metric['icon']); ?></span>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if ($role !== 'admin'): ?>
      <section class="dashboard-section">
        <div class="dashboard-section-heading">
          <div><span class="dashboard-eyebrow">YOUR ACCESS</span><h3>Work areas</h3></div>
          <span><?php echo count($availableSections); ?> available</span>
        </div>
        <?php if ($availableSections): ?>
          <div class="dashboard-work-grid">
            <?php foreach ($availableSections as $key => $section): ?>
              <a class="dashboard-work-card" href="<?php echo e(url($section['path'])); ?>">
                <span class="dashboard-work-icon" aria-hidden="true"><?php echo icon(sectionIcon($key)); ?></span>
                <span><strong><?php echo e($section['label']); ?></strong><small>Open authorised section</small></span>
                <span class="dashboard-work-arrow" aria-hidden="true"><?php echo icon('arrow-right', '', 16); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="dashboard-empty">No operational sections are assigned to this account. Contact an authorized manager if you need access.</p>
        <?php endif; ?>
      </section>

      <?php if ($canAppointments): ?>
        <section class="panel dashboard-table-panel">
          <div class="panel-header">
            <div><span class="dashboard-eyebrow">SCHEDULE</span><h3>Upcoming appointments</h3></div>
            <a class="button-link small" href="<?php echo e(url('/appointments/index.php')); ?>">Open appointments</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Patient</th><?php if ($canDoctorPatientDetails): ?><th>Patient details</th><?php endif; ?><th>Doctor</th><th>Date and time</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (!$appointments): ?>
                  <tr><td colspan="<?php echo 4 + (int) $canDoctorPatientDetails; ?>"><p class="dashboard-empty">No upcoming appointments are recorded.</p></td></tr>
                <?php else: ?>
                  <?php foreach ($appointments as $appointment): ?>
                    <tr>
                      <td class="cell-primary"><?php echo e($appointment['patientName']); ?></td>
                      <?php if ($canDoctorPatientDetails): ?>
                      <td>
                        <details>
                          <summary>View patient details</summary>
                          <dl>
                            <dt>Patient ID</dt><dd><?php echo e($appointment['patientRecordID']); ?></dd>
                            <dt>Gender</dt><dd><?php echo e($appointment['patientGender']); ?></dd>
                            <dt>Date of birth</dt><dd><?php echo e($appointment['patientDateOfBirth']); ?></dd>
                            <dt>Phone</dt><dd><?php echo e($appointment['patientPhone'] ?: 'Not recorded'); ?></dd>
                            <dt>Address</dt><dd><?php echo e($appointment['patientAddress'] ?: 'Not recorded'); ?></dd>
                          </dl>
                        </details>
                      </td>
                      <?php endif; ?>
                      <td><?php echo e($appointment['doctorName']); ?></td>
                      <td><?php echo e(date('M d, Y · h:i A', strtotime($appointment['appointmentDate'] . ' ' . $appointment['appointmentTime']))); ?></td>
                      <td><?php echo statusBadge($appointment['status']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <section class="dashboard-limitations" aria-label="Current workflow availability">
        <?php if ($role === 'doctor'): ?>
          Clinical encounters, signed notes, prescriptions, and referrals are not implemented yet.
        <?php elseif ($role === 'receptionist'): ?>
          Patient registration and appointment records are available; check-in and waiting-list workflows are not implemented yet.
        <?php elseif ($role === 'lab_technician'): ?>
          This build stores basic lab result records; specimen tracking, validation, approval, and release workflows are not implemented yet.
        <?php elseif ($role === 'billing_officer'): ?>
          This build stores bills and paid/unpaid status only; payment transactions, reconciliation, refunds, and receipts are not implemented yet.
        <?php elseif ($role === 'supervisor'): ?>
          Summaries are limited to granted sections and the fields currently stored in the database.
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </main>
  </div>
  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
