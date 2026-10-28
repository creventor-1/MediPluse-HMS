<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireUserManagementPermission();
$pdo = getDb();
$sections = appSections();
$actions = sectionActions();
$roles = $pdo->query(
    "SELECT roleKey, roleName, description FROM tblRoles
     WHERE isAssignable = 1 AND roleKey <> 'pending' ORDER BY roleName"
)->fetchAll();
$doctorSpecialtyColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$doctorSpecialtyColumn->execute(['tblUserRoleRequests', 'doctorSpecialty']);
$hasDoctorSpecialtyColumn = (int) $doctorSpecialtyColumn->fetchColumn() > 0;
$doctorSpecialtySelection = $hasDoctorSpecialtyColumn ? 'r.doctorSpecialty' : 'NULL AS doctorSpecialty';
$users = $pdo->query(
    'SELECT u.userID, u.username, u.email, u.displayName, u.role, u.accountStatus,
            r.requestedRole, ' . $doctorSpecialtySelection . ', r.reviewedAt, r.requestedAt
     FROM tblUsers u
     LEFT JOIN tblUserRoleRequests r ON r.userID = u.userID
     ORDER BY u.username'
)->fetchAll();

$selectedUserId = trim($_GET['user'] ?? $_POST['user_id'] ?? '');
$selectedUser = null;
foreach ($users as $user) {
    if ($user['userID'] === $selectedUserId) {
        $selectedUser = $user;
        break;
    }
}
$message = '';
$messageType = '';
$emailInputValue = null;

$activeManagerCount = static function () use ($pdo): int {
    $activeUsers = $pdo->query(
        "SELECT userID, role FROM tblUsers WHERE accountStatus = 'active'"
    )->fetchAll();
    $count = 0;
    foreach ($activeUsers as $account) {
        if (hasPermission($pdo, $account['userID'], $account['role'], 'system', 'manage_users')) {
            $count++;
        }
    }

    return $count;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['save_role_permissions'])) {
        $roleKey = trim($_POST['role_key'] ?? '');
        $knownRoleKeys = array_column($roles, 'roleKey');
        if (!in_array($roleKey, $knownRoleKeys, true)) {
            denyAccess('Choose a valid role.');
        }
        if ($roleKey === currentRoleKey()) {
            denyAccess('You cannot change the permission template for your own role.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM tblRolePermissions WHERE roleKey = ?')->execute([$roleKey]);
            $stmt = $pdo->prepare(
                'INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed) VALUES (?, ?, ?, 1)'
            );
            foreach ($sections as $sectionKey => $_section) {
                foreach ($actions as $action) {
                    if (isset($_POST['role_permissions'][$sectionKey][$action])) {
                        $stmt->execute([$roleKey, $sectionKey, $action]);
                    }
                }
            }
            recordAuditEvent($pdo, 'system', 'role_permissions_updated', $roleKey);
            $pdo->commit();
            $message = 'Role permission template saved successfully.';
            $messageType = 'success';
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    if (isset($_POST['save_user_permissions'])) {
        $targetUserId = trim($_POST['user_id'] ?? '');
        if ($targetUserId === '' || $targetUserId === (currentUser()['id'] ?? null)) {
            denyAccess('Select another account. You cannot change your own role or permissions here.');
        }

        $target = $pdo->prepare('SELECT userID FROM tblUsers WHERE userID = ?');
        $target->execute([$targetUserId]);
        if (!$target->fetchColumn()) {
            denyAccess('That account was not found.');
        }

        $roleKey = trim($_POST['role_key'] ?? '');
        $roleNames = array_column($roles, 'roleName', 'roleKey');
        if (!isset($roleNames[$roleKey])) {
            denyAccess('Choose a valid role.');
        }
        $accountStatus = trim($_POST['account_status'] ?? '');
        if (!in_array($accountStatus, ['active', 'pending', 'disabled'], true)) {
            denyAccess('Choose a valid account status.');
        }
        $email = strtolower(trim($_POST['email'] ?? ''));
        $emailInputValue = $email;
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) {
            denyAccess('Enter a valid email address or leave it blank.');
        }
        $emailOwner = $pdo->prepare(
            'SELECT 1 FROM tblUsers WHERE email = ? AND userID <> ? LIMIT 1'
        );
        $emailOwner->execute([$email !== '' ? $email : null, $targetUserId]);
        $emailAlreadyUsed = $email !== '' && (bool) $emailOwner->fetchColumn();
        $doctorSpecialty = trim($_POST['doctor_specialty'] ?? '');
        $doctorApprovalBlocked = $roleKey === 'doctor' && $accountStatus === 'active' && !$hasDoctorSpecialtyColumn;
        if (!$doctorApprovalBlocked && $roleKey === 'doctor' && $accountStatus === 'active'
            && ($doctorSpecialty === '' || mb_strlen($doctorSpecialty) > 50)) {
            denyAccess('Enter the Doctor specialty (1 to 50 characters) before activating this account.');
        }

        if ($emailAlreadyUsed) {
            $message = 'That email address is already assigned to another account. Enter a different email address.';
            $messageType = 'danger';
        } elseif ($doctorApprovalBlocked) {
            $message = 'Doctor approval is temporarily unavailable because the doctor-specialty database migration has not been applied. Run database/migrations/009_doctor_signup_specialty.sql in phpMyAdmin, then reload this page.';
            $messageType = 'danger';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE tblUsers SET role = ?, accountStatus = ?, email = ? WHERE userID = ?')
                    ->execute([$roleNames[$roleKey], $accountStatus, $email !== '' ? $email : null, $targetUserId]);
                $pdo->prepare(
                    'UPDATE tblUserRoleRequests
                     SET reviewedBy = ?, reviewedAt = CURRENT_TIMESTAMP
                     WHERE userID = ? AND reviewedAt IS NULL'
                )->execute([currentUser()['id'], $targetUserId]);
                if ($roleKey === 'doctor' && $hasDoctorSpecialtyColumn) {
                    $pdo->prepare(
                        'UPDATE tblUserRoleRequests SET doctorSpecialty = ? WHERE userID = ?'
                    )->execute([$doctorSpecialty !== '' ? $doctorSpecialty : null, $targetUserId]);
                }
                if ($roleKey === 'doctor' && $accountStatus === 'active') {
                    ensureDoctorProfileForAccount($pdo, $targetUserId, $doctorSpecialty);
                }

                $pdo->prepare('DELETE FROM tblUserPermissions WHERE userID = ?')->execute([$targetUserId]);
                $permissionStmt = $pdo->prepare(
                    'INSERT INTO tblUserPermissions (userID, sectionKey, actionKey, isAllowed, updatedBy)
                     VALUES (?, ?, ?, ?, ?)'
                );
                foreach ($sections as $sectionKey => $_section) {
                    foreach ($actions as $action) {
                        $choice = $_POST['permissions'][$sectionKey][$action] ?? 'default';
                        if (!in_array($choice, ['default', 'allow', 'deny'], true)) {
                            denyAccess('An invalid permission value was submitted.');
                        }
                        if ($choice !== 'default') {
                            $permissionStmt->execute([
                                $targetUserId,
                                $sectionKey,
                                $action,
                                $choice === 'allow' ? 1 : 0,
                                currentUser()['id'],
                            ]);
                        }
                    }
                }

                if (isset($_POST['manage_users'])) {
                    $permissionStmt->execute([$targetUserId, 'system', 'manage_users', 1, currentUser()['id']]);
                }

                if ($activeManagerCount() < 1) {
                    throw new RuntimeException('At least one active user manager must remain.');
                }

                recordAuditEvent($pdo, 'system', 'user_permissions_updated', $targetUserId);
                $pdo->commit();
                $message = 'Account role, status, and permissions saved successfully.';
                if ($roleKey === 'doctor' && $accountStatus === 'active') {
                    $message .= ' The Doctor profile is linked and active in the Doctors directory.';
                }
                $messageType = 'success';
                $selectedUserId = $targetUserId;
            } catch (Throwable $error) {
                $pdo->rollBack();
                if ($error instanceof RuntimeException && $error->getMessage() === 'At least one active user manager must remain.') {
                    $message = $error->getMessage();
                    $messageType = 'danger';
                } elseif ($error instanceof PDOException
                    && isset($error->errorInfo[1])
                    && (int) $error->errorInfo[1] === 1062) {
                    $message = 'That email address is already assigned to another account. Enter a different email address.';
                    $messageType = 'danger';
                } else {
                    throw $error;
                }
            }
        }

        $users = $pdo->query(
            'SELECT u.userID, u.username, u.email, u.displayName, u.role, u.accountStatus,
                    r.requestedRole, ' . $doctorSpecialtySelection . ', r.reviewedAt, r.requestedAt
             FROM tblUsers u
             LEFT JOIN tblUserRoleRequests r ON r.userID = u.userID
             ORDER BY u.username'
        )->fetchAll();
        foreach ($users as $user) {
            if ($user['userID'] === $selectedUserId) {
                $selectedUser = $user;
                break;
            }
        }
    }

    if (isset($_POST['link_patient_record']) || isset($_POST['revoke_patient_record'])) {
        if (!can('patients', 'view')) {
            denyAccess('Patient-view permission is required to link patient records to accounts.');
        }
        $targetUserId = trim($_POST['user_id'] ?? '');
        $patientId = trim($_POST['patient_id'] ?? $_POST['revoke_patient_record'] ?? '');
        if ($targetUserId === '' || $targetUserId === (currentUser()['id'] ?? null) || $patientId === '') {
            denyAccess('Select another patient account and a patient record.');
        }

        $targetStmt = $pdo->prepare('SELECT role, accountStatus FROM tblUsers WHERE userID = ?');
        $targetStmt->execute([$targetUserId]);
        $targetAccount = $targetStmt->fetch();
        if (!$targetAccount || roleKey((string) $targetAccount['role']) !== 'patient'
            || $targetAccount['accountStatus'] !== 'active') {
            denyAccess('Only an active patient account can be linked to a patient record.');
        }

        if (isset($_POST['link_patient_record'])) {
            $pdo->beginTransaction();
            try {
                $userLock = $pdo->prepare('SELECT userID FROM tblUsers WHERE userID = ? FOR UPDATE');
                $userLock->execute([$targetUserId]);
                $patientLock = $pdo->prepare('SELECT patientID FROM tblPatients WHERE patientID = ? FOR UPDATE');
                $patientLock->execute([$patientId]);
                if (!$userLock->fetchColumn() || !$patientLock->fetchColumn()) {
                    throw new RuntimeException('The selected account or patient record could not be found.');
                }
                $existingActiveUserLink = $pdo->prepare(
                    "SELECT patientID FROM tblPatientAccounts
                     WHERE userID = ? AND accountStatus = 'active'
                     FOR UPDATE"
                );
                $existingActiveUserLink->execute([$targetUserId]);
                if ($existingActiveUserLink->fetchColumn() !== false) {
                    throw new RuntimeException('This patient account is already linked to an active patient record.');
                }
                $existingActivePatientLink = $pdo->prepare(
                    "SELECT userID FROM tblPatientAccounts
                     WHERE patientID = ? AND accountStatus = 'active'
                     FOR UPDATE"
                );
                $existingActivePatientLink->execute([$patientId]);
                if ($existingActivePatientLink->fetchColumn() !== false) {
                    throw new RuntimeException('That patient record is already linked to another active patient account.');
                }

                $link = $pdo->prepare(
                    "INSERT INTO tblPatientAccounts (userID, patientID, accountStatus, approvedBy, approvedAt)
                     VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP)
                     ON DUPLICATE KEY UPDATE accountStatus = 'active', approvedBy = VALUES(approvedBy), approvedAt = CURRENT_TIMESTAMP"
                );
                $link->execute([$targetUserId, $patientId, currentUser()['id']]);
                recordAuditEvent($pdo, 'system', 'patient_account_linked', $targetUserId . ':' . $patientId);
                $pdo->commit();
                $message = 'Patient record linked to account.';
                $messageType = 'success';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($error instanceof RuntimeException) {
                    $message = $error->getMessage();
                    $messageType = 'danger';
                } else {
                    throw $error;
                }
            }
        } elseif (isset($_POST['revoke_patient_record'])) {
            $pdo->beginTransaction();
            try {
                $userLock = $pdo->prepare('SELECT userID FROM tblUsers WHERE userID = ? FOR UPDATE');
                $userLock->execute([$targetUserId]);
                $patientLock = $pdo->prepare('SELECT patientID FROM tblPatients WHERE patientID = ? FOR UPDATE');
                $patientLock->execute([$patientId]);
                if (!$userLock->fetchColumn() || !$patientLock->fetchColumn()) {
                    throw new RuntimeException('The selected account or patient record could not be found.');
                }
                $revoke = $pdo->prepare(
                    "UPDATE tblPatientAccounts SET accountStatus = 'revoked', approvedBy = ?, approvedAt = CURRENT_TIMESTAMP
                     WHERE userID = ? AND patientID = ? AND accountStatus = 'active'"
                );
                $revoke->execute([currentUser()['id'], $targetUserId, $patientId]);
                if ($revoke->rowCount() !== 1) {
                    throw new RuntimeException('That active patient link could not be found.');
                }
                recordAuditEvent($pdo, 'system', 'patient_account_link_revoked', $targetUserId . ':' . $patientId);
                $pdo->commit();
                $message = 'Patient account link revoked.';
                $messageType = 'success';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($error instanceof RuntimeException) {
                    $message = $error->getMessage();
                    $messageType = 'danger';
                } else {
                    throw $error;
                }
            }
        }
        $selectedUserId = $targetUserId;
    }
}

// Active Tab navigation
$activeTab = trim($_GET['tab'] ?? '');
if ($activeTab === '') {
    $activeTab = (isset($_GET['role']) && !isset($_GET['user'])) ? 'roles' : 'users';
}

$selectedRoleKey = trim($_GET['role'] ?? $_POST['role_key'] ?? 'supervisor');
if (!in_array($selectedRoleKey, array_column($roles, 'roleKey'), true)) {
    $selectedRoleKey = 'supervisor';
}
$rolePermissionRows = $pdo->prepare(
    'SELECT sectionKey, actionKey FROM tblRolePermissions WHERE roleKey = ? AND isAllowed = 1'
);
$rolePermissionRows->execute([$selectedRoleKey]);
$rolePermissions = [];
foreach ($rolePermissionRows->fetchAll() as $permission) {
    $rolePermissions[$permission['sectionKey']][$permission['actionKey']] = true;
}

$userPermissionRows = [];
if ($selectedUser) {
    $userPermissionsStmt = $pdo->prepare(
        'SELECT sectionKey, actionKey, isAllowed FROM tblUserPermissions WHERE userID = ?'
    );
    $userPermissionsStmt->execute([$selectedUserId]);
    foreach ($userPermissionsStmt->fetchAll() as $permission) {
        $userPermissionRows[$permission['sectionKey']][$permission['actionKey']] = (int) $permission['isAllowed'];
    }
}
$roleKeyByName = [];
$roleNamesByKey = [];
foreach ($roles as $role) {
    $roleKeyByName[$role['roleName']] = $role['roleKey'];
    $roleNamesByKey[$role['roleKey']] = $role['roleName'];
}
$selectedUserRoleKey = $selectedUser ? ($roleKeyByName[$selectedUser['role']] ?? 'pending') : '';
$selectedDoctorSpecialty = $selectedUser['doctorSpecialty'] ?? '';
if ($selectedUser) {
    $doctorSpecialtyStmt = $pdo->prepare('SELECT specialty FROM tblDoctors WHERE userID = ?');
    $doctorSpecialtyStmt->execute([$selectedUserId]);
    $linkedSpecialty = $doctorSpecialtyStmt->fetchColumn();
    if ($linkedSpecialty !== false && $linkedSpecialty !== null && $linkedSpecialty !== '') {
        $selectedDoctorSpecialty = $linkedSpecialty;
    }
}

// Fetch baseline permissions for the selected user's assigned role
$userRoleDefaultPermissions = [];
if ($selectedUserRoleKey !== '' && $selectedUserRoleKey !== 'pending') {
    $userRolePermStmt = $pdo->prepare(
        'SELECT sectionKey, actionKey FROM tblRolePermissions WHERE roleKey = ? AND isAllowed = 1'
    );
    $userRolePermStmt->execute([$selectedUserRoleKey]);
    foreach ($userRolePermStmt->fetchAll() as $perm) {
        $userRoleDefaultPermissions[$perm['sectionKey']][$perm['actionKey']] = true;
    }
}

// Identify pending accounts
$pendingAccounts = [];
foreach ($users as $u) {
    if ($u['accountStatus'] === 'pending' || roleKey((string) $u['role']) === 'pending') {
        $pendingAccounts[] = $u;
    }
}

$canLinkPatientRecords = can('patients', 'view');
$activePatientLinks = [];
$availablePatientRecords = [];
if ($selectedUser && $selectedUserRoleKey === 'patient' && $canLinkPatientRecords) {
    $activeLinksStmt = $pdo->prepare(
        "SELECT pa.patientID, p.fullName, pa.approvedAt
         FROM tblPatientAccounts pa
         JOIN tblPatients p ON p.patientID = pa.patientID
         WHERE pa.userID = ? AND pa.accountStatus = 'active'
         ORDER BY p.fullName"
    );
    $activeLinksStmt->execute([$selectedUserId]);
    $activePatientLinks = $activeLinksStmt->fetchAll();
    $availablePatientRecords = $pdo->query(
        'SELECT patientID, fullName, dateOfBirth FROM tblPatients ORDER BY fullName'
    )->fetchAll();
}
$requestedRoleName = $selectedUser && isset($selectedUser['requestedRole'])
    ? ($roleNamesByKey[$selectedUser['requestedRole']] ?? '')
    : '';

$pageTitle = 'User Permissions';
require __DIR__ . '/../partials/head.php';
?>
<style>
  .admin-subnav {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 12px;
    width: fit-content;
  }
  .admin-subnav a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    color: #64748b;
    transition: all 0.15s ease;
  }
  .admin-subnav a.active {
    background: #ffffff;
    color: #0284c7;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  }
  .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: capitalize;
  }
  .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 999px;
  }
  .status-pill::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }
  .status-active { background: #dcfce7; color: #15803d; }
  .status-active::before { background: #22c55e; }
  .status-pending { background: #fef3c7; color: #b45309; }
  .status-pending::before { background: #f59e0b; }
  .status-disabled { background: #fee2e2; color: #b91c1c; }
  .status-disabled::before { background: #ef4444; }
  .role-pills-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 14px 0 20px;
  }
  .role-pill-btn {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
  }
  .role-pill-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
  }
  .role-pill-btn.active {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
  }
  .role-template-form {
    display: block;
    width: 100%;
    margin-top: 18px;
  }
  .role-template-panel {
    width: 100% !important;
    max-width: 100% !important;
    display: block !important;
    box-sizing: border-box;
    margin-bottom: 20px;
  }
  .role-template-panel .table-wrap {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .role-template-panel table {
    width: 100% !important;
    min-width: 640px;
    border-collapse: collapse;
  }
  @media (min-width: 901px) {
    .role-template-panel table {
      display: table !important;
    }
  }
  .role-template-panel th,
  .role-template-panel td {
    padding: 12px 16px;
  }
  .role-checkbox-custom {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #0284c7;
    vertical-align: middle;
  }
  .concept-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #0284c7;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
  }
  .concept-box h4 {
    margin: 0 0 4px;
    font-size: 0.95rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .concept-box p {
    margin: 0;
    font-size: 0.86rem;
    color: #475569;
    line-height: 1.5;
  }
  .override-select {
    font-size: 0.84rem;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    width: 100%;
  }
  .override-select.has-allow {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
    font-weight: 600;
  }
  .override-select.has-deny {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #991b1b;
    font-weight: 600;
  }
</style>
<body>
  <div class="layout">
    <?php $activeNav = 'permissions'; require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">System Administration</p>
          <h2>Roles &amp; Permissions Manager</h2>
        </div>
        <a class="button-link secondary" href="<?php echo e(url('/dashboard/index.php')); ?>"><?php echo icon('chevron-left', '', 16); ?> Back to dashboard</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>" role="status"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if (!$hasDoctorSpecialtyColumn): ?>
        <div class="alert info" role="status">
          Doctor specialty support is not set up in this database yet. Run <code>database/migrations/009_doctor_signup_specialty.sql</code> once in phpMyAdmin against <code>medipulse_hms</code>, then reload this page to approve Doctor accounts and create their profiles.
        </div>
      <?php endif; ?>

      <!-- Primary Navigation Tabs -->
      <nav class="admin-subnav" aria-label="Permissions Management Sections">
        <a href="?tab=users<?php echo $selectedUserId ? '&user=' . urlencode($selectedUserId) : ''; ?>" class="<?php echo $activeTab === 'users' ? 'active' : ''; ?>">
          <span><?php echo icon('users', '', 16); ?> User Accounts &amp; Individual Overrides</span>
          <?php if (count($pendingAccounts) > 0): ?>
            <span style="background: #ef4444; color: #fff; font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; font-weight: 700;">
              <?php echo count($pendingAccounts); ?> Pending
            </span>
          <?php endif; ?>
        </a>
        <a href="?tab=roles<?php echo $selectedRoleKey ? '&role=' . urlencode($selectedRoleKey) : ''; ?>" class="<?php echo $activeTab === 'roles' ? 'active' : ''; ?>">
          <span><?php echo icon('shield-check', '', 16); ?> Role Permission Templates (Baseline Defaults)</span>
        </a>
      </nav>

      <?php if ($activeTab === 'users'): ?>
        <!-- ========================================== -->
        <!-- TAB 1: USER ACCOUNTS & INDIVIDUAL OVERRIDES -->
        <!-- ========================================== -->

        <div class="concept-box">
          <h4><?php echo icon('info', '', 16); ?> How User Roles &amp; Overrides Work</h4>
          <p>
            Every user account belongs to a <strong>Role</strong> and automatically inherits that role’s default permissions.
            Use this screen to manage accounts, approve pending staff signups, and apply <strong>custom overrides</strong> (e.g. granting extra access or revoking standard access) for a specific employee.
          </p>
        </div>

        <!-- Pending Approval Alert Card (if any pending accounts exist) -->
        <?php if (count($pendingAccounts) > 0): ?>
          <section class="panel" style="border-left: 4px solid #f59e0b; background: #fffdf5; padding: 18px 20px; margin-bottom: 24px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="margin-bottom: 12px;">
              <h3 style="margin:0 0 4px; font-size:1.05rem; color:#92400e; display:flex; align-items:center; gap:8px;">
                <span><?php echo icon('alert-triangle', '', 18); ?></span> Staff Accounts Awaiting Review (<?php echo count($pendingAccounts); ?>)
              </h3>
              <p style="margin:0; font-size:0.86rem; color:#b45309;">
                The following staff accounts have submitted registration requests. Review their requested role and activate their account.
              </p>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
              <?php foreach ($pendingAccounts as $pu): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; padding:12px 16px; border-radius:8px; border:1px solid #fde68a;">
                  <div>
                    <strong><?php echo e($pu['displayName'] ?: $pu['username']); ?></strong>
                    <span style="color:#64748b; font-size:0.85rem; margin-left:8px;">(<?php echo e($pu['email'] ?: 'No email recorded'); ?>)</span>
                    <div style="font-size:0.84rem; color:#475569; margin-top:3px;">
                      Requested account type: <strong><?php echo e($roleNamesByKey[$pu['requestedRole']] ?? $pu['requestedRole'] ?? 'Unspecified'); ?></strong>
                      <?php if (!empty($pu['doctorSpecialty'])): ?>
                        · Specialty: <strong><?php echo e($pu['doctorSpecialty']); ?></strong>
                      <?php endif; ?>
                    </div>
                  </div>
                  <a href="?tab=users&user=<?php echo urlencode($pu['userID']); ?>#user-editor" class="button-link" style="padding:6px 14px; font-size:0.85rem; background:#0284c7; color:#fff; border-radius:6px; text-decoration:none;">
                    Review &amp; Approve →
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- User Directory Table Card -->
        <section class="form-card" style="margin-bottom: 28px;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
            <div>
              <h3 style="margin:0;">User Directory (<?php echo count($users); ?> accounts)</h3>
              <p class="hint" style="margin:3px 0 0;">Click "Manage" on any user to review their role, status, or set individual permission overrides.</p>
            </div>
            <input type="text" id="user-search-input" placeholder="🔍 Search by name, username, or role..." style="max-width: 300px; padding: 7px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.88rem;">
          </div>

          <div class="panel">
            <div class="table-wrap">
              <table id="user-directory-table">
                <thead>
                  <tr>
                    <th>User / Name</th>
                    <th>Email Address</th>
                    <th>Assigned Role</th>
                    <th>Account Status</th>
                    <th style="text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $account):
                    $rKey = roleKey((string) $account['role']);
                    $isSelected = $selectedUserId === $account['userID'];
                    $isPending = $account['accountStatus'] === 'pending' || $rKey === 'pending';
                  ?>
                    <tr class="<?php echo $isSelected ? 'selected-row' : ''; ?>" style="<?php echo $isSelected ? 'background: #f0f9ff;' : ''; ?>">
                      <td>
                        <strong><?php echo e($account['displayName'] ?: $account['username']); ?></strong>
                        <div style="font-size: 0.78rem; color: #64748b;"><?php echo e($account['userID']); ?> · @<?php echo e($account['username']); ?></div>
                      </td>
                      <td><?php echo e($account['email'] ?: '—'); ?></td>
                      <td>
                        <span class="role-badge" style="background:#f1f5f9; color:#1e293b; border: 1px solid #cbd5e1;">
                          <?php echo e($account['role']); ?>
                        </span>
                        <?php if (!empty($account['requestedRole']) && $isPending): ?>
                          <div style="font-size: 0.75rem; color: #b45309; margin-top: 2px;">
                            Req: <?php echo e($roleNamesByKey[$account['requestedRole']] ?? $account['requestedRole']); ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                          $statusClass = 'status-' . strtolower((string) $account['accountStatus']);
                        ?>
                        <span class="status-pill <?php echo e($statusClass); ?>">
                          <?php echo e(ucfirst($account['accountStatus'])); ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <?php if ($isSelected): ?>
                          <span style="font-size: 0.84rem; font-weight: 700; color: #0284c7;">Editing Below ↓</span>
                        <?php else: ?>
                          <a href="?tab=users&user=<?php echo urlencode($account['userID']); ?>#user-editor" class="button-link secondary small" style="text-decoration: none;">
                            Manage →
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Selected User Editor & Overrides Matrix -->
        <section class="form-card" id="user-editor">
          <?php if (!$selectedUser): ?>
            <div style="text-align: center; padding: 40px 20px; color: #64748b;">
              <span style="font-size: 2.2rem; display: block; margin-bottom: 8px;"><?php echo icon('user', '', 40); ?></span>
              <h3 style="margin: 0 0 6px; color: #1e293b;">Select an Account to Manage</h3>
              <p style="margin: 0; font-size: 0.9rem;">Choose any user from the directory table above to view their details, change their role, or apply custom permission overrides.</p>
            </div>
          <?php elseif ($selectedUserId === (currentUser()['id'] ?? null)): ?>
            <div style="padding: 24px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
              <h3>Managing Account: <?php echo e($selectedUser['displayName'] ?: $selectedUser['username']); ?></h3>
              <p class="hint">Your own role, status, and permissions cannot be changed from this screen. Another administrator must make changes to your account to preserve system integrity.</p>
            </div>
          <?php else: ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
              <div>
                <h3 style="margin:0;">Managing Account: <?php echo e($selectedUser['displayName'] ?: $selectedUser['username']); ?></h3>
                <p class="hint" style="margin:2px 0 0;">User ID: <code><?php echo e($selectedUser['userID']); ?></code> · Username: <code><?php echo e($selectedUser['username']); ?></code></p>
              </div>
              <a href="?tab=users" class="button-link secondary small" style="text-decoration:none;">Close Editor &times;</a>
            </div>

            <?php if ($requestedRoleName !== ''): ?>
              <div class="alert info" style="margin-bottom: 16px;">
                Requested account type: <strong><?php echo e($requestedRoleName); ?></strong>. Review this request and choose the approved role and status below.
              </div>
            <?php endif; ?>

            <form method="POST" class="form-grid permissions-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="user_id" value="<?php echo e($selectedUserId); ?>">

              <label>Assigned Role
                <select name="role_key" required>
                  <?php if ($selectedUserRoleKey === 'pending'): ?>
                    <option value="" <?php echo $requestedRoleName === '' ? 'selected' : ''; ?> disabled>Select a role to approve this account</option>
                  <?php endif; ?>
                  <?php foreach ($roles as $role): ?>
                    <?php $assignedRoleKey = $selectedUserRoleKey === 'pending' && $requestedRoleName !== '' ? $selectedUser['requestedRole'] : $selectedUserRoleKey; ?>
                    <option value="<?php echo e($role['roleKey']); ?>" <?php echo $assignedRoleKey === $role['roleKey'] ? 'selected' : ''; ?>>
                      <?php echo e($role['roleName']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label>Account Status
                <select name="account_status">
                  <?php foreach (['active' => 'Active', 'pending' => 'Pending approval', 'disabled' => 'Disabled'] as $status => $label): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $selectedUser['accountStatus'] === $status ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label>Email Address
                <input type="email" name="email" maxlength="254" value="<?php echo e($emailInputValue ?? $selectedUser['email'] ?? ''); ?>" placeholder="user@example.com">
              </label>

              <?php if ($hasDoctorSpecialtyColumn): ?>
                <label>Doctor Specialty (required when activating Doctor account)
                  <input type="text" name="doctor_specialty" maxlength="50" value="<?php echo e($selectedDoctorSpecialty); ?>" placeholder="e.g. Cardiology, Pediatrics">
                </label>
              <?php endif; ?>

              <?php if ($selectedUserRoleKey === 'patient'): ?>
                <section class="patient-link-management" style="grid-column: 1 / -1; background:#f8fafc; padding:18px; border-radius:10px; border:1px solid #e2e8f0; margin-top:8px;">
                  <h4 style="margin:0 0 6px;">Verified Patient Record Link</h4>
                  <p class="hint" style="margin:0 0 12px;">Link an active Patient account to exactly one clinical patient record after verifying their identity. The patient will only see records for their approved link.</p>
                  <?php if ($canLinkPatientRecords): ?>
                    <?php if ($activePatientLinks === []): ?>
                      <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                        <label style="flex:1; margin:0;">Choose Clinical Patient Record
                          <select name="patient_id">
                            <option value="">Choose verified patient record</option>
                            <?php foreach ($availablePatientRecords as $patientRecord): ?>
                              <option value="<?php echo e($patientRecord['patientID']); ?>">
                                <?php echo e($patientRecord['fullName'] . ' — ' . $patientRecord['patientID'] . ' — DOB ' . $patientRecord['dateOfBirth']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <button type="submit" name="link_patient_record" value="1">Verify and link patient</button>
                      </div>
                    <?php else: ?>
                      <?php foreach ($activePatientLinks as $patientLink): ?>
                        <div class="patient-link-row" style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; padding:10px 14px; border-radius:8px; border:1px solid #cbd5e1;">
                          <span>Linked Patient: <strong><?php echo e($patientLink['fullName']); ?></strong> (ID: <code><?php echo e($patientLink['patientID']); ?></code>)</span>
                          <button type="submit" name="revoke_patient_record" value="<?php echo e($patientLink['patientID']); ?>" class="button-link secondary small">Revoke link</button>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="hint">Your manager account needs patient-view permission to verify and link patient records.</p>
                  <?php endif; ?>
                </section>
              <?php endif; ?>

              <label class="permission-manager" style="grid-column: 1 / -1; display:flex; align-items:center; gap:10px; margin: 10px 0;">
                <input type="checkbox" name="manage_users" value="1" <?php echo isset($userPermissionRows['system']['manage_users']) && $userPermissionRows['system']['manage_users'] === 1 ? 'checked' : ''; ?>>
                <strong>Administrative Access:</strong> Allow this user to manage accounts, roles, and permissions
              </label>

              <!-- Individual Overrides Matrix -->
              <div class="panel permissions-panel" style="grid-column: 1 / -1; margin-top: 14px;">
                <div style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; background: #fafafa;">
                  <h4 style="margin: 0 0 4px; color: #1e293b;">Individual Permission Overrides Matrix</h4>
                  <p class="hint" style="margin: 0;">
                    Shows the default for this user's assigned role. Select <strong>Allow</strong> to grant extra access or <strong>Deny</strong> to revoke access.
                  </p>
                </div>

                <div class="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Hospital Section</th>
                        <?php foreach ($actions as $action): ?>
                          <th style="text-align: center;"><?php echo e(ucfirst($action)); ?></th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($sections as $sectionKey => $section): ?>
                        <tr>
                          <td><strong><?php echo e($section['label']); ?></strong></td>
                          <?php foreach ($actions as $action):
                            $val = $userPermissionRows[$sectionKey][$action] ?? null;
                            $choice = $val === null ? 'default' : ($val === 1 ? 'allow' : 'deny');
                            $isRoleDefaultAllowed = !empty($userRoleDefaultPermissions[$sectionKey][$action]);
                            $selectClass = $choice === 'allow' ? 'has-allow' : ($choice === 'deny' ? 'has-deny' : '');
                          ?>
                            <td style="text-align: center;">
                              <select class="override-select <?php echo $selectClass; ?>" name="permissions[<?php echo e($sectionKey); ?>][<?php echo e($action); ?>]" aria-label="<?php echo e($section['label'] . ' ' . $action . ' permission'); ?>">
                                <option value="default" <?php echo $choice === 'default' ? 'selected' : ''; ?>>
                                  Role default (<?php echo $isRoleDefaultAllowed ? '✓ Allowed' : '✕ Denied'; ?>)
                                </option>
                                <option value="allow" <?php echo $choice === 'allow' ? 'selected' : ''; ?> style="color:#15803d; font-weight:600;">
                                  ✓ Allow (Override)
                                </option>
                                <option value="deny" <?php echo $choice === 'deny' ? 'selected' : ''; ?> style="color:#b91c1c; font-weight:600;">
                                  ✕ Deny (Override)
                                </option>
                              </select>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="form-actions" style="grid-column: 1 / -1; margin-top: 18px;">
                <button type="submit" name="save_user_permissions">Save account permissions</button>
              </div>
            </form>
          <?php endif; ?>
        </section>

      <?php else: ?>
        <!-- ========================================== -->
        <!-- TAB 2: ROLE PERMISSION TEMPLATES           -->
        <!-- ========================================== -->

        <div class="concept-box">
          <h4><?php echo icon('shield-check', '', 16); ?> What Role Permission Templates Do</h4>
          <p>
            Role templates define the baseline permissions for each job position in the hospital.
            When a staff member is assigned a role (e.g., Doctor, Receptionist, Lab Technician), they automatically inherit these settings without needing custom configuration.
          </p>
        </div>

        <section class="form-card">
          <h3>Choose Role Template to Configure</h3>
          <p class="hint">Select a role below to view or edit the sections and actions granted to everyone in that role.</p>

          <!-- Role Pills Selector -->
          <div class="role-pills-bar">
            <?php foreach ($roles as $role): ?>
              <a href="?tab=roles&role=<?php echo urlencode($role['roleKey']); ?>" class="role-pill-btn <?php echo $selectedRoleKey === $role['roleKey'] ? 'active' : ''; ?>">
                <?php echo e($role['roleName']); ?>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($selectedRoleKey === currentRoleKey()): ?>
            <div class="alert info" style="margin-top: 16px;">
              You cannot edit the permission template for your own role (<?php echo e($roleNamesByKey[$selectedRoleKey] ?? $selectedRoleKey); ?>) while signed in.
            </div>
          <?php else: ?>
            <form method="POST" class="role-template-form" style="width: 100%; display: block; margin-top: 16px;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="role_key" value="<?php echo e($selectedRoleKey); ?>">

              <div class="panel role-template-panel" style="width: 100%; box-sizing: border-box; margin-bottom: 18px;">
                <div class="panel-header" style="padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
                  <div>
                    <h3 style="margin: 0 0 4px; font-size: 1.05rem;">Baseline Grants for Role: <?php echo e($roleNamesByKey[$selectedRoleKey] ?? $selectedRoleKey); ?></h3>
                    <p class="hint" style="margin: 0; font-size: 0.84rem;">Check the permissions that accounts with the <strong><?php echo e($roleNamesByKey[$selectedRoleKey] ?? $selectedRoleKey); ?></strong> role receive by default.</p>
                  </div>
                  <span class="role-badge" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; font-size: 0.8rem; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                    <?php echo e($roleNamesByKey[$selectedRoleKey] ?? $selectedRoleKey); ?>
                  </span>
                </div>
                <div class="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th style="text-align: left; width: 40%; padding: 12px 16px;">Hospital Section</th>
                        <?php foreach ($actions as $action): ?>
                          <th style="text-align: center; width: 15%; padding: 12px 10px;"><?php echo e(ucfirst($action)); ?></th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($sections as $sectionKey => $section): ?>
                        <tr>
                          <td style="padding: 12px 16px; vertical-align: middle;">
                            <strong><?php echo e($section['label']); ?></strong>
                          </td>
                          <?php foreach ($actions as $action): ?>
                            <td style="text-align: center; padding: 12px 10px; vertical-align: middle;">
                              <input type="checkbox" class="role-checkbox-custom" name="role_permissions[<?php echo e($sectionKey); ?>][<?php echo e($action); ?>]" value="1" aria-label="<?php echo e($section['label'] . ' ' . $action); ?>" <?php echo isset($rolePermissions[$sectionKey][$action]) ? 'checked' : ''; ?>>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="form-actions" style="margin-top: 18px; display: flex; align-items: center; gap: 14px;">
                <button type="submit" name="save_role_permissions">Save role template</button>
                <span class="hint" style="margin: 0; font-size: 0.84rem;">Updates baseline grants across all active accounts assigned to <?php echo e($roleNamesByKey[$selectedRoleKey] ?? $selectedRoleKey); ?>.</span>
              </div>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <script>
    (function () {
      // Live search filter on user directory table
      var searchInput = document.getElementById('user-search-input');
      var userTable = document.getElementById('user-directory-table');
      if (searchInput && userTable) {
        var rows = userTable.querySelectorAll('tbody tr');
        searchInput.addEventListener('input', function () {
          var q = searchInput.value.toLowerCase().trim();
          rows.forEach(function (row) {
            var text = row.textContent.toLowerCase();
            row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
          });
        });
      }
    })();
  </script>
  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
