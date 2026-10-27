-- Apply after 003_signup_role_requests.sql.
-- Stores the specialty collected during a Doctor account request.
USE medipulse_hms;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblUserRoleRequests'
      AND COLUMN_NAME = 'doctorSpecialty'
  ),
  'SELECT 1',
  'ALTER TABLE tblUserRoleRequests ADD COLUMN doctorSpecialty VARCHAR(50) NULL AFTER requestedRole'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
