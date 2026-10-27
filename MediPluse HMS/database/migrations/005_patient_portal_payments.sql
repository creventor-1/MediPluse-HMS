-- Apply after 004_billing_payments.sql.
-- Patient payment selections are requests only; staff must verify and record funds.
USE medipulse_hms;

CREATE TABLE IF NOT EXISTS tblPatientPaymentRequests (
  requestID   VARCHAR(12) NOT NULL,
  userID      VARCHAR(10) NOT NULL,
  patientID   VARCHAR(10) NOT NULL,
  billID      VARCHAR(10) NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  paymentMethod VARCHAR(20) NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  paymentID   VARCHAR(10) NULL,
  reviewedBy  VARCHAR(10) NULL,
  requestedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewedAt  DATETIME NULL,
  PRIMARY KEY (requestID),
  KEY idx_patient_payment_user_status (userID, status, requestedAt),
  KEY idx_patient_payment_bill_status (billID, status),
  CONSTRAINT fk_patient_payment_user FOREIGN KEY (userID) REFERENCES tblUsers (userID),
  CONSTRAINT fk_patient_payment_patient FOREIGN KEY (patientID) REFERENCES tblPatients (patientID),
  CONSTRAINT fk_patient_payment_bill FOREIGN KEY (billID) REFERENCES tblBilling (billID),
  CONSTRAINT fk_patient_payment_payment FOREIGN KEY (paymentID) REFERENCES tblPayments (paymentID) ON DELETE SET NULL,
  CONSTRAINT fk_patient_payment_reviewer FOREIGN KEY (reviewedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL,
  CONSTRAINT chk_patient_payment_amount CHECK (amount > 0),
  CONSTRAINT chk_patient_payment_method CHECK (paymentMethod IN ('Cash','Bank transfer','ATM card')),
  CONSTRAINT chk_patient_payment_status CHECK (status IN ('pending','completed','rejected','cancelled'))
) ENGINE=InnoDB;
