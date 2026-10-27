-- Apply after 001_permissions_auth.sql.
-- These additive tables establish shared ownership and encounter relationships.
USE medipulse_hms;

CREATE TABLE IF NOT EXISTS tblPatientAccounts (
  userID       VARCHAR(10) NOT NULL,
  patientID    VARCHAR(10) NOT NULL,
  accountStatus VARCHAR(20) NOT NULL DEFAULT 'pending',
  approvedBy   VARCHAR(10) NULL,
  createdAt    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approvedAt   DATETIME NULL,
  PRIMARY KEY (userID, patientID),
  KEY idx_patient_account_patient (patientID, accountStatus),
  CONSTRAINT fk_patient_account_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE CASCADE,
  CONSTRAINT fk_patient_account_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID) ON DELETE CASCADE,
  CONSTRAINT fk_patient_account_approver FOREIGN KEY (approvedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL,
  CONSTRAINT chk_patient_account_status CHECK (accountStatus IN ('pending','active','revoked'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tblEncounters (
  encounterID   VARCHAR(10) NOT NULL,
  patientID     VARCHAR(10) NOT NULL,
  appointmentID VARCHAR(10) NULL,
  doctorID      VARCHAR(10) NULL,
  encounterType VARCHAR(20) NOT NULL DEFAULT 'outpatient',
  status        VARCHAR(20) NOT NULL DEFAULT 'planned',
  createdBy     VARCHAR(10) NOT NULL,
  openedAt      DATETIME NULL,
  closedAt      DATETIME NULL,
  createdAt     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (encounterID),
  UNIQUE KEY uq_encounter_appointment (appointmentID),
  KEY idx_encounter_patient_date (patientID, createdAt),
  KEY idx_encounter_doctor_status (doctorID, status),
  CONSTRAINT fk_encounter_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID),
  CONSTRAINT fk_encounter_appointment FOREIGN KEY (appointmentID) REFERENCES tblAppointments (appointmentID) ON DELETE SET NULL,
  CONSTRAINT fk_encounter_doctor FOREIGN KEY (doctorID) REFERENCES tblDoctors (doctorID) ON DELETE SET NULL,
  CONSTRAINT fk_encounter_creator FOREIGN KEY (createdBy) REFERENCES tblUsers (userID),
  CONSTRAINT chk_encounter_type CHECK (encounterType IN ('outpatient','walk_in','emergency','inpatient','referral')),
  CONSTRAINT chk_encounter_status CHECK (status IN ('planned','open','signed','closed','cancelled'))
) ENGINE=InnoDB;
