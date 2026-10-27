SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblAppointments'
      AND COLUMN_NAME = 'bookedByDoctorID'
  ),
  'SELECT 1',
  'ALTER TABLE tblAppointments ADD COLUMN bookedByDoctorID VARCHAR(10) NULL AFTER attendedByDoctorID'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblAppointments'
      AND COLUMN_NAME = 'bookedByDoctorID'
      AND REFERENCED_TABLE_NAME = 'tblDoctors'
  ),
  'SELECT 1',
  'ALTER TABLE tblAppointments ADD CONSTRAINT fk_appt_booked_by FOREIGN KEY (bookedByDoctorID) REFERENCES tblDoctors (doctorID) ON DELETE SET NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
