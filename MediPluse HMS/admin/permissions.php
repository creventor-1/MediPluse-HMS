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
            r.requestedRole, ' . $doctorSpecialtySelection . ', r.reviewedAt
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
            $message = 'Role permission template saved.';
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
            $message = 'Account role, status, and permissions saved.';
            if ($roleKey === 'doctor' && $accountStatus === 'active') {
                $message .= ' The Doctor profile is linked and available in the Doctors directory and appointment booking list.';
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
                    r.requestedRole, ' . $doctorSpecialtySelection . ', r.reviewedAt
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

                $userLink = $pdo->prepare(
                    "SELECT patientID FROM tblPatientAccounts
                     WHERE userID = ? AND accountStatus = 'active' FOR UPDATE"
                );
                $userLink->execute([$targetUserId]);
                $activePatientIds = $userLink->fetchAll(PDO::FETCH_COLUMN);
                if ($activePatientIds !== [] && !in_array($patientId, $activePatientIds, true)) {
                    throw new RuntimeException('This account is already linked to another patient record. Revoke that link first.');
                }

                $patientLink = $pdo->prepare(
                    "SELECT userID FROM tblPatientAccounts
                     WHERE patientID = ? AND accountStatus = 'active' FOR UPDATE"
                );
                $patientLink->execute([$patientId]);
                $activeUserIds = $patientLink->fetchAll(PDO::FETCH_COLUMN);
                if ($activeUserIds !== [] && !in_array($targetUserId, $activeUserIds, true)) {
                    throw new RuntimeException('This patient record is already linked to another account.');
                }

                $pdo->prepare(
                    "INSERT INTO tblPatientAccounts (userID, patientID, accountStatus, approvedBy, approvedAt)
                     VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP)
                     ON DUPLICATE KEY UPDATE accountStatus = 'active', approvedBy = VALUES(approvedBy),
                                             approvedAt = CURRENT_TIMESTAMP"
                )->execute([$targetUserId, $patientId, currentUser()['id']]);
                recordAuditEvent($pdo, 'system', 'patient_account_linked', $targetUserId . ':' . $patientId);
                $pdo->commit();
                $message = 'Patient account linked and approved.';
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
        } else {
            $pdo->beginTransaction();
            try {
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
<body>
  <div class="layout">
    <?php $activeNav = 'dashboard'; require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main-content">
      <div class="page-header">
        <div><p class="eyebrow">System administration</p><h2>Roles &amp; permissions</h2></div>
        <a class="button-link secondary" href="<?php echo e(url('/dashboard/index.php')); ?>">Back to dashboard</a>
      </div>
      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>" role="status"><?php echo e($message); ?></div>
      <?php endif; ?>
      <?php if (!$hasDoctorSpecialtyColumn): ?>
        <div class="alert info" role="status">
          Doctor specialty support is not set up in this database yet. Run <code>database/migrations/009_doctor_signup_specialty.sql</code> once in phpMyAdmin against <code>medipulse_hms</code>, then reload this page to approve Doctor accounts and create their profiles.
        </div>
      <?php endif; ?>

      <section class="form-card">
        <h3>Role permission templates</h3>
        <p class="hint">These are explicit section/action grants. The Administrator role has no automatic section access.</p>
        <form method="GET" class="form-grid">
          <label>Role template
            <select name="role" onchange="this.form.submit()">
              <?php foreach ($roles as $role): ?>
                <option value="<?php echo e($role['roleKey']); ?>" <?php echo $selectedRoleKey === $role['roleKey'] ? 'selected' : ''; ?>>
                  <?php echo e($role['roleName']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <noscript><button type="submit">Load role</button></noscript>
        </form>
        <?php if ($selectedRoleKey === currentRoleKey()): ?>
          <p class="hint">You cannot edit the permission template for your own role. Select another role.</p>
        <?php else: ?>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="role_key" value="<?php echo e($selectedRoleKey); ?>">
          <div class="panel">
            <table>
              <thead><tr><th>Section</th><?php foreach ($actions as $action): ?><th><?php echo e(ucfirst($action)); ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($sections as $sectionKey => $section): ?>
                  <tr>
                    <td><?php echo e($section['label']); ?></td>
                    <?php foreach ($actions as $action): ?>
                      <td><input type="checkbox" name="role_permissions[<?php echo e($sectionKey); ?>][<?php echo e($action); ?>]" value="1" aria-label="<?php echo e($section['label'] . ' ' . $action); ?>" <?php echo isset($rolePermissions[$sectionKey][$action]) ? 'checked' : ''; ?>></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="form-actions"><button type="submit" name="save_role_permissions">Save role template</button></div>
        </form>
        <?php endif; ?>
      </section>

      <section class="form-card">
        <h3>Account roles and individual overrides</h3>
        <form method="GET" class="form-grid">
          <label>Select account
            <select name="user" onchange="this.form.submit()">
              <option value="">Choose an account</option>
              <?php foreach ($users as $account): ?>
                <option value="<?php echo e($account['userID']); ?>" <?php echo $selectedUserId === $account['userID'] ? 'selected' : ''; ?>>
                  <?php
                    $requestLabel = !empty($account['requestedRole']) && isset($roleNamesByKey[$account['requestedRole']])
                        ? ' — Requested: ' . $roleNamesByKey[$account['requestedRole']]
                        : '';
                    echo e(($account['displayName'] ?: $account['username']) . ' — ' . $account['role'] . $requestLabel . ' (' . $account['accountStatus'] . ')');
                  ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <noscript><button type="submit">Load account</button></noscript>
        </form>
        <?php if ($selectedUser && $selectedUserId !== (currentUser()['id'] ?? null)): ?>
        <form method="POST" class="form-grid permissions-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo e($selectedUserId); ?>">
          <?php if ($requestedRoleName !== ''): ?>
            <p class="hint">Requested account type: <strong><?php echo e($requestedRoleName); ?></strong>. Review this request and choose the approved role below.</p>
          <?php endif; ?>
          <label>Role
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
          <label>Account status
            <select name="account_status">
              <?php foreach (['active' => 'Active', 'pending' => 'Pending approval', 'disabled' => 'Disabled'] as $status => $label): ?>
                <option value="<?php echo e($status); ?>" <?php echo $selectedUser['accountStatus'] === $status ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Email for password recovery
            <input type="email" name="email" maxlength="254" value="<?php echo e($emailInputValue ?? $selectedUser['email'] ?? ''); ?>">
          </label>
          <?php if ($hasDoctorSpecialtyColumn): ?>
            <label>Doctor specialty (required when activating a Doctor account)
              <input type="text" name="doctor_specialty" maxlength="50" value="<?php echo e($selectedDoctorSpecialty); ?>">
            </label>
          <?php endif; ?>
          <?php if ($selectedUserRoleKey === 'patient'): ?>
            <section class="patient-link-management">
              <h3>Verified patient record</h3>
              <p class="hint">Link an active Patient account to exactly one patient record after verifying the person’s identity. The account will only see records for its approved link.</p>
              <?php if ($canLinkPatientRecords): ?>
                <?php if ($activePatientLinks === []): ?>
                  <label>Patient record
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
                <?php else: ?>
                  <?php foreach ($activePatientLinks as $patientLink): ?>
                    <div class="patient-link-row">
                      <span><strong><?php echo e($patientLink['fullName']); ?></strong> — <?php echo e($patientLink['patientID']); ?></span>
                      <button type="submit" name="revoke_patient_record" value="<?php echo e($patientLink['patientID']); ?>" class="button-link secondary small">Revoke link</button>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php else: ?>
                <p class="hint">Your manager account needs patient-view permission to verify and link patient records.</p>
              <?php endif; ?>
            </section>
          <?php endif; ?>
          <label class="permission-manager">
            <input type="checkbox" name="manage_users" value="1" <?php echo isset($userPermissionRows['system']['manage_users']) && $userPermissionRows['system']['manage_users'] === 1 ? 'checked' : ''; ?>>
            Allow this user to manage accounts, roles, and permissions
          </label>
          <p class="hint">For each action, choose the role default, explicitly allow, or explicitly deny. User overrides take precedence over role templates.</p>
          <div class="panel permissions-panel">
            <div class="table-wrap">
              <table>
                <thead><tr><th>Section</th><?php foreach ($actions as $action): ?><th><?php echo e(ucfirst($action)); ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                  <?php foreach ($sections as $sectionKey => $section): ?>
                    <tr>
                      <td><?php echo e($section['label']); ?></td>
                      <?php foreach ($actions as $action):
                          $value = $userPermissionRows[$sectionKey][$action] ?? null;
                          $choice = $value === null ? 'default' : ($value === 1 ? 'allow' : 'deny');
                      ?>
                        <td>
                          <select name="permissions[<?php echo e($sectionKey); ?>][<?php echo e($action); ?>]" aria-label="<?php echo e($section['label'] . ' ' . $action . ' permission'); ?>">
                            <option value="default" <?php echo $choice === 'default' ? 'selected' : ''; ?>>Role default</option>
                            <option value="allow" <?php echo $choice === 'allow' ? 'selected' : ''; ?>>Allow</option>
                            <option value="deny" <?php echo $choice === 'deny' ? 'selected' : ''; ?>>Deny</option>
                          </select>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="form-actions"><button type="submit" name="save_user_permissions">Save account permissions</button></div>
        </form>
        <?php elseif ($selectedUser): ?>
          <p class="hint">Your own role, status, and permissions cannot be changed from this screen.</p>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
