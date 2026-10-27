-- Allow doctors to book appointments assigned to their own doctor profile.
-- Per-user permission overrides remain authoritative.
INSERT INTO tblRolePermissions (roleKey, sectionKey, actionKey, isAllowed)
VALUES ('doctor', 'appointments', 'create', 1)
ON DUPLICATE KEY UPDATE isAllowed = VALUES(isAllowed);
