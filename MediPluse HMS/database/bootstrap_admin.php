<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php database/bootstrap_admin.php <username> <email> <full-name>\n");
    exit(2);
}

[$script, $username, $email, $name] = $argv;
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || trim($name) === '' || mb_strlen($name) > 100) {
    fwrite(STDERR, "Provide a valid username, email address, and full name.\n");
    exit(2);
}

$pdo = getDb();
$managerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM tblUserPermissions p
     JOIN tblUsers u ON u.userID = p.userID
     WHERE p.sectionKey = 'system' AND p.actionKey = 'manage_users'
       AND p.isAllowed = 1 AND u.accountStatus = 'active'"
)->fetchColumn();
if ($managerCount > 0) {
    fwrite(STDERR, "An active user manager already exists. Use the permission-management screen instead.\n");
    exit(1);
}

$temporaryPassword = bin2hex(random_bytes(20));
$userId = nextId($pdo, 'tblUsers', 'userID', 'USR');
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        $username,
        strtolower($email),
        trim($name),
        password_hash($temporaryPassword, PASSWORD_DEFAULT),
        'Administrator',
        'active',
    ]);
    $pdo->prepare(
        "INSERT INTO tblUserPermissions (userID, sectionKey, actionKey, isAllowed)
         VALUES (?, 'system', 'manage_users', 1)"
    )->execute([$userId]);
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}

fwrite(STDOUT, "Administrator account created. It has user-management access but no operational section access.\n");
fwrite(STDOUT, "Username: {$username}\n");
fwrite(STDOUT, "Temporary password (shown once): {$temporaryPassword}\n");
fwrite(STDOUT, "After signing in, assign the account its required section permissions.\n");
