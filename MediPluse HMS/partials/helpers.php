<?php
/**
 * Shared helpers for the tbl* tables in Chapter Three, Section 3.4.
 * Load with: require_once __DIR__ . '/../partials/helpers.php';  (after config.php)
 */

/** Generates the next ID such as PAT0001, DOC0002, APT0003 (fits VARCHAR(10)). */
function nextId(PDO $pdo, string $table, string $column, string $prefix, int $width = 4): string
{
    $start = strlen($prefix) + 1;
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING($column, $start) AS UNSIGNED)) FROM $table WHERE $column LIKE ?"
    );
    $stmt->execute([$prefix . '%']);

    return $prefix . str_pad((string) (((int) $stmt->fetchColumn()) + 1), $width, '0', STR_PAD_LEFT);
}

/** Internal role key of the logged-in user: admin, doctor, receptionist, ... */
function currentRoleKey(): string
{
    return roleKey((string) (currentUser()['role'] ?? ''));
}

function roleKey(string $role): string
{
    $role = strtolower(str_replace([' ', '-'], '_', trim($role)));

    return [
        'administrator' => 'admin',
        'manager' => 'supervisor',
    ][$role] ?? $role;
}

function appSections(): array
{
    return [
        'patients' => ['label' => 'Patients', 'path' => '/patients/index.php'],
        'appointments' => ['label' => 'Appointments', 'path' => '/appointments/index.php'],
        'doctors' => ['label' => 'Doctors', 'path' => '/doctors/index.php'],
        'lab' => ['label' => 'Lab Results', 'path' => '/lab/index.php'],
        'billing' => ['label' => 'Billing', 'path' => '/billing/index.php'],
        'staff' => ['label' => 'Staff', 'path' => '/staff/index.php'],
        'reports' => ['label' => 'Reports', 'path' => '/reports/index.php'],
    ];
}

function sectionActions(): array
{
    return ['view', 'create', 'update', 'delete'];
}

function roleCanAccessSection(string $role, string $section): bool
{
    if (roleKey($role) === 'patient') {
        return false;
    }

    return true;
}

function linkedPatientIds(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT patientID FROM tblPatientAccounts
         WHERE userID = ? AND accountStatus = 'active'
         ORDER BY patientID"
    );
    $stmt->execute([$userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function requirePatientRole(): void
{
    requireLogin();
    if (roleKey((string) (currentUser()['role'] ?? '')) !== 'patient') {
        denyAccess('This page is only available to patient accounts.');
    }
    if ((currentUser()['accountStatus'] ?? '') !== 'active') {
        denyAccess('Your patient account is awaiting approval.');
    }
}

function hasPermission(PDO $pdo, string $userId, string $role, string $section, string $action, string $status = 'active'): bool
{
    if ($status !== 'active' || !roleCanAccessSection($role, $section)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT isAllowed FROM tblUserPermissions WHERE userID = ? AND sectionKey = ? AND actionKey = ?'
    );
    $stmt->execute([$userId, $section, $action]);
    $override = $stmt->fetchColumn();
    if ($override !== false) {
        return (bool) $override;
    }

    if ($section === 'system') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT isAllowed FROM tblRolePermissions WHERE roleKey = ? AND sectionKey = ? AND actionKey = ?'
    );
    $stmt->execute([roleKey($role), $section, $action]);

    return (bool) $stmt->fetchColumn();
}

function can(string $section, string $action = 'view'): bool
{
    $userId = (string) (currentUser()['id'] ?? '');
    if ($userId === '') {
        return false;
    }

    static $accounts = [];
    if (!array_key_exists($userId, $accounts)) {
        $stmt = getDb()->prepare('SELECT role, accountStatus FROM tblUsers WHERE userID = ?');
        $stmt->execute([$userId]);
        $accounts[$userId] = $stmt->fetch() ?: null;
    }

    $account = $accounts[$userId];
    if (!$account || $account['accountStatus'] !== 'active') {
        return false;
    }
    if (!roleCanAccessSection($account['role'], $section)) {
        return false;
    }

    static $permissions = [];
    if (!array_key_exists($userId, $permissions)) {
        $permissions[$userId] = [];
        $roleKey = roleKey($account['role']);

        $stmt = getDb()->prepare(
            'SELECT sectionKey, actionKey FROM tblRolePermissions WHERE roleKey = ? AND isAllowed = 1'
        );
        $stmt->execute([$roleKey]);
        foreach ($stmt->fetchAll() as $permission) {
            $permissions[$userId][$permission['sectionKey']][$permission['actionKey']] = true;
        }

        $stmt = getDb()->prepare(
            'SELECT sectionKey, actionKey, isAllowed FROM tblUserPermissions WHERE userID = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $permission) {
            $permissions[$userId][$permission['sectionKey']][$permission['actionKey']] = (bool) $permission['isAllowed'];
        }
    }

    return $permissions[$userId][$section][$action] ?? false;
}

function requireLogin(): void
{
    $userId = (string) (currentUser()['id'] ?? '');
    if ($userId === '') {
        redirect('/auth/login.php');
    }

    $stmt = getDb()->prepare(
        'SELECT username, email, displayName, role, accountStatus, sessionVersion
         FROM tblUsers WHERE userID = ?'
    );
    $stmt->execute([$userId]);
    $account = $stmt->fetch();

    if (!$account || $account['accountStatus'] === 'disabled'
        || (isset($_SESSION['authVersion']) && (int) $_SESSION['authVersion'] !== (int) $account['sessionVersion'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirect('/auth/login.php');
    }

    $_SESSION['user'] = [
        'id' => $userId,
        'username' => $account['username'],
        'email' => $account['email'],
        'name' => $account['displayName'] ?: $account['username'],
        'role' => $account['role'],
        'accountStatus' => $account['accountStatus'],
    ];
    $_SESSION['authVersion'] = (int) $account['sessionVersion'];
}

function denyAccess(string $message = 'You do not have permission to access this page.'): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Access restricted</title>'
        . '<body><main><h1>Access restricted</h1><p>' . e($message) . '</p>'
        . '<p><a href="' . e(url('/dashboard/index.php')) . '">Return to dashboard</a></p></main></body></html>';
    exit;
}

function requireSectionPermission(string $section, string $action = 'view'): void
{
    requireLogin();
    if (!isset(appSections()[$section]) || !in_array($action, sectionActions(), true)) {
        http_response_code(404);
        exit('Page not found.');
    }

    if (!can($section, $action)) {
        denyAccess('Permission for ' . appSections()[$section]['label'] . ' (' . $action . ') is required.');
    }
}

function requireUserManagementPermission(): void
{
    requireLogin();
    if (!can('system', 'manage_users')) {
        denyAccess('Permission to manage user accounts and permissions is required.');
    }
}

function recordAuditEvent(PDO $pdo, string $section, string $action, ?string $targetId = null): void
{
    $userId = currentUser()['id'] ?? null;
    $pdo->prepare('INSERT INTO tblAuditLog (userID, sectionKey, actionKey, targetID) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $section, $action, $targetId]);
}

/** doctorID of the logged-in doctor (via tblDoctors.userID), or null. */
function currentDoctorId(PDO $pdo): ?string
{
    $userId = currentUser()['id'] ?? null;
    if ($userId === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT doctorID FROM tblDoctors WHERE userID = ?');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (string) $id;
}

/**
 * Checks a username/password against tblUsers.
 * Returns the array to store in $_SESSION['user'], or null if invalid.
 * Use this inside auth/login.php instead of the old query on `users`.
 */
function attemptLogin(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.userID, u.username, u.email, u.displayName, u.passwordHash, u.role,
                u.accountStatus, u.sessionVersion,
                COALESCE(d.fullName, s.fullName, u.displayName, u.username) AS fullName
         FROM tblUsers u
         LEFT JOIN tblDoctors d ON d.userID = u.userID
         LEFT JOIN tblStaff s ON s.userID = u.userID
         WHERE u.username = ? OR u.email = ?
         LIMIT 1'
    );
    $stmt->execute([$username, $username]);
    $row = $stmt->fetch();

    if (!$row || $row['accountStatus'] === 'disabled' || !password_verify($password, $row['passwordHash'])) {
        return null;
    }

    return [
        'id'       => $row['userID'],
        'username' => $row['username'],
        'email'    => $row['email'],
        'name'     => $row['fullName'],
        'role'     => $row['role'],
        'accountStatus' => $row['accountStatus'],
        'sessionVersion' => (int) $row['sessionVersion'],
    ];
}

function doctorSpecialties(): array
{
    return [
        'Anesthesiology',
        'Cardiology',
        'Dermatology',
        'Emergency Medicine',
        'Endocrinology',
        'Family Medicine',
        'Gastroenterology',
        'General Surgery',
        'Hematology',
        'Infectious Disease',
        'Internal Medicine',
        'Neurology',
        'Neurosurgery',
        'Obstetrics and Gynecology',
        'Oncology',
        'Ophthalmology',
        'Orthopedic Surgery',
        'Otolaryngology (ENT)',
        'Pediatrics',
        'Physical Medicine and Rehabilitation',
        'Plastic Surgery',
        'Psychiatry',
        'Pulmonology',
        'Radiology',
        'Urology',
    ];
}

function createPendingAccount(
    PDO $pdo,
    string $name,
    string $email,
    string $password,
    string $requestedRole,
    ?string $doctorSpecialty = null
): string
{
    $signupRoles = publicSignupRoles($pdo);
    $requestedRole = trim($requestedRole);
    $doctorSpecialty = trim((string) $doctorSpecialty);
    if (!isset($signupRoles[$requestedRole])) {
        throw new InvalidArgumentException('Choose a valid account type.');
    }
    if ($requestedRole === 'doctor' && !in_array($doctorSpecialty, doctorSpecialties(), true)) {
        throw new InvalidArgumentException('Choose a valid medical specialty.');
    }

    $userId = 'USR' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
    $username = 'account_' . bin2hex(random_bytes(16));
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus)
             VALUES (?, ?, ?, ?, ?, 'Pending', 'pending')"
        );
        $stmt->execute([$userId, $username, $email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        $pdo->prepare(
            'INSERT INTO tblUserRoleRequests (userID, requestedRole, doctorSpecialty) VALUES (?, ?, ?)'
        )->execute([$userId, $requestedRole, $requestedRole === 'doctor' ? $doctorSpecialty : null]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return $userId;
}

function registerDirectPatientAccount(
    PDO $pdo,
    string $name,
    string $email,
    string $password,
    string $gender,
    string $dateOfBirth,
    string $phoneNumber = '',
    string $address = ''
): array {
    $email = strtolower(trim($email));
    $name = trim($name);
    $gender = trim($gender);
    $dateOfBirth = trim($dateOfBirth);
    $phoneNumber = trim($phoneNumber);
    $address = trim($address);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Enter a name between 2 and 100 characters.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Choose a password with at least 12 characters.');
    }
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        throw new InvalidArgumentException('Choose a valid gender option.');
    }
    $dobDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirth);
    if (!$dobDate || $dobDate->format('Y-m-d') !== $dateOfBirth || $dobDate > new DateTimeImmutable('today')) {
        throw new InvalidArgumentException('Enter a valid date of birth in the past.');
    }

    $existing = $pdo->prepare('SELECT userID FROM tblUsers WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    if ($existing->fetchColumn()) {
        throw new InvalidArgumentException('That email address is already associated with an account.');
    }

    $userId = nextId($pdo, 'tblUsers', 'userID', 'USR');
    $patientId = nextId($pdo, 'tblPatients', 'patientID', 'PAT');
    $username = 'patient_' . bin2hex(random_bytes(8));

    $pdo->beginTransaction();
    try {
        $stmtUser = $pdo->prepare(
            "INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus, sessionVersion)
             VALUES (?, ?, ?, ?, ?, 'Patient', 'active', 1)"
        );
        $stmtUser->execute([
            $userId,
            $username,
            $email,
            $name,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $stmtPatient = $pdo->prepare(
            "INSERT INTO tblPatients (patientID, fullName, gender, dateOfBirth, phoneNumber, address, registrationDate)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE)"
        );
        $stmtPatient->execute([
            $patientId,
            $name,
            $gender,
            $dateOfBirth,
            $phoneNumber !== '' ? $phoneNumber : null,
            $address !== '' ? $address : null,
        ]);

        $stmtLink = $pdo->prepare(
            "INSERT INTO tblPatientAccounts (userID, patientID, accountStatus, approvedBy, approvedAt)
             VALUES (?, ?, 'active', NULL, CURRENT_TIMESTAMP)"
        );
        $stmtLink->execute([$userId, $patientId]);

        $pdo->commit();

        return [
            'id'            => $userId,
            'username'      => $username,
            'email'         => $email,
            'name'          => $name,
            'role'          => 'Patient',
            'accountStatus' => 'active',
            'sessionVersion'=> 1,
            'patientID'     => $patientId,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function ensureDoctorProfileForAccount(PDO $pdo, string $userId, string $specialty): string
{
    $profile = $pdo->prepare('SELECT doctorID FROM tblDoctors WHERE userID = ?');
    $profile->execute([$userId]);
    $doctorId = $profile->fetchColumn();
    if ($doctorId !== false) {
        $pdo->prepare('UPDATE tblDoctors SET specialty = ? WHERE doctorID = ?')
            ->execute([$specialty, $doctorId]);
        return (string) $doctorId;
    }

    $account = $pdo->prepare('SELECT displayName, username FROM tblUsers WHERE userID = ?');
    $account->execute([$userId]);
    $user = $account->fetch();
    if (!$user) {
        throw new RuntimeException('The approved Doctor account could not be found.');
    }

    $doctorId = nextId($pdo, 'tblDoctors', 'doctorID', 'DOC');
    $pdo->prepare(
        'INSERT INTO tblDoctors (doctorID, fullName, specialty, phoneNumber, userID)
         VALUES (?, ?, ?, NULL, ?)'
    )->execute([$doctorId, $user['displayName'] ?: $user['username'], $specialty, $userId]);

    return $doctorId;
}

function publicSignupRoles(PDO $pdo): array
{
    $roles = $pdo->query(
        "SELECT roleKey, roleName FROM tblRoles
         WHERE isAssignable = 1 AND roleKey NOT IN ('admin', 'supervisor', 'pending')
         ORDER BY roleName"
    )->fetchAll();

    $options = [];
    foreach ($roles as $role) {
        $options[$role['roleKey']] = $role['roleName'];
    }

    return $options;
}

function issuePasswordResetToken(PDO $pdo, string $userId, ?DateTimeImmutable $now = null): string
{
    $now = $now ?? new DateTimeImmutable('now');
    $token = bin2hex(random_bytes(32));
    $expiresAt = $now->modify('+' . PASSWORD_RESET_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE tblPasswordResetTokens SET consumedAt = ? WHERE userID = ? AND consumedAt IS NULL'
        );
        $stmt->execute([$now->format('Y-m-d H:i:s'), $userId]);
        $stmt = $pdo->prepare(
            'INSERT INTO tblPasswordResetTokens (tokenHash, userID, expiresAt) VALUES (?, ?, ?)'
        );
        $stmt->execute([hash('sha256', $token), $userId, $expiresAt]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return $token;
}

function consumePasswordResetToken(PDO $pdo, string $token, string $password, ?DateTimeImmutable $now = null): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $now = $now ?? new DateTimeImmutable('now');
    $timestamp = $now->format('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare(
            'SELECT userID FROM tblPasswordResetTokens
             WHERE tokenHash = ? AND consumedAt IS NULL AND expiresAt > ?' . $lockClause
        );
        $stmt->execute([hash('sha256', $token), $timestamp]);
        $userId = $stmt->fetchColumn();
        if ($userId === false) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblPasswordResetTokens SET consumedAt = ? WHERE tokenHash = ? AND consumedAt IS NULL'
        );
        $stmt->execute([$timestamp, hash('sha256', $token)]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblUsers SET passwordHash = ?, sessionVersion = sessionVersion + 1 WHERE userID = ?'
        );
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $pdo->commit();

        return true;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function smtpReadResponse($socket): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('SMTP server closed the connection.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    return $response;
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Unable to write to the SMTP server.');
    }

    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
    }

    return $response;
}

function sendPasswordResetEmail(string $email, string $name, string $token): void
{
    if (APP_URL === '' || MAIL_HOST === '' || MAIL_FROM_ADDRESS === ''
        || !filter_var(MAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Password reset email configuration is incomplete.');
    }

    $transport = MAIL_PORT === 465 ? 'ssl://' : 'tcp://';
    $socket = stream_socket_client(
        $transport . MAIL_HOST . ':' . MAIL_PORT,
        $errorCode,
        $errorMessage,
        10,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );
    if ($socket === false) {
        throw new RuntimeException('Unable to connect to the SMTP server: ' . $errorMessage, $errorCode);
    }
    stream_set_timeout($socket, 10);

    try {
        $greeting = smtpReadResponse($socket);
        if ((int) substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP server did not accept the connection.');
        }

        $hostName = (string) (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost');
        smtpCommand($socket, 'EHLO ' . $hostName, [250]);

        if (MAIL_PORT !== 465) {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('Unable to establish SMTP TLS.');
            }
            smtpCommand($socket, 'EHLO ' . $hostName, [250]);
        }

        if (MAIL_USERNAME !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode(MAIL_USERNAME), [334]);
            smtpCommand($socket, base64_encode(MAIL_PASSWORD), [235]);
        }

        smtpCommand($socket, 'MAIL FROM:<' . MAIL_FROM_ADDRESS . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $email . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $resetUrl = APP_URL . url('/auth/reset_password.php?token=' . rawurlencode($token));
        $safeName = trim(preg_replace('/[\r\n]+/', ' ', $name));
        $subject = '=?UTF-8?B?' . base64_encode(APP_NAME . ' password reset') . '?=';
        $fromName = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
        $body = "Hello {$safeName},\r\n\r\n"
            . "Use the link below to set a new password. It expires in " . PASSWORD_RESET_TTL_MINUTES . " minutes and can be used once.\r\n\r\n"
            . $resetUrl . "\r\n\r\nIf you did not request this, you can ignore this email.\r\n";
        $message = 'From: ' . $fromName . ' <' . MAIL_FROM_ADDRESS . ">\r\n"
            . 'To: <' . $email . ">\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . preg_replace('/(?m)^\./', '..', $body);
        if (fwrite($socket, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException('Unable to send the SMTP message.');
        }
        $response = smtpReadResponse($socket);
        if ((int) substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP server did not accept the message.');
        }
        smtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
