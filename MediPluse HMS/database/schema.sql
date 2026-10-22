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
  passwordHash VARCHAR(255) NOT NULL,
  role         VARCHAR(20)  NOT NULL,
  PRIMARY KEY (userID),
  UNIQUE KEY uq_users_username (username),
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
  PRIMARY KEY (appointmentID),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID),
  CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctorID)  REFERENCES tblDoctors  (doctorID),
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
