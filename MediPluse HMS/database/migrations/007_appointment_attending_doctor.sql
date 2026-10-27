ALTER TABLE tblAppointments
  ADD COLUMN attendedByDoctorID VARCHAR(10) NULL AFTER status,
  ADD CONSTRAINT fk_appt_attended_by
    FOREIGN KEY (attendedByDoctorID) REFERENCES tblDoctors (doctorID) ON DELETE SET NULL;
