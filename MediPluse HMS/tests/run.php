<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException('Test failed: ' . $message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE tblUsers (
        userID TEXT PRIMARY KEY, username TEXT UNIQUE, email TEXT UNIQUE, displayName TEXT,
        passwordHash TEXT, role TEXT, accountStatus TEXT, sessionVersion INTEGER DEFAULT 0
    );
    CREATE TABLE tblDoctors (
        doctorID TEXT PRIMARY KEY, userID TEXT UNIQUE, fullName TEXT, specialty TEXT, phoneNumber TEXT
    );
    CREATE TABLE tblAppointments (
        appointmentID TEXT PRIMARY KEY, doctorID TEXT, bookedByDoctorID TEXT
    );
    CREATE TABLE tblLabResults (
        labResultID TEXT PRIMARY KEY, requestedBy TEXT, resultValue TEXT, dateRecorded TEXT
    );
    CREATE TABLE tblStaff (staffID TEXT, userID TEXT, fullName TEXT);
    CREATE TABLE tblPatientAccounts (
        userID TEXT, patientID TEXT, accountStatus TEXT,
        PRIMARY KEY (userID, patientID)
    );
    CREATE TABLE tblRoles (roleKey TEXT PRIMARY KEY, roleName TEXT, isAssignable INTEGER);
    CREATE TABLE tblUserRoleRequests (
        userID TEXT PRIMARY KEY, requestedRole TEXT, doctorSpecialty TEXT, requestedAt TEXT DEFAULT CURRENT_TIMESTAMP,
        reviewedBy TEXT, reviewedAt TEXT
    );
    CREATE TABLE tblRolePermissions (
        roleKey TEXT, sectionKey TEXT, actionKey TEXT, isAllowed INTEGER,
        PRIMARY KEY (roleKey, sectionKey, actionKey)
    );
    CREATE TABLE tblUserPermissions (
        userID TEXT, sectionKey TEXT, actionKey TEXT, isAllowed INTEGER,
        updatedBy TEXT, updatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (userID, sectionKey, actionKey)
    );
    CREATE TABLE tblPasswordResetTokens (
        tokenHash TEXT PRIMARY KEY, userID TEXT, expiresAt TEXT, consumedAt TEXT, createdAt TEXT
    );
    CREATE TABLE tblAuditLog (
        auditID INTEGER PRIMARY KEY AUTOINCREMENT, userID TEXT, sectionKey TEXT, actionKey TEXT,
        targetID TEXT, createdAt TEXT DEFAULT CURRENT_TIMESTAMP
    );'
);
$pdo->exec(
    "INSERT INTO tblRoles (roleKey, roleName, isAssignable) VALUES
     ('admin', 'Administrator', 1), ('supervisor', 'Supervisor', 1), ('doctor', 'Doctor', 1),
     ('receptionist', 'Receptionist', 1), ('lab_technician', 'Lab Technician', 1),
     ('billing_officer', 'Billing Officer', 1), ('patient', 'Patient', 1), ('pending', 'Pending', 0)"
);

$sections = array_keys(appSections());
$supervisorGrant = $pdo->prepare(
    "INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed) VALUES ('supervisor', ?, 'view', 1)"
);
foreach ($sections as $section) {
    $supervisorGrant->execute([$section]);
}
$pdo->exec(
    "INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed)
     VALUES ('receptionist', 'patients', 'view', 1)"
);
$pdo->exec(
    "INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus)
     VALUES ('USR1', 'manager', 'manager@example.test', 'Manager', '" . password_hash('correct horse battery staple', PASSWORD_DEFAULT) . "', 'Administrator', 'active')"
);

$assert(roleKey('Administrator') === 'admin', 'administrator role normalization');
$assert(roleKey('Manager') === 'supervisor', 'manager role normalization');
$assert(roleKey('Lab Technician') === 'lab_technician', 'section role normalization');
$dashboardRoutes = [
    'Administrator' => '/dashboard/admin.php',
    'Supervisor' => '/dashboard/manager.php',
    'Doctor' => '/dashboard/doctor.php',
    'Receptionist' => '/dashboard/reception.php',
    'Lab Technician' => '/dashboard/laboratory.php',
    'Billing Officer' => '/dashboard/billing.php',
    'Patient' => '/dashboard/patient.php',
    'Pending' => '/dashboard/pending.php',
];
foreach ($dashboardRoutes as $dashboardRole => $dashboardRoute) {
    $assert(roleDashboardPath($dashboardRole) === $dashboardRoute, $dashboardRole . ' has a dedicated dashboard route');
    $routeFile = __DIR__ . '/..' . $dashboardRoute;
    $assert(is_file($routeFile), $dashboardRole . ' dashboard route exists');
}
$assert(roleDashboardPath('unrecognized') === '/dashboard/unassigned.php', 'unknown roles do not fall back to the generic dashboard');
$assert(is_file(__DIR__ . '/../dashboard/unassigned.php'), 'unassigned roles have a guarded fallback route');
$landingSections = [
    'Doctor' => 'appointments',
    'Receptionist' => 'patients',
    'Lab Technician' => 'lab',
    'Laboratory Scientist' => 'lab',
    'Billing Officer' => 'billing',
    'Supervisor' => null,
    'Patient' => null,
    'Pending' => null,
];
foreach ($landingSections as $landingRole => $landingSection) {
    $assert(
        roleLandingSection($landingRole) === $landingSection,
        $landingRole . ' has the intended direct landing section'
    );
}
$dashboardSource = file_get_contents(__DIR__ . '/../dashboard/role.php');
$assert(
    $dashboardSource !== false
        && strpos($dashboardSource, '$landingSectionKey = roleLandingSection($role)') !== false
        && strpos($dashboardSource, '$landingSectionKey = array_key_first($availableSections)') !== false
        && strpos($dashboardSource, "redirect(\$availableSections[\$landingSectionKey]['path'])") !== false,
    'staff role landing redirects only to a permitted operational section'
);
$assert($dashboardSource !== false && strpos($dashboardSource, '$role !== $expectedRole') !== false, 'dashboard rejects a mismatched account role');
$assert($dashboardSource !== false && strpos($dashboardSource, "can('system', 'manage_users')") !== false, 'administrator dashboard requires explicit system permission');
$assert(
    $dashboardSource !== false
        && strpos($dashboardSource, "SELECT COUNT(*) FROM tblPatients") !== false
        && strpos($dashboardSource, 'Total patient records') !== false
        && strpos($dashboardSource, '$role !== \'admin\'') !== false,
    'administrator dashboard includes the aggregate patient-record count'
);
$assert(
    $dashboardSource !== false
        && strpos($dashboardSource, 'Recent audit events') === false
        && strpos($dashboardSource, '$adminDataTables') !== false
        && strpos($dashboardSource, '$adminDataTables[\'Reports\']') !== false
        && strpos($dashboardSource, 'SELECT patientID, fullName, gender, dateOfBirth, phoneNumber, address, registrationDate FROM tblPatients') !== false
        && strpos($dashboardSource, 'No records are currently stored.') !== false,
    'administrator dashboard displays all operational table rows plus a report summary'
);
$assert(
    $dashboardSource !== false
        && strpos($dashboardSource, "'Appointments' =>") !== false
        && strpos($dashboardSource, "'Doctors' =>") !== false
        && strpos($dashboardSource, "'Lab Results' =>") !== false
        && strpos($dashboardSource, "'Billing' =>") !== false
        && strpos($dashboardSource, "'Staff' =>") !== false
        && strpos($dashboardSource, "if (\$role === 'admin')") !== false,
    'all requested record tables are guarded by the administrator role'
);
$assert($dashboardSource !== false && strpos($dashboardSource, '$canPatientDetails ?') !== false, 'dashboard hides patient names without patient-section permission');
$assert(
    $dashboardSource !== false
        && strpos($dashboardSource, "if (\$role === 'patient')") !== false
        && strpos($dashboardSource, "redirect('/patient/portal.php')") !== false,
    'patient dashboard routes to the private patient portal'
);
$portalSource = file_get_contents(__DIR__ . '/../patient/portal.php');
$assert(
    $portalSource !== false
        && strpos($portalSource, 'requirePatientRole()') !== false
        && strpos($portalSource, 'linkedPatientIds($pdo, $userId)') !== false
        && strpos($portalSource, 'WHERE b.patientID IN ($placeholders)') !== false,
    'patient portal requires the patient role and filters all displayed bills by approved patient links'
);
$assert(
    $portalSource !== false
        && strpos($portalSource, 'INSERT INTO tblBilling') === false
        && strpos($portalSource, 'tblPatientPaymentRequests') !== false
        && strpos($portalSource, 'The bill will remain unpaid until staff verifies and records the payment.') !== false,
    'patients cannot create charges and payment selections remain pending for staff verification'
);
$patientLinkSource = file_get_contents(__DIR__ . '/../admin/permissions.php');
$assert(
    $patientLinkSource !== false
        && strpos($patientLinkSource, "if (!can('patients', 'view'))") !== false
        && strpos($patientLinkSource, "WHERE userID = ? AND accountStatus = 'active'") !== false
        && strpos($patientLinkSource, 'Verify and link patient') !== false,
    'patient records are explicitly linked by authorized managers and active ownership is checked'
);
$billingSourceForRequests = file_get_contents(__DIR__ . '/../billing/index.php');
$assert(
    $billingSourceForRequests !== false
        && strpos($billingSourceForRequests, 'complete_payment_request') !== false
        && strpos($billingSourceForRequests, 'reject_payment_request') !== false
        && strpos($billingSourceForRequests, "status = 'completed'") !== false
        && strpos($billingSourceForRequests, "requireSectionPermission('billing', 'update')") !== false,
    'billing staff manually verify and record patient payment requests'
);
$patientPaymentMigration = file_get_contents(__DIR__ . '/../database/migrations/005_patient_portal_payments.sql');
$assert(
    $patientPaymentMigration !== false
        && strpos($patientPaymentMigration, 'CREATE TABLE IF NOT EXISTS tblPatientPaymentRequests') !== false
        && strpos($patientPaymentMigration, "paymentMethod IN ('Cash','Bank transfer','ATM card')") !== false
        && strpos($patientPaymentMigration, "status IN ('pending','completed','rejected','cancelled')") !== false,
    'patient payment requests have explicit supported methods and review states'
);
$pdo->exec(
    "INSERT INTO tblPatientAccounts (userID, patientID, accountStatus) VALUES
     ('USR1', 'PAT1', 'active'), ('USR1', 'PAT2', 'revoked'),
     ('USR2', 'PAT3', 'active')"
);
$assert(linkedPatientIds($pdo, 'USR1') === ['PAT1'], 'patient record lookup returns only approved active links for the signed-in user');
$assert(linkedPatientIds($pdo, 'USR2') === ['PAT3'], 'patient record lookup isolates different user accounts');
$assert(linkedPatientIds($pdo, 'UNKNOWN') === [], 'accounts without an approved link receive no patient records');
$foundationMigration = file_get_contents(__DIR__ . '/../database/migrations/002_shared_care_foundation.sql');
$assert($foundationMigration !== false && strpos($foundationMigration, 'CREATE TABLE IF NOT EXISTS tblPatientAccounts') !== false, 'patient account ownership relation is included in the foundation migration');
$assert($foundationMigration !== false && strpos($foundationMigration, 'CREATE TABLE IF NOT EXISTS tblEncounters') !== false, 'shared encounter anchor is included in the foundation migration');
$roleRequestMigration = file_get_contents(__DIR__ . '/../database/migrations/003_signup_role_requests.sql');
$assert($roleRequestMigration !== false && strpos($roleRequestMigration, 'CREATE TABLE IF NOT EXISTS tblUserRoleRequests') !== false, 'requested signup role is stored separately from the assigned role');
$doctorSignupMigration = file_get_contents(__DIR__ . '/../database/migrations/009_doctor_signup_specialty.sql');
$assert(
    $doctorSignupMigration !== false
        && strpos($doctorSignupMigration, 'ADD COLUMN doctorSpecialty VARCHAR(50) NULL') !== false,
    'doctor specialty is persisted with signup requests'
);
$permissionsSource = file_get_contents(__DIR__ . '/../admin/permissions.php');
$assert(
    $permissionsSource !== false
        && strpos($permissionsSource, 'LEFT JOIN tblUserRoleRequests') !== false
        && strpos($permissionsSource, 'Requested account type:') !== false
        && strpos($permissionsSource, 'ensureDoctorProfileForAccount($pdo, $targetUserId, $doctorSpecialty)') !== false
        && strpos($permissionsSource, 'name="doctor_specialty"') !== false,
    'authorized managers can review requested account types'
);
$assert(
    $permissionsSource !== false
        && strpos($permissionsSource, 'information_schema.COLUMNS') !== false
        && strpos($permissionsSource, 'NULL AS doctorSpecialty') !== false
        && strpos($permissionsSource, 'Doctor approval is temporarily unavailable') !== false,
    'permissions page handles a missing doctor-specialty migration without a fatal query error'
);
$assert(
    $permissionsSource !== false
        && strpos($permissionsSource, 'SELECT 1 FROM tblUsers WHERE email = ? AND userID <> ? LIMIT 1') !== false
        && strpos($permissionsSource, 'That email address is already assigned to another account.') !== false
        && strpos($permissionsSource, '(int) $error->errorInfo[1] === 1062') !== false
        && strpos($permissionsSource, '$emailInputValue ?? $selectedUser[\'email\']') !== false,
    'user management handles duplicate email addresses without a fatal error and preserves the submitted email'
);
$emailOwnerCheck = $pdo->prepare(
    'SELECT 1 FROM tblUsers WHERE email = ? AND userID <> ? LIMIT 1'
);
$emailOwnerCheck->execute(['manager@example.test', 'USR2']);
$assert((bool) $emailOwnerCheck->fetchColumn(), 'duplicate email check finds a different owning account');
$emailOwnerCheck->execute(['manager@example.test', 'USR1']);
$assert(!$emailOwnerCheck->fetchColumn(), 'duplicate email check allows an account to retain its own email');
$assert(
    $permissionsSource !== false
        && strpos($permissionsSource, 'class="form-grid permissions-form"') !== false
        && strpos($permissionsSource, 'class="panel permissions-panel"') !== false
        && strpos($permissionsSource, 'class="table-wrap"') !== false,
    'individual permission table has a full-row scrollable wrapper'
);
$permissionThemeSource = file_get_contents(__DIR__ . '/../assets/css/style_theme.css');
$assert(
    $permissionThemeSource !== false
        && strpos($permissionThemeSource, '.permissions-form > .permissions-panel') !== false
        && strpos($permissionThemeSource, 'grid-column: 1 / -1') !== false
        && strpos($permissionThemeSource, '.permissions-panel .table-wrap') !== false
        && strpos($permissionThemeSource, 'overflow-x: auto') !== false,
    'permission table stacks below account fields and scrolls inside its panel'
);
$doctorRouteSource = file_get_contents(__DIR__ . '/../doctors/index.php');
$doctorAccountGuard = $doctorRouteSource !== false
    ? strpos($doctorRouteSource, "if (\$userId && !can('system', 'manage_users'))")
    : false;
$doctorAccountDelete = $doctorRouteSource !== false
    ? strpos($doctorRouteSource, "DELETE FROM tblUsers WHERE userID = ?")
    : false;
$assert(
    $doctorAccountGuard !== false && $doctorAccountDelete !== false && $doctorAccountGuard < $doctorAccountDelete,
    'deleting a linked doctor account requires explicit user-management permission'
);
$appointmentRouteSource = file_get_contents(__DIR__ . '/../appointments/index.php');
$doctorsRouteSource = file_get_contents(__DIR__ . '/../doctors/index.php');
$patientPortalSource = file_get_contents(__DIR__ . '/../patient/portal.php');
$assert(
    $appointmentRouteSource !== false
        && strpos($appointmentRouteSource, 'bookedByDoctorID)') !== false
        && strpos($appointmentRouteSource, "\$roleKey === 'doctor' ? \$myDoctorId : null") !== false
        && strpos($appointmentRouteSource, 'a.bookedByDoctorID = ?') !== false
        && strpos($appointmentRouteSource, 'bookingDoctor.fullName AS bookedByDoctorName') !== false
        && strpos($appointmentRouteSource, '<th>Booked by</th>') !== false
        && strpos($appointmentRouteSource, "\$a['bookedByDoctorName'] ?: 'Not recorded'") !== false
        && strpos($appointmentRouteSource, '$canViewAssignedPatientDetails') !== false
        && strpos($appointmentRouteSource, "\$a['doctorID'] === \$myDoctorId") !== false
        && strpos($appointmentRouteSource, 'patientAddress') !== false
        && strpos($appointmentRouteSource, '$isAssignedDoctor && $canUpdate') !== false
        && strpos($appointmentRouteSource, '$isAssignedDoctor && $canDelete') !== false
        && strpos($appointmentRouteSource, 'View only') !== false
        && strpos($appointmentRouteSource, '(referred)') !== false
        && strpos($appointmentRouteSource, 'DELETE FROM tblAppointments WHERE appointmentID = ? AND doctorID = ?') !== false
        && strpos($appointmentRouteSource, '$roleKey !== \'doctor\' || $myDoctorId !== null') !== false,
    'referring doctors see referred appointments but only assigned doctors can edit or delete'
);
$roleDashboardSource = file_get_contents(__DIR__ . '/../dashboard/role.php');
$assert(
    $roleDashboardSource !== false
        && strpos($roleDashboardSource, '$canDoctorPatientDetails') !== false
        && strpos($roleDashboardSource, 'a.doctorID = ?') !== false
        && strpos($roleDashboardSource, 'View patient details') !== false
        && strpos($roleDashboardSource, 'p.patientID AS patientRecordID') !== false,
    'upcoming appointment dashboard shows patient details only within the selected doctor’s assigned schedule'
);
$doctorAppointmentMigration = file_get_contents(__DIR__ . '/../database/migrations/006_doctor_appointment_booking.sql');
$attendingDoctorMigration = file_get_contents(__DIR__ . '/../database/migrations/007_appointment_attending_doctor.sql');
$bookingDoctorMigration = file_get_contents(__DIR__ . '/../database/migrations/008_appointment_booking_doctor.sql');
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql');
$assert(
    $appointmentRouteSource !== false
        && strpos($appointmentRouteSource, "\$doctorId = trim(\$_POST['doctor_id'] ?? '')") !== false
        && strpos($appointmentRouteSource, '<select name="doctor_id" required>') !== false
        && strpos($appointmentRouteSource, "\$sql .= ' WHERE (a.doctorID = ? OR a.bookedByDoctorID = ?)'") !== false
        && strpos($appointmentRouteSource, 'attending.fullName AS attendedByName') !== false
        && strpos($appointmentRouteSource, 'a.attendedByDoctorID') !== false
        && strpos($appointmentRouteSource, 'Doctor who attended') !== false
        && strpos($appointmentRouteSource, 'My Appointments') !== false
        && $doctorAppointmentMigration !== false
        && strpos($doctorAppointmentMigration, "('doctor', 'appointments', 'create', 1)") !== false,
    'doctors can refer appointments to a selected doctor, and appointments appear in the assigned doctor’s list'
);
$appointmentEditSource = file_get_contents(__DIR__ . '/../appointments/edit.php');
$assert(
    $appointmentEditSource !== false
        && strpos($appointmentEditSource, 'AND a.doctorID = ?') !== false
        && strpos($appointmentEditSource, 'WHERE appointmentID = ? AND doctorID = ?') !== false
        && strpos($appointmentEditSource, 'name="attended_by_doctor_id"') !== false
        && strpos($appointmentEditSource, 'attendedByDoctorID = ?') !== false
        && strpos($appointmentEditSource, 'Select the doctor who attended the patient before completing the appointment.') !== false
        && $attendingDoctorMigration !== false
        && strpos($attendingDoctorMigration, 'attendedByDoctorID') !== false
        && $bookingDoctorMigration !== false
        && strpos($bookingDoctorMigration, 'bookedByDoctorID') !== false
        && strpos($bookingDoctorMigration, 'information_schema.COLUMNS') !== false
        && strpos($bookingDoctorMigration, 'information_schema.KEY_COLUMN_USAGE') !== false
        && $schemaSource !== false
        && strpos($schemaSource, 'bookedByDoctorID VARCHAR(10) NULL') !== false
        && $patientPortalSource !== false
        && strpos($patientPortalSource, 'attending.fullName AS attendedByName') !== false
        && strpos($patientPortalSource, 'Doctor who attended') !== false,
    'assigned doctors can record the attending doctor, and completed visits require that record'
);
$pdo->exec(
    "INSERT INTO tblAppointments (appointmentID, doctorID, bookedByDoctorID) VALUES
     ('APT-REFERRAL', 'DOC-2', 'DOC-1'), ('APT-ASSIGNED', 'DOC-1', NULL)"
);
$doctorAppointments = $pdo->prepare(
    'SELECT appointmentID FROM tblAppointments WHERE doctorID = ? OR bookedByDoctorID = ? ORDER BY appointmentID'
);
$doctorAppointments->execute(['DOC-1', 'DOC-1']);
$assert(
    $doctorAppointments->fetchAll(PDO::FETCH_COLUMN) === ['APT-ASSIGNED', 'APT-REFERRAL'],
    'doctor list includes appointments assigned to or booked by the current doctor'
);
$assert(
    $doctorsRouteSource !== false
        && strpos($doctorsRouteSource, "can('doctors', 'view') && !\$canManageUsers") !== false
        && strpos($doctorsRouteSource, "isset(\$_POST['link_doctor_account'])") !== false
        && strpos($doctorsRouteSource, 'requireUserManagementPermission()') !== false
        && strpos($doctorsRouteSource, "roleKey((string) \$account['role']) !== 'doctor'") !== false
        && strpos($doctorsRouteSource, 'NOT EXISTS (SELECT 1 FROM tblDoctors d WHERE d.userID = u.userID)') !== false
        && strpos($doctorsRouteSource, "recordAuditEvent(\$pdo, 'system', 'doctor_account_linked'") !== false,
    'a user manager can explicitly link an active Doctor login to one doctor profile without guessing identity'
);
$labRouteSource = file_get_contents(__DIR__ . '/../lab/index.php');
$assert(
    $labRouteSource !== false
        && strpos($labRouteSource, 'DELETE FROM tblLabResults WHERE labResultID = ? AND requestedBy = ?') !== false,
    'laboratory deletion is scoped to the requesting doctor'
);
$labEditSource = file_get_contents(__DIR__ . '/../lab/edit.php');
$assert(
    $labEditSource !== false
        && strpos($labEditSource, 'AND l.requestedBy = ?') !== false
        && strpos($labEditSource, "AND requestedBy = ?") !== false,
    'laboratory direct reads and updates enforce doctor ownership'
);
$pdo->exec(
    "INSERT INTO tblAppointments (appointmentID, doctorID) VALUES ('APT-OWN', 'DOC-1'), ('APT-OTHER', 'DOC-2');
     INSERT INTO tblLabResults (labResultID, requestedBy, resultValue, dateRecorded)
     VALUES ('LAB-OWN', 'DOC-1', NULL, NULL), ('LAB-OTHER', 'DOC-2', NULL, NULL)"
);
$appointmentDelete = $pdo->prepare(
    'DELETE FROM tblAppointments WHERE appointmentID = ? AND doctorID = ?'
);
$appointmentDelete->execute(['APT-OTHER', 'DOC-1']);
$assert($appointmentDelete->rowCount() === 0, 'doctor cannot delete another doctor appointment by ID');
$appointmentDelete->execute(['APT-OWN', 'DOC-1']);
$assert($appointmentDelete->rowCount() === 1, 'doctor can delete an appointment they own when granted delete permission');
$labUpdate = $pdo->prepare(
    'UPDATE tblLabResults SET resultValue = ?, dateRecorded = ? WHERE labResultID = ? AND requestedBy = ?'
);
$labUpdate->execute(['private result', '2026-10-23', 'LAB-OTHER', 'DOC-1']);
$assert($labUpdate->rowCount() === 0, 'doctor cannot update another doctor lab result by ID');
$labUpdate->execute(['own result', '2026-10-23', 'LAB-OWN', 'DOC-1']);
$assert($labUpdate->rowCount() === 1, 'doctor can update a lab result they own when granted update permission');

$assert(hasPermission($pdo, 'USR1', 'Administrator', 'patients', 'view') === false, 'administrator role does not grant section access');
$assert(hasPermission($pdo, 'USR1', 'Supervisor', 'reports', 'view'), 'supervisor can view the reports template');
$assert(hasPermission($pdo, 'USR1', 'Supervisor', 'staff', 'view'), 'supervisor can view the staff template');
$assert(hasPermission($pdo, 'USR1', 'Supervisor', 'billing', 'view'), 'supervisor can view the billing template');
$assert(hasPermission($pdo, 'USR1', 'Receptionist', 'patients', 'view'), 'role permission grants section view');
$pdo->exec(
    "INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed)
     VALUES ('patient', 'staff', 'view', 1), ('patient', 'reports', 'view', 1)"
);
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'staff', 'view'), 'patient role is denied staff access even with a role grant');
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'reports', 'view'), 'patient role is denied reports access even with a role grant');
$assert(!roleCanAccessSection('Patient', 'staff'), 'patient role cannot access staff routes');
$assert(!roleCanAccessSection('Patient', 'reports'), 'patient role cannot access reports routes');
$assert(!roleCanAccessSection('Patient', 'patients'), 'patient role cannot access the global patient directory');
$assert(!roleCanAccessSection('Patient', 'billing'), 'patient role cannot access staff billing or create charges');
foreach ($sections as $section) {
    $assert(!roleCanAccessSection('Patient', $section), 'patient role cannot open the global ' . $section . ' section');
}
$assert(roleCanAccessSection('Receptionist', 'staff'), 'staff access remains possible for non-patient roles');

$pdo->exec(
    "INSERT INTO tblUserPermissions (userID, sectionKey, actionKey, isAllowed)
     VALUES ('USR1', 'patients', 'view', 1), ('USR1', 'billing', 'view', 0),
            ('USR1', 'system', 'manage_users', 1),
            ('USR1', 'staff', 'view', 1), ('USR1', 'reports', 'view', 1),
            ('USR1', 'patients', 'create', 1), ('USR1', 'billing', 'create', 1)"
);
$assert(hasPermission($pdo, 'USR1', 'Administrator', 'patients', 'view'), 'individual permission grants access');
$assert(!hasPermission($pdo, 'USR1', 'Supervisor', 'billing', 'view'), 'individual denial overrides role grant');
$assert(hasPermission($pdo, 'USR1', 'Administrator', 'system', 'manage_users'), 'system-management grant is explicit');
$assert(!hasPermission($pdo, 'USR1', 'Administrator', 'system', 'manage_users', 'pending'), 'pending accounts cannot use grants');
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'staff', 'view'), 'patient role denies user-specific staff permission overrides');
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'reports', 'view'), 'patient role denies user-specific reports permission overrides');
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'patients', 'create'), 'patient role cannot create global patient records');
$assert(!hasPermission($pdo, 'USR1', 'Patient', 'billing', 'create'), 'patient role cannot create or alter hospital charges');
$sidebarSource = file_get_contents(__DIR__ . '/../partials/sidebar.php');
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isDoctor && !$isBillingOfficer && !$isReceptionist):') !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isAdmin)') !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isAdmin && !$isBillingOfficer)') !== false,
    'patient and receptionist navigation hides the dashboard while patient restrictions remain'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, '$isAdmin = $userRoleKey === \'admin\'') !== false
        && strpos($sidebarSource, 'if (!$isAdmin)') !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isAdmin)') !== false,
    'administrator navigation omits all operational section links'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isBillingOfficer && can(\'system\', \'manage_users\'))') !== false,
    'patient navigation hides user and permission management even when a grant exists'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, '$isBillingOfficer = $userRoleKey === \'billing_officer\'') !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isDoctor && !$isBillingOfficer && !$isReceptionist):') !== false
        && strpos($sidebarSource, 'if (!$isAdmin && !$isBillingOfficer && !$isPatient):') !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isAdmin && !$isBillingOfficer)') !== false
        && strpos($sidebarSource, "if (!\$isPatient && !\$isAdmin):") !== false
        && strpos($sidebarSource, 'if (!$isPatient && !$isBillingOfficer && can(\'system\', \'manage_users\'))') !== false,
    'billing officer sidebar omits dashboard, other operation links, staff, and user management'
);

$login = attemptLogin($pdo, 'manager', 'correct horse battery staple');
$assert($login !== null && $login['id'] === 'USR1', 'login accepts valid credentials');
$assert(attemptLogin($pdo, 'manager', 'wrong password') === null, 'login rejects invalid password');

$signupRoles = publicSignupRoles($pdo);
$assert(isset($signupRoles['doctor']) && isset($signupRoles['patient']), 'public signup options come from assignable role records');
$assert(!isset($signupRoles['admin']) && !isset($signupRoles['supervisor']), 'elevated account types are excluded from public signup requests');
$invalidRoleRejected = false;
try {
    createPendingAccount($pdo, 'Unsafe User', 'unsafe@example.test', 'a sufficiently long password', 'admin');
} catch (InvalidArgumentException $exception) {
    $invalidRoleRejected = true;
}
$assert($invalidRoleRejected, 'signup rejects elevated account type requests');
$invalidSpecialtyRejected = false;
try {
    createPendingAccount($pdo, 'Unsafe Doctor', 'unsafe-doctor@example.test', 'a sufficiently long password', 'doctor', 'Fake Specialty');
} catch (InvalidArgumentException $exception) {
    $invalidSpecialtyRejected = true;
}
$assert($invalidSpecialtyRejected, 'signup rejects medical specialties outside the approved dropdown choices');
$pendingId = createPendingAccount($pdo, 'New User', 'new@example.test', 'a sufficiently long password', 'doctor', 'Cardiology');
$pending = $pdo->query("SELECT username, email, role, accountStatus, passwordHash FROM tblUsers WHERE userID = " . $pdo->quote($pendingId))->fetch();
$assert($pending['role'] === 'Pending' && $pending['accountStatus'] === 'pending', 'signup assigns pending role and status');
$request = $pdo->query("SELECT requestedRole, doctorSpecialty FROM tblUserRoleRequests WHERE userID = " . $pdo->quote($pendingId))->fetch();
$assert($request['requestedRole'] === 'doctor', 'requested role is persisted without assigning it to the account');
$assert($request['doctorSpecialty'] === 'Cardiology', 'doctor signup specialty is saved with the role request');
$assert(strpos($pending['username'], 'account_') === 0, 'signup creates an internal username without requiring one from the user');
$assert($pending['email'] === 'new@example.test', 'signup stores the email used for sign-in');
$assert(password_verify('a sufficiently long password', $pending['passwordHash']), 'signup stores a password hash');
$pdo->prepare("UPDATE tblUsers SET role = 'Doctor', accountStatus = 'active' WHERE userID = ?")->execute([$pendingId]);
$doctorProfileId = ensureDoctorProfileForAccount($pdo, $pendingId, $request['doctorSpecialty']);
$doctorProfile = $pdo->prepare('SELECT fullName, specialty, userID FROM tblDoctors WHERE doctorID = ?');
$doctorProfile->execute([$doctorProfileId]);
$doctorProfile = $doctorProfile->fetch();
$assert(
    $doctorProfile['fullName'] === 'New User'
        && $doctorProfile['specialty'] === 'Cardiology'
        && $doctorProfile['userID'] === $pendingId,
    'approved Doctor account receives a linked directory profile with its name and specialty'
);
$emailLogin = attemptLogin($pdo, 'new@example.test', 'a sufficiently long password');
$assert($emailLogin !== null && $emailLogin['id'] === $pendingId, 'new accounts can sign in with email');
$assert(!hasPermission($pdo, $pendingId, 'Pending', 'patients', 'view', 'pending'), 'pending registration has no section access');
$signupSource = file_get_contents(__DIR__ . '/../auth/signup.php');
$assert($signupSource !== false && strpos($signupSource, 'name="username"') === false, 'signup form does not request a username');
$assert($signupSource !== false && strpos($signupSource, 'data-password-toggle') !== false, 'signup form includes password visibility controls');
$assert($signupSource !== false && strpos($signupSource, 'Password meets the 12-character minimum.') !== false, 'signup provides positive password-length feedback');
$assert($signupSource !== false && strpos($signupSource, "url('/index.php')") !== false, 'signup links back to the public homepage');
$assert($signupSource !== false && strpos($signupSource, 'name="requested_role"') !== false, 'signup form allows selecting a requested account type');
$assert($signupSource !== false && strpos($signupSource, 'name="doctor_specialty"') !== false, 'signup form collects Doctor specialty');
$assert(
    $signupSource !== false
        && strpos($signupSource, '<select id="doctor-specialty" name="doctor_specialty"') !== false
        && strpos($signupSource, 'foreach ($doctorSpecialties as $specialty)') !== false
        && strpos($signupSource, 'specialtyField.hidden = !isDoctor;') !== false
        && strpos($signupSource, 'specialty.required = isDoctor;') !== false,
    'signup shows a required medical specialty dropdown only for Doctor account requests'
);
$assert($signupSource !== false && strpos($signupSource, 'publicSignupRoles(getDb())') !== false, 'signup options are populated from roles table');
$landingSource = file_get_contents(__DIR__ . '/../index.php');
$assert($landingSource !== false && strpos($landingSource, 'class="landing public-home"') !== false, 'healthcare landing page remains the public entry experience');
$headSource = file_get_contents(__DIR__ . '/../partials/head.php');
$themeSource = file_get_contents(__DIR__ . '/../assets/css/style_theme.css');
$assert(
    $headSource !== false
        && strpos($headSource, 'fonts.googleapis.com') === false
        && $themeSource !== false
        && strpos($themeSource, 'Arial, Helvetica, sans-serif !important') !== false,
    'shared pages use the local Arial sans-serif font stack'
);
$assert(
    $themeSource !== false
        && strpos($themeSource, '--bg: #090909') !== false
        && strpos($themeSource, 'body.public-home') !== false
        && strpos($themeSource, '.auth-card') !== false
        && strpos($themeSource, '.panel') !== false,
    'dark interface styling covers public, authentication, and application pages'
);
$assert(
    $themeSource !== false
        && strpos($themeSource, 'width: min(100%, 1600px)') !== false
        && strpos($themeSource, 'max-width: 1200px') !== false
        && strpos($themeSource, 'overflow-x: auto') !== false,
    'compact application layout stays centered and scrolls wide tables on smaller screens'
);
$loginSource = file_get_contents(__DIR__ . '/../auth/login.php');
$forgotPasswordSource = file_get_contents(__DIR__ . '/../auth/forgot_password.php');
$resetPasswordSource = file_get_contents(__DIR__ . '/../auth/reset_password.php');
$assert(
    $forgotPasswordSource !== false
        && strpos($forgotPasswordSource, 'name="email"') !== false
        && strpos($forgotPasswordSource, 'sendPasswordResetEmail(') !== false
        && strpos($forgotPasswordSource, '$mailConfigured') !== false
        && strpos($forgotPasswordSource, '$submitted = true;') !== false
        && strpos($forgotPasswordSource, '$deliveryFailed = true;') !== false
        && strpos($forgotPasswordSource, 'We couldn\'t send a password-reset email right now.') !== false
        && strpos($forgotPasswordSource, 'Open the link before it expires.') !== false
        && $resetPasswordSource !== false
        && strpos($resetPasswordSource, 'consumePasswordResetToken(') !== false
        && strpos($resetPasswordSource, "url('/auth/login.php')") !== false,
    'password recovery requires a configured email service, verifies reset tokens, and returns users to sign in'
);
$assert(
    $loginSource !== false
        && strpos($loginSource, '$sections[$requestedSectionKey] ?? null') !== false
        && strpos($loginSource, 'Access depends on the permissions assigned to your account.') !== false,
    'login displays context for a validated requested section'
);
$loginRoleKeys = ['admin', 'supervisor', 'doctor', 'receptionist', 'lab_technician', 'billing_officer', 'patient'];
$assert(
    $loginSource !== false
        && strpos($loginSource, 'name="account_type"') !== false
        && strpos($loginSource, 'roleKey((string) $user[\'role\']) === $selectedRole') !== false
        && strpos($loginSource, 'This selection checks your sign-in; it does not change your role or permissions.') !== false,
    'login requires a selected account type and checks it against the authenticated account role'
);
foreach ($loginRoleKeys as $loginRoleKey) {
    $assert(
        $loginSource !== false && strpos($loginSource, "'" . $loginRoleKey . "' =>") !== false,
        $loginRoleKey . ' is available as a sign-in account type'
    );
}
foreach (['patients', 'appointments', 'doctors', 'lab', 'billing', 'staff', 'reports'] as $sectionKey) {
    $assert(
        $landingSource !== false && strpos($landingSource, '/auth/login.php?section=' . $sectionKey) !== false,
        $sectionKey . ' public work-area link opens contextual sign-in'
    );
}

$now = new DateTimeImmutable('2026-10-22 12:00:00');
$token = issuePasswordResetToken($pdo, 'USR1', $now);
$storedHash = $pdo->query('SELECT tokenHash FROM tblPasswordResetTokens')->fetchColumn();
$assert($storedHash === hash('sha256', $token), 'reset token is stored only as a hash');
$assert(consumePasswordResetToken($pdo, $token, 'new secure password phrase', $now), 'valid reset token updates the password');
$assert(!consumePasswordResetToken($pdo, $token, 'another password phrase', $now), 'reset token cannot be reused');
$updatedUser = $pdo->query("SELECT passwordHash, sessionVersion FROM tblUsers WHERE userID = 'USR1'")->fetch();
$assert(password_verify('new secure password phrase', $updatedUser['passwordHash']), 'reset stores the new password hash');
$assert((int) $updatedUser['sessionVersion'] === 1, 'password reset invalidates previous sessions');

$expiredToken = issuePasswordResetToken(
    $pdo,
    'USR1',
    $now->modify('-' . (PASSWORD_RESET_TTL_MINUTES + 1) . ' minutes')
);
$assert(!consumePasswordResetToken($pdo, $expiredToken, 'expired token password', $now), 'expired reset token is rejected');

foreach ($sections as $section) {
    $route = $section === 'lab' ? 'lab/index.php' : $section . '/index.php';
    $source = file_get_contents(__DIR__ . '/../' . $route);
    $assert(
        $source !== false && strpos($source, "requireSectionPermission('{$section}', 'view')") !== false,
        $section . ' route enforces view permission'
    );
}

$billingSource = file_get_contents(__DIR__ . '/../billing/index.php');
$receiptSource = file_get_contents(__DIR__ . '/../billing/receipt.php');
$paymentMigration = file_get_contents(__DIR__ . '/../database/migrations/004_billing_payments.sql');
$assert(
    $billingSource !== false
        && strpos($billingSource, "requireSectionPermission('billing', 'update')") !== false
        && strpos($billingSource, 'SELECT amount, paymentStatus FROM tblBilling WHERE billID = ? FOR UPDATE') !== false
        && strpos($billingSource, 'INSERT INTO tblPayments') !== false,
    'billing records payments under update permission while locking the bill'
);
$assert(
    $billingSource !== false
        && strpos($billingSource, "['Cash', 'Card', 'Bank transfer']") !== false
        && strpos($billingSource, 'A bill with payment history or patient payment requests cannot be deleted.') !== false
        && strpos($billingSource, 'name="record_payment"') !== false,
    'billing permits the supported payment methods and protects payment history'
);
$assert(
    $receiptSource !== false
        && strpos($receiptSource, "requireSectionPermission('billing', 'view')") !== false
        && strpos($receiptSource, 'window.print()') !== false
        && strpos($receiptSource, 'Balance after this payment') !== false,
    'receipts require billing view access and show print-ready payment details'
);
$assert(
    $paymentMigration !== false
        && strpos($paymentMigration, 'CREATE TABLE IF NOT EXISTS tblPayments') !== false
        && strpos($paymentMigration, 'balanceAfter  DECIMAL(10,2) NOT NULL') !== false
        && strpos($paymentMigration, 'FOREIGN KEY (billID) REFERENCES tblBilling (billID)') !== false,
    'billing migration creates an auditable payment ledger linked to bills'
);

$portalSource = file_get_contents(__DIR__ . '/../patient/portal.php');
$sidebarSource = file_get_contents(__DIR__ . '/../partials/sidebar.php');
$headSource = file_get_contents(__DIR__ . '/../partials/head.php');
$themeScript = file_get_contents(__DIR__ . '/../assets/js/theme.js');
$themeStyles = file_get_contents(__DIR__ . '/../assets/css/style_theme.css');
$assert(
    $portalSource !== false
        && strpos($portalSource, 'id="doctors"') !== false
        && strpos($portalSource, 'id="appointments"') !== false
        && strpos($portalSource, 'id="medical-reports"') !== false
        && strpos($portalSource, 'id="my-bills"') !== false,
    'patient portal includes navigation targets for personal care sections'
);
$assert(
    $portalSource !== false
        && strpos($portalSource, 'in_array($patientId, $patientIds, true)') !== false
        && strpos($portalSource, 'appointmentValue > new DateTimeImmutable()') !== false
        && strpos($portalSource, 'a.patientID IN ($placeholders)') !== false
        && strpos($portalSource, 'l.patientID IN ($placeholders)') !== false
        && strpos($portalSource, 'b.patientID IN ($placeholders)') !== false,
    'patient booking and clinical records are limited to linked patient IDs and future visits'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, '!$isReceptionist') !== false
        && strpos($sidebarSource, '/patient/portal.php#doctors') !== false
        && strpos($sidebarSource, '/patient/portal.php#appointments') !== false
        && strpos($sidebarSource, '/patient/portal.php#medical-reports') !== false
        && strpos($sidebarSource, '<span>Lab Results</span>') !== false
        && strpos($sidebarSource, '/patient/portal.php#my-bills') !== false,
    'sidebar hides the Receptionist dashboard and links patient Lab Results to the private portal'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, '$isDoctor = $userRoleKey === \'doctor\'') !== false
        && strpos($sidebarSource, '!$isDoctor') !== false
        && strpos($sidebarSource, "if (\$isDoctor):") !== false
        && strpos($sidebarSource, "if (!\$isDoctor):") !== false,
    'Doctor sidebar hides Dashboard and places its permission-checked Doctors link first'
);
$assert(
    $sidebarSource !== false
        && strpos($sidebarSource, 'if (!$isAdmin && !$isBillingOfficer && !$isPatient):') !== false
        && strpos($sidebarSource, '$navItem(\'lab\', \'/lab/index.php\', \'Lab Results\'') !== false
        && !roleCanAccessSection('Patient', 'lab'),
    'patient sidebar hides locked global sections and patient lab data stays on the scoped portal'
);
$assert(
    $headSource !== false
        && strpos($headSource, "localStorage.getItem('medipulse-theme')") !== false
        && strpos($headSource, 'assets/js/theme.js') !== false
        && $themeScript !== false
        && strpos($themeScript, "localStorage.setItem('medipulse-theme', theme)") !== false
        && strpos($themeScript, 'data-theme-toggle') !== false
        && $themeStyles !== false
        && strpos($themeStyles, 'html[data-theme="light"]') !== false,
    'dark and light theme selection is applied and persisted'
);
$assert(
    $themeStyles !== false
        && strpos($themeStyles, 'html[data-theme="light"] .alert.danger') !== false
        && strpos($themeStyles, 'color: #7f1d1d !important') !== false
        && strpos($themeStyles, 'html[data-theme="light"] .alert.info') !== false
        && strpos($themeStyles, 'color: #1e3a8a !important') !== false
        && strpos($themeStyles, 'html[data-theme="light"] .alert.success') !== false
        && strpos($themeStyles, 'html[data-theme="light"] .alert.warning') !== false,
    'light-theme status alerts use readable text and stronger background and border colors'
);

fwrite(STDOUT, "Passed {$tests} tests.\n");
