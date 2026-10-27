-- Apply after 001_permissions_auth.sql.
-- Stores a requested account type separately from the assigned role.
USE medipulse_hms;

CREATE TABLE IF NOT EXISTS tblUserRoleRequests (
  userID       VARCHAR(10) NOT NULL,
  requestedRole VARCHAR(30) NOT NULL,
  requestedAt  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewedBy   VARCHAR(10) NULL,
  reviewedAt   DATETIME NULL,
  PRIMARY KEY (userID),
  KEY idx_role_request_role_reviewed (requestedRole, reviewedAt),
  CONSTRAINT fk_role_request_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE CASCADE,
  CONSTRAINT fk_role_request_role FOREIGN KEY (requestedRole) REFERENCES tblRoles (roleKey),
  CONSTRAINT fk_role_request_reviewer FOREIGN KEY (reviewedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL
) ENGINE=InnoDB;
