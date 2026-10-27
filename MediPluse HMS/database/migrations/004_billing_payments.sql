-- Apply after 003_signup_role_requests.sql.
-- Records manually received payments and links printable receipts to each payment.
USE medipulse_hms;

CREATE TABLE IF NOT EXISTS tblPayments (
  paymentID     VARCHAR(10) NOT NULL,
  billID        VARCHAR(10) NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  balanceAfter  DECIMAL(10,2) NOT NULL,
  paymentMethod VARCHAR(20) NOT NULL,
  reference     VARCHAR(100) NULL,
  receivedBy    VARCHAR(10) NULL,
  receivedAt    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (paymentID),
  KEY idx_payment_bill_date (billID, receivedAt),
  CONSTRAINT fk_payment_bill FOREIGN KEY (billID) REFERENCES tblBilling (billID),
  CONSTRAINT fk_payment_receiver FOREIGN KEY (receivedBy) REFERENCES tblUsers (userID) ON DELETE SET NULL,
  CONSTRAINT chk_payment_amount CHECK (amount > 0),
  CONSTRAINT chk_payment_method CHECK (paymentMethod IN ('Cash','Card','Bank transfer'))
) ENGINE=InnoDB;
