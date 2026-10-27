<?php
/**
 * Automated migration and setup runner for MediPulse HMS.
 * Safe to run multiple times (idempotent).
 * Can be run via CLI during container boot or manually.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden. CLI execution only.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

function logMsg(string $msg): void
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
}

try {
    logMsg("Connecting to database: " . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . "...");
    $pdo = getDb();
    $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
} catch (Throwable $e) {
    logMsg("Error connecting to database: " . $e->getMessage());
    exit(1);
}

// 1. Ensure migrations tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tbl_migrations (
        migration_file VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

// 2. Check if base schema has been executed
$tableCheck = $pdo->query("
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'tblUsers'
")->fetchColumn();

if ((int)$tableCheck === 0) {
    logMsg("Running initial database schema (database/schema.sql)...");
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        logMsg("Error: schema.sql not found at $schemaFile");
        exit(1);
    }

    $schemaSql = file_get_contents($schemaFile);
    // Remove database creation / USE statements to support custom cloud database names
    $schemaSql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+[^;]+;/i', '', $schemaSql);
    $schemaSql = preg_replace('/USE\s+[^;]+;/i', '', $schemaSql);

    $pdo->exec($schemaSql);
    logMsg("Base schema created successfully.");
} else {
    logMsg("Base schema already exists.");
}

// 3. Run all migrations in order
$migrationsDir = __DIR__ . '/migrations';
if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '/*.sql');
    sort($files);

    foreach ($files as $filePath) {
        $fileName = basename($filePath);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_migrations WHERE migration_file = ?");
        $stmt->execute([$fileName]);
        if ((int)$stmt->fetchColumn() > 0) {
            logMsg("Migration {$fileName} already applied. Skipping.");
            continue;
        }

        logMsg("Applying migration {$fileName}...");
        $migrationSql = file_get_contents($filePath);
        $migrationSql = preg_replace('/USE\s+[^;]+;/i', '', $migrationSql);

        $pdo->exec($migrationSql);

        $record = $pdo->prepare("INSERT INTO tbl_migrations (migration_file) VALUES (?)");
        $record->execute([$fileName]);
        logMsg("Migration {$fileName} applied successfully.");
    }
}

// 4. Ensure an active Administrator exists
$adminCount = (int)$pdo->query("
    SELECT COUNT(*) FROM tblUserPermissions p
    JOIN tblUsers u ON u.userID = p.userID
    WHERE p.sectionKey = 'system' AND p.actionKey = 'manage_users'
      AND p.isAllowed = 1 AND u.accountStatus = 'active'
")->fetchColumn();

if ($adminCount === 0) {
    $adminUsername = getenv('ADMIN_USERNAME') ?: 'admin';
    $adminEmail = strtolower(getenv('ADMIN_EMAIL') ?: 'admin@medipulse.local');
    $adminPassword = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
    $adminName = getenv('ADMIN_NAME') ?: 'System Administrator';

    $userId = nextId($pdo, 'tblUsers', 'userID', 'USR');

    $pdo->beginTransaction();
    try {
        $pdo->prepare('
            INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $userId,
            $adminUsername,
            $adminEmail,
            $adminName,
            password_hash($adminPassword, PASSWORD_DEFAULT),
            'Administrator',
            'active',
        ]);

        $pdo->prepare("
            INSERT INTO tblUserPermissions (userID, sectionKey, actionKey, isAllowed)
            VALUES (?, 'system', 'manage_users', 1)
        ")->execute([$userId]);

        $pdo->commit();

        logMsg("=================================================");
        logMsg("Initial Administrator account created!");
        logMsg("Username: {$adminUsername}");
        logMsg("Email:    {$adminEmail}");
        logMsg("Password: {$adminPassword}");
        logMsg("=================================================");
    } catch (Throwable $e) {
        $pdo->rollBack();
        logMsg("Failed to bootstrap administrator: " . $e->getMessage());
    }
} else {
    logMsg("Active administrator account already present.");
}

logMsg("Migration and database setup complete.");
