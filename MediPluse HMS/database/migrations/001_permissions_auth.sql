-- Apply once, after database/schema.sql. Preserves existing clinical records.
USE medipulse_hms;

-- Older installations may already have an email column or may not have the
-- original role check constraint. Add only the missing schema pieces.
SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblUsers'
      AND CONSTRAINT_NAME = 'chk_users_role'
      AND CONSTRAINT_TYPE = 'CHECK'
  ),
  'ALTER TABLE tblUsers DROP CONSTRAINT chk_users_role',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblUsers' AND COLUMN_NAME = 'email'
  ),
  'SELECT 1',
  'ALTER TABLE tblUsers ADD COLUMN email VARCHAR(254) NULL AFTER username'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblUsers' AND COLUMN_NAME = 'displayName'
  ),
  'SELECT 1',
  'ALTER TABLE tblUsers ADD COLUMN displayName VARCHAR(100) NULL AFTER email'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblUsers' AND COLUMN_NAME = 'accountStatus'
  ),
  'SELECT 1',
  'ALTER TABLE tblUsers ADD COLUMN accountStatus VARCHAR(20) NOT NULL DEFAULT ''active'' AFTER role'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblUsers' AND COLUMN_NAME = 'sessionVersion'
  ),
  'SELECT 1',
  'ALTER TABLE tblUsers ADD COLUMN sessionVersion INT UNSIGNED NOT NULL DEFAULT 0 AFTER accountStatus'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblUsers'
      AND COLUMN_NAME = 'email'
      AND NON_UNIQUE = 0
  ),
  'SELECT 1',
  'ALTER TABLE tblUsers ADD UNIQUE KEY uq_users_email (email)'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

UPDATE tblUsers
SET displayName = username
WHERE displayName IS NULL OR displayName = '';

CREATE TABLE tblRoles (
  roleKey       VARCHAR(30)  NOT NULL,
  roleName      VARCHAR(50)  NOT NULL,
  description   VARCHAR(255) NOT NULL DEFAULT '',
  isAssignable  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (roleKey),
  UNIQUE KEY uq_roles_name (roleName)
) ENGINE=InnoDB;

INSERT INTO tblRoles (roleKey, roleName, description, isAssignable) VALUES
  ('admin', 'Administrator', 'System user and permission administration; section access is separately granted.', 1),
  ('supervisor', 'Supervisor', 'Read-only oversight across assigned hospital sections.', 1),
  ('doctor', 'Doctor', 'Clinical access explicitly granted for this user.', 1),
  ('receptionist', 'Receptionist', 'Patient registration and appointment scheduling.', 1),
  ('lab_technician', 'Lab Technician', 'Laboratory result access and updates.', 1),
  ('billing_officer', 'Billing Officer', 'Billing access and payment updates.', 1),
  ('patient', 'Patient', 'Patient role; section access requires explicit grants.', 1),
  ('pending', 'Pending approval', 'New registration awaiting authorized review.', 0);

CREATE TABLE tblSections (
  sectionKey VARCHAR(30) NOT NULL,
  label      VARCHAR(50) NOT NULL,
  sortOrder  TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (sectionKey),
  UNIQUE KEY uq_sections_label (label)
) ENGINE=InnoDB;

INSERT INTO tblSections (sectionKey, label, sortOrder) VALUES
  ('patients', 'Patients', 1),
  ('appointments', 'Appointments', 2),
  ('doctors', 'Doctors', 3),
  ('lab', 'Lab Results', 4),
  ('billing', 'Billing', 5),
  ('staff', 'Staff', 6),
  ('reports', 'Reports', 7),
  ('system', 'System Administration', 0);

CREATE TABLE tblRolePermissions (
  roleKey     VARCHAR(30) NOT NULL,
  sectionKey  VARCHAR(30) NOT NULL,
  actionKey   VARCHAR(20) NOT NULL,
  isAllowed   TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (roleKey, sectionKey, actionKey),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (roleKey) REFERENCES tblRoles (roleKey) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_section FOREIGN KEY (sectionKey) REFERENCES tblSections (sectionKey) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tblUserPermissions (
  userID      VARCHAR(10) NOT NULL,
  sectionKey  VARCHAR(30) NOT NULL,
  actionKey   VARCHAR(20) NOT NULL,
  isAllowed   TINYINT(1)  NOT NULL,
  updatedBy   VARCHAR(10) NULL,
  updatedAt   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (userID, sectionKey, actionKey),
  CONSTRAINT fk_user_permissions_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE CASCADE,
  CONSTRAINT fk_user_permissions_section FOREIGN KEY (sectionKey) REFERENCES tblSections (sectionKey) ON DELETE CASCADE,
  CONSTRAINT fk_user_permissions_updated_by FOREIGN KEY (updatedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tblPasswordResetTokens (
  tokenHash  CHAR(64)    NOT NULL,
  userID     VARCHAR(10) NOT NULL,
  expiresAt  DATETIME    NOT NULL,
  consumedAt DATETIME    NULL,
  createdAt  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tokenHash),
  KEY idx_reset_user_expiry (userID, expiresAt),
  CONSTRAINT fk_reset_token_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tblAuditLog (
  auditID    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  userID     VARCHAR(10) NULL,
  sectionKey VARCHAR(30) NOT NULL,
  actionKey  VARCHAR(40) NOT NULL,
  targetID   VARCHAR(64) NULL,
  createdAt  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (auditID),
  KEY idx_audit_user_date (userID, createdAt),
  CONSTRAINT fk_audit_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Explicit role templates. Administrator receives no clinical/operational
-- section access by default; its role is not a section-permission bypass.
INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed) VALUES
  ('supervisor', 'patients', 'view', 1),
  ('supervisor', 'appointments', 'view', 1),
  ('supervisor', 'doctors', 'view', 1),
  ('supervisor', 'lab', 'view', 1),
  ('supervisor', 'billing', 'view', 1),
  ('supervisor', 'staff', 'view', 1),
  ('supervisor', 'reports', 'view', 1),
  ('doctor', 'patients', 'view', 1),
  ('doctor', 'appointments', 'view', 1),
  ('doctor', 'appointments', 'create', 1),
  ('doctor', 'appointments', 'update', 1),
  ('doctor', 'doctors', 'view', 1),
  ('doctor', 'lab', 'view', 1),
  ('doctor', 'lab', 'create', 1),
  ('receptionist', 'patients', 'view', 1),
  ('receptionist', 'patients', 'create', 1),
  ('receptionist', 'patients', 'update', 1),
  ('receptionist', 'appointments', 'view', 1),
  ('receptionist', 'appointments', 'create', 1),
  ('receptionist', 'appointments', 'update', 1),
  ('receptionist', 'doctors', 'view', 1),
  ('lab_technician', 'lab', 'view', 1),
  ('lab_technician', 'lab', 'update', 1),
  ('billing_officer', 'billing', 'view', 1),
  ('billing_officer', 'billing', 'create', 1),
  ('billing_officer', 'billing', 'update', 1);

-- Legacy active administrators receive only the essential user-management
-- capability, not access to the seven operational sections.
INSERT INTO tblUserPermissions (userID, sectionKey, actionKey, isAllowed)
SELECT userID, 'system', 'manage_users', 1
FROM tblUsers
WHERE LOWER(REPLACE(role, ' ', '_')) IN ('administrator', 'admin')
  AND accountStatus = 'active';
