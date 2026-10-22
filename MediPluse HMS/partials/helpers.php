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
    $role = strtolower(trim((string) (currentUser()['role'] ?? '')));

    return str_replace([' ', '-'], '_', $role);
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
        'SELECT u.userID, u.username, u.passwordHash, u.role,
                COALESCE(d.fullName, s.fullName, u.username) AS fullName
         FROM tblUsers u
         LEFT JOIN tblDoctors d ON d.userID = u.userID
         LEFT JOIN tblStaff s ON s.userID = u.userID
         WHERE u.username = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['passwordHash'])) {
        return null;
    }

    return [
        'id'       => $row['userID'],
        'username' => $row['username'],
        'name'     => $row['fullName'],
        'role'     => $row['role'],
    ];
}
