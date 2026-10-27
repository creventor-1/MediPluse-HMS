-- =====================================================================
-- MediPulse HMS : database schema aligned with Chapter Three, Section 3.4
-- Tables 3.1 to 3.7 (tblPatients ... tblBilling)
-- Run in phpMyAdmin (SQL tab) against the medipulse_hms database.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS medipulse_hms
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE medipulse_hms;

-- ---------------------------------------------------------------------
-- Table 3.1: tblPatients
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblPatients (
  patientID        VARCHAR(10)  NOT NULL,
  fullName         VARCHAR(100) NOT NULL,
  gender           VARCHAR(10)  NOT NULL,
  dateOfBirth      DATE         NOT NULL,
  phoneNumber      VARCHAR(15)  NULL,
  address          VARCHAR(150) NULL,
  registrationDate DATE         NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (patientID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.2: tblUsers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblUsers (
  userID       VARCHAR(10)  NOT NULL,
  username     VARCHAR(50)  NOT NULL,
  email        VARCHAR(254) NULL,
  displayName  VARCHAR(100) NULL,
  passwordHash VARCHAR(255) NOT NULL,
  role         VARCHAR(20)  NOT NULL,
  accountStatus VARCHAR(20) NOT NULL DEFAULT 'active',
  sessionVersion INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (userID),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT chk_users_role CHECK
    (role IN ('Doctor','Receptionist','Lab Technician','Billing Officer','Administrator'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.3: tblDoctors   (one-to-one with tblUsers, per class diagram)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblDoctors (
  doctorID    VARCHAR(10)  NOT NULL,
  fullName    VARCHAR(100) NOT NULL,
  specialty   VARCHAR(50)  NULL,
  phoneNumber VARCHAR(15)  NULL,
  userID      VARCHAR(10)  NULL,
  PRIMARY KEY (doctorID),
  UNIQUE KEY uq_doctors_user (userID),
  CONSTRAINT fk_doctors_user FOREIGN KEY (userID) REFERENCES tblUsers (userID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.4: tblStaff     (one-to-one with tblUsers, per class diagram)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblStaff (
  staffID     VARCHAR(10)  NOT NULL,
  fullName    VARCHAR(100) NOT NULL,
  designation VARCHAR(50)  NULL,
  userID      VARCHAR(10)  NULL,
  PRIMARY KEY (staffID),
  UNIQUE KEY uq_staff_user (userID),
  CONSTRAINT fk_staff_user FOREIGN KEY (userID) REFERENCES tblUsers (userID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.5: tblAppointments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblAppointments (
  appointmentID   VARCHAR(10) NOT NULL,
  patientID       VARCHAR(10) NOT NULL,
  doctorID        VARCHAR(10) NOT NULL,
  appointmentDate DATE        NOT NULL,
  appointmentTime TIME        NOT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'Booked',
  bookedByDoctorID VARCHAR(10) NULL,
  PRIMARY KEY (appointmentID),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID),
  CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctorID)  REFERENCES tblDoctors  (doctorID),
  CONSTRAINT fk_appt_booked_by FOREIGN KEY (bookedByDoctorID) REFERENCES tblDoctors (doctorID) ON DELETE SET NULL,
  CONSTRAINT chk_appt_status CHECK (status IN ('Booked','Completed','Cancelled'))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.6: tblLabResults
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblLabResults (
  labResultID  VARCHAR(10)  NOT NULL,
  patientID    VARCHAR(10)  NOT NULL,
  requestedBy  VARCHAR(10)  NOT NULL,
  testType     VARCHAR(100) NOT NULL,
  resultValue  VARCHAR(255) NULL,
  dateRecorded DATE         NULL,
  PRIMARY KEY (labResultID),
  CONSTRAINT fk_lab_patient FOREIGN KEY (patientID)   REFERENCES tblPatients (patientID),
  CONSTRAINT fk_lab_doctor  FOREIGN KEY (requestedBy) REFERENCES tblDoctors  (doctorID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table 3.7: tblBilling
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tblBilling (
  billID        VARCHAR(10)   NOT NULL,
  patientID     VARCHAR(10)   NOT NULL,
  appointmentID VARCHAR(10)   NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  paymentStatus VARCHAR(20)   NOT NULL DEFAULT 'Unpaid',
  dateIssued    DATE          NULL,
  PRIMARY KEY (billID),
  CONSTRAINT fk_bill_patient FOREIGN KEY (patientID)     REFERENCES tblPatients     (patientID),
  CONSTRAINT fk_bill_appt    FOREIGN KEY (appointmentID) REFERENCES tblAppointments (appointmentID),
  CONSTRAINT chk_bill_status CHECK (paymentStatus IN ('Paid','Unpaid'))
) ENGINE=InnoDB;

-- Patient portal ownership is granted explicitly by an authorized reviewer.
CREATE TABLE IF NOT EXISTS tblPatientAccounts (
  userID      VARCHAR(10) NOT NULL,
  patientID   VARCHAR(10) NOT NULL,
  accountStatus VARCHAR(20) NOT NULL DEFAULT 'pending',
  approvedBy  VARCHAR(10) NULL,
  createdAt   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approvedAt  DATETIME NULL,
  PRIMARY KEY (userID, patientID),
  KEY idx_patient_account_patient (patientID, accountStatus),
  CONSTRAINT fk_patient_account_user FOREIGN KEY (userID) REFERENCES tblUsers (userID) ON DELETE CASCADE,
  CONSTRAINT fk_patient_account_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID) ON DELETE CASCADE,
  CONSTRAINT fk_patient_account_approver FOREIGN KEY (approvedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL,
  CONSTRAINT chk_patient_account_status CHECK (accountStatus IN ('pending','active','revoked'))
) ENGINE=InnoDB;

-- A shared encounter anchor links appointments, clinicians, and future clinical records.
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

-- ---------------------------------------------------------------------
-- CLEAN-UP (run ONLY after the PHP code has been switched to the tbl*
-- tables and you have copied any data you want to keep).
-- These four tables are not in Chapter Three and are the source of the
-- mismatch seen in phpMyAdmin.
-- ---------------------------------------------------------------------
-- DROP TABLE IF EXISTS appointments;
-- DROP TABLE IF EXISTS doctors;
-- DROP TABLE IF EXISTS patients;
-- DROP TABLE IF EXISTS users;
