<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('billing', 'view');

$pdo = getDb();
$roleKey = currentRoleKey();
$canCreate = can('billing', 'create');
$canUpdate = can('billing', 'update');
$canDelete = can('billing', 'delete');
$message = '';
$messageType = '';
$receiptUrl = '';
$toCents = static function (string $amount): int {
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['generate_bill'])) {
        requireSectionPermission('billing', 'create');
        $appointmentId = trim($_POST['appointment_id'] ?? '');
        $amount = trim($_POST['amount'] ?? '');

        $stmt = $pdo->prepare('SELECT patientID FROM tblAppointments WHERE appointmentID = ?');
        $stmt->execute([$appointmentId]);
        $patientId = $stmt->fetchColumn();

        if (!$patientId) {
            $message = 'Select a valid appointment.';
            $messageType = 'danger';
        } elseif (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 99999999.99) {
            $message = 'Enter a valid amount greater than zero.';
            $messageType = 'danger';
        } else {
            try {
                $billId = nextId($pdo, 'tblBilling', 'billID', 'BIL');
                $pdo->prepare(
                    "INSERT INTO tblBilling (billID, patientID, appointmentID, amount, paymentStatus, dateIssued)
                     VALUES (?, ?, ?, ?, 'Unpaid', CURDATE())"
                )->execute([$billId, $patientId, $appointmentId, number_format((float) $amount, 2, '.', '')]);
                $message = 'Bill ' . $billId . ' generated.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'The bill could not be saved.';
                $messageType = 'danger';
            }
        }
    }

    if (isset($_POST['complete_payment_request'])) {
        requireSectionPermission('billing', 'update');
        $requestId = trim($_POST['complete_payment_request']);
        $reference = trim($_POST['request_reference'] ?? '');

        if (strlen($reference) > 100) {
            $message = 'The payment reference cannot exceed 100 characters.';
            $messageType = 'danger';
        } else {
            try {
                $requestLookup = $pdo->prepare(
                    'SELECT billID FROM tblPatientPaymentRequests WHERE requestID = ?'
                );
                $requestLookup->execute([$requestId]);
                $billId = $requestLookup->fetchColumn();
                if ($billId === false) {
                    $message = 'That payment request could not be found.';
                    $messageType = 'danger';
                } else {
                    $pdo->beginTransaction();
                    $billStmt = $pdo->prepare(
                        'SELECT amount, paymentStatus FROM tblBilling WHERE billID = ? FOR UPDATE'
                    );
                    $billStmt->execute([$billId]);
                    $bill = $billStmt->fetch();
                    $requestStmt = $pdo->prepare(
                        "SELECT requestID, amount, paymentMethod, status FROM tblPatientPaymentRequests
                         WHERE requestID = ? AND billID = ? FOR UPDATE"
                    );
                    $requestStmt->execute([$requestId, $billId]);
                    $request = $requestStmt->fetch();

                    if (!$bill || !$request || $request['status'] !== 'pending') {
                        $pdo->rollBack();
                        $message = 'That request is no longer pending or its bill is unavailable.';
                        $messageType = 'danger';
                    } else {
                        $paidStmt = $pdo->prepare(
                            'SELECT COALESCE(SUM(amount), 0) FROM tblPayments WHERE billID = ?'
                        );
                        $paidStmt->execute([$billId]);
                        $paidCents = $toCents((string) $paidStmt->fetchColumn());
                        $remainingCents = $toCents((string) $bill['amount']) - $paidCents;
                        if ($bill['paymentStatus'] === 'Paid' && $paidCents === 0) {
                            $remainingCents = 0;
                        }
                        $paymentCents = $toCents((string) $request['amount']);

                        if ($paymentCents <= 0 || $paymentCents > $remainingCents) {
                            $pdo->rollBack();
                            $message = 'The requested amount no longer matches the outstanding balance. Review the bill before recording payment.';
                            $messageType = 'danger';
                        } else {
                            $balanceAfter = $remainingCents - $paymentCents;
                            $paymentMethod = $request['paymentMethod'] === 'ATM card'
                                ? 'Card'
                                : $request['paymentMethod'];
                            $paymentId = 'PAY' . strtoupper(bin2hex(random_bytes(3))) . strtoupper(substr(bin2hex(random_bytes(2)), 0, 1));
                            $pdo->prepare(
                                'INSERT INTO tblPayments (paymentID, billID, amount, balanceAfter, paymentMethod, reference, receivedBy)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)'
                            )->execute([
                                $paymentId,
                                $billId,
                                number_format($paymentCents / 100, 2, '.', ''),
                                number_format($balanceAfter / 100, 2, '.', ''),
                                $paymentMethod,
                                $reference !== '' ? $reference : null,
                                currentUser()['id'],
                            ]);
                            $pdo->prepare('UPDATE tblBilling SET paymentStatus = ? WHERE billID = ?')
                                ->execute([$balanceAfter === 0 ? 'Paid' : 'Unpaid', $billId]);
                            $pdo->prepare(
                                "UPDATE tblPatientPaymentRequests
                                 SET status = 'completed', paymentID = ?, reviewedBy = ?, reviewedAt = CURRENT_TIMESTAMP
                                 WHERE requestID = ?"
                            )->execute([$paymentId, currentUser()['id'], $requestId]);
                            $pdo->commit();
                            $message = 'Verified payment recorded. Print the receipt for the patient.';
                            $messageType = 'success';
                            $receiptUrl = url('/billing/receipt.php?payment=' . rawurlencode($paymentId));
                        }
                    }
                }
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Patient payment request completion failed: ' . $error->getMessage());
                $message = 'The payment request could not be completed.';
                $messageType = 'danger';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
    }

    if (isset($_POST['reject_payment_request'])) {
        requireSectionPermission('billing', 'update');
        $requestId = trim($_POST['reject_payment_request']);
        $reject = $pdo->prepare(
            "UPDATE tblPatientPaymentRequests
             SET status = 'rejected', reviewedBy = ?, reviewedAt = CURRENT_TIMESTAMP
             WHERE requestID = ? AND status = 'pending'"
        );
        $reject->execute([currentUser()['id'], $requestId]);
        if ($reject->rowCount() === 1) {
            $message = 'Payment request rejected. The patient can submit another request if needed.';
            $messageType = 'success';
        } else {
            $message = 'That payment request is no longer pending.';
            $messageType = 'danger';
        }
    }

    if (isset($_POST['record_payment'])) {
        requireSectionPermission('billing', 'update');
        $billId = trim($_POST['bill_id'] ?? '');
        $paymentAmount = trim($_POST['payment_amount'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $reference = trim($_POST['reference'] ?? '');
        $validAmount = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $paymentAmount) === 1
            && $toCents($paymentAmount) > 0;

        if (!$validAmount) {
            $message = 'Enter a payment amount greater than zero with no more than two decimal places.';
            $messageType = 'danger';
        } elseif (!in_array($paymentMethod, ['Cash', 'Card', 'Bank transfer'], true)) {
            $message = 'Choose a valid payment method.';
            $messageType = 'danger';
        } elseif (strlen($reference) > 100) {
            $message = 'The payment reference cannot exceed 100 characters.';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $billStmt = $pdo->prepare(
                    'SELECT amount, paymentStatus FROM tblBilling WHERE billID = ? FOR UPDATE'
                );
                $billStmt->execute([$billId]);
                $bill = $billStmt->fetch();
                if (!$bill) {
                    $pdo->rollBack();
                    $message = 'That bill could not be found.';
                    $messageType = 'danger';
                } else {
                    $paidStmt = $pdo->prepare(
                        'SELECT COALESCE(SUM(amount), 0) FROM tblPayments WHERE billID = ?'
                    );
                    $paidStmt->execute([$billId]);
                    $paidAmount = $paidStmt->fetchColumn();
                    $paidCents = $toCents((string) $paidAmount);
                    $remainingCents = $toCents((string) $bill['amount']) - $paidCents;
                    if ($bill['paymentStatus'] === 'Paid' && $paidCents === 0) {
                        $remainingCents = 0;
                    }
                    $paymentCents = $toCents($paymentAmount);

                    if ($paymentCents > $remainingCents) {
                        $pdo->rollBack();
                        $message = 'The payment exceeds the remaining balance of ' . number_format(max(0, $remainingCents) / 100, 2) . '.';
                        $messageType = 'danger';
                    } else {
                        $newBalanceCents = $remainingCents - $paymentCents;
                        $paymentId = 'PAY' . strtoupper(bin2hex(random_bytes(3))) . strtoupper(substr(bin2hex(random_bytes(2)), 0, 1));
                        $pdo->prepare(
                            'INSERT INTO tblPayments (paymentID, billID, amount, balanceAfter, paymentMethod, reference, receivedBy)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $paymentId,
                            $billId,
                            number_format($paymentCents / 100, 2, '.', ''),
                            number_format($newBalanceCents / 100, 2, '.', ''),
                            $paymentMethod,
                            $reference !== '' ? $reference : null,
                            currentUser()['id'],
                        ]);

                        $pdo->prepare('UPDATE tblBilling SET paymentStatus = ? WHERE billID = ?')
                            ->execute([$newBalanceCents === 0 ? 'Paid' : 'Unpaid', $billId]);
                        $pdo->commit();
                        $message = 'Payment recorded. Print the receipt for the patient.';
                        $messageType = 'success';
                        $receiptUrl = url('/billing/receipt.php?payment=' . rawurlencode($paymentId));
                    }
                }
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Payment recording failed: ' . $error->getMessage());
                $message = 'The payment could not be recorded. Check the bill and try again.';
                $messageType = 'danger';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
    }

    if (isset($_POST['delete_bill'])) {
        requireSectionPermission('billing', 'delete');
        try {
            $pdo->beginTransaction();
            $billId = trim($_POST['delete_bill']);
            $billLock = $pdo->prepare('SELECT billID FROM tblBilling WHERE billID = ? FOR UPDATE');
            $billLock->execute([$billId]);
            if (!$billLock->fetchColumn()) {
                $pdo->rollBack();
                $message = 'That bill could not be found.';
                $messageType = 'danger';
            } else {
                $paymentCheck = $pdo->prepare(
                    'SELECT
                       (SELECT COUNT(*) FROM tblPayments WHERE billID = ?) +
                       (SELECT COUNT(*) FROM tblPatientPaymentRequests WHERE billID = ?)'
                );
                $paymentCheck->execute([$billId, $billId]);
                if ((int) $paymentCheck->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $message = 'A bill with payment history or patient payment requests cannot be deleted.';
                    $messageType = 'danger';
                } else {
                    $pdo->prepare('DELETE FROM tblBilling WHERE billID = ?')->execute([$billId]);
                    $pdo->commit();
                    $message = 'Bill deleted.';
                    $messageType = 'success';
                }
            }
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Bill deletion failed: ' . $error->getMessage());
            $message = 'The bill could not be deleted.';
            $messageType = 'danger';
        }
    }
}

$billable = $pdo->query(
    "SELECT a.appointmentID, a.appointmentDate, p.fullName
     FROM tblAppointments a
     JOIN tblPatients p ON p.patientID = a.patientID
     LEFT JOIN tblBilling b ON b.appointmentID = a.appointmentID
     WHERE b.billID IS NULL AND a.status <> 'Cancelled'
     ORDER BY a.appointmentDate DESC"
)->fetchAll();

$bills = $pdo->query(
    "SELECT b.*, p.fullName AS patientName, a.appointmentDate,
            CASE
              WHEN b.paymentStatus = 'Paid' AND payment_totals.paidAmount IS NULL THEN b.amount
              ELSE COALESCE(payment_totals.paidAmount, 0.00)
            END AS paidAmount
     FROM tblBilling b
     JOIN tblPatients p ON p.patientID = b.patientID
     JOIN tblAppointments a ON a.appointmentID = b.appointmentID
     LEFT JOIN (
         SELECT billID, SUM(amount) AS paidAmount FROM tblPayments GROUP BY billID
     ) payment_totals ON payment_totals.billID = b.billID
     ORDER BY b.billID DESC"
)->fetchAll();
$paymentRows = $pdo->query(
    'SELECT paymentID, billID, amount, paymentMethod, receivedAt
     FROM tblPayments ORDER BY receivedAt DESC, paymentID DESC'
)->fetchAll();
$patientPaymentRequests = $canUpdate
    ? $pdo->query(
        "SELECT r.requestID, r.billID, r.amount, r.paymentMethod, r.requestedAt,
                p.fullName AS patientName
         FROM tblPatientPaymentRequests r
         JOIN tblPatients p ON p.patientID = r.patientID
         WHERE r.status = 'pending'
         ORDER BY r.requestedAt, r.requestID"
    )->fetchAll()
    : [];
$paymentsByBill = [];
foreach ($paymentRows as $paymentRow) {
    $paymentsByBill[$paymentRow['billID']][] = $paymentRow;
}

$pageTitle = 'Billing';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'billing'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Finance</p>
          <h2>Billing</h2>
        </div>
        <a href="<?php echo e(url(roleDashboardPath($roleKey))); ?>" class="button-link secondary">Back</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?>
          <?php if ($receiptUrl !== ''): ?><a href="<?php echo e($receiptUrl); ?>" target="_blank" rel="noopener">Open and print receipt</a><?php endif; ?>
        </div></div>
      <?php endif; ?>

      <?php if ($canUpdate): ?>
      <section class="panel">
        <div class="panel-header"><h3>Patient payment requests</h3></div>
        <p class="hint">Only confirm after you have actually received the cash or verified the bank/card payment. Confirming records the payment and updates the bill.</p>
        <?php if ($patientPaymentRequests === []): ?>
          <div class="empty-state">No patient payment requests are awaiting verification.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Request</th><th>Patient</th><th>Bill</th><th>Amount</th><th>Method</th><th>Requested</th><th>Verification</th></tr></thead>
              <tbody>
                <?php foreach ($patientPaymentRequests as $request): ?>
                  <tr>
                    <td><?php echo e($request['requestID']); ?></td>
                    <td><?php echo e($request['patientName']); ?></td>
                    <td><?php echo e($request['billID']); ?></td>
                    <td><?php echo e(number_format((float) $request['amount'], 2)); ?></td>
                    <td><?php echo e($request['paymentMethod']); ?></td>
                    <td><?php echo e($request['requestedAt']); ?></td>
                    <td>
                      <div class="patient-request-actions">
                        <form method="POST" class="patient-payment-form" onsubmit="return confirm('Confirm that you received or verified this payment?');">
                          <?php echo csrf_field(); ?>
                          <label>Reference (optional)
                            <input type="text" name="request_reference" maxlength="100">
                          </label>
                          <button type="submit" name="complete_payment_request" value="<?php echo e($request['requestID']); ?>">Verify &amp; record</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Reject this payment request?');">
                          <?php echo csrf_field(); ?>
                          <button type="submit" name="reject_payment_request" value="<?php echo e($request['requestID']); ?>" class="button-link secondary small">Reject request</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($canCreate): ?>
      <section class="form-card">
        <h3>Generate Bill</h3>
        <p class="hint">Only appointments that do not yet have a bill are listed.</p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <label>
            Appointment
            <select name="appointment_id" required>
              <option value="">Select appointment</option>
              <?php foreach ($billable as $b): ?>
                <option value="<?php echo e($b['appointmentID']); ?>"><?php echo e($b['appointmentID'] . ' - ' . $b['fullName'] . ' (' . $b['appointmentDate'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Amount
            <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
          </label>
          <div class="form-actions">
            <button type="submit" name="generate_bill">Generate Bill</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-header">
          <h3>Bills</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Bill ID</th>
              <th>Patient</th>
              <th>Appointment</th>
              <th>Amount</th>
              <th>Paid</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Date issued</th>
              <?php if ($canUpdate || $canDelete): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bills)): ?>
              <tr>
                <td colspan="<?php echo ($canUpdate || $canDelete) ? 9 : 8; ?>">
                  <div class="empty-state">
                    <span class="emoji">💳</span>
                    No bills generated yet.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($bills as $bill): ?>
                <tr>
                  <td><?php echo e($bill['billID']); ?></td>
                  <td class="cell-primary"><?php echo e($bill['patientName']); ?></td>
                  <td><?php echo e($bill['appointmentID'] . ' (' . $bill['appointmentDate'] . ')'); ?></td>
                  <td><?php echo e(number_format((float) $bill['amount'], 2)); ?></td>
                  <?php $balance = max(0, (float) $bill['amount'] - (float) $bill['paidAmount']); ?>
                  <td><?php echo e(number_format((float) $bill['paidAmount'], 2)); ?></td>
                  <td><?php echo e(number_format($balance, 2)); ?></td>
                  <td><span class="status <?php echo e(strtolower($bill['paymentStatus'])); ?>"><?php echo e($bill['paymentStatus']); ?></span></td>
                  <td><?php echo e($bill['dateIssued'] ?: 'N/A'); ?></td>
                  <?php if ($canUpdate || $canDelete): ?>
                  <td>
                    <div class="list-actions">
                      <?php if ($canUpdate && $balance > 0): ?>
                      <form method="POST" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="bill_id" value="<?php echo e($bill['billID']); ?>">
                        <label>Receive payment
                          <input type="number" name="payment_amount" min="0.01" max="<?php echo e(number_format($balance, 2, '.', '')); ?>" step="0.01" value="<?php echo e(number_format($balance, 2, '.', '')); ?>" required>
                        </label>
                        <label>Method
                          <select name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Bank transfer">Bank transfer</option>
                          </select>
                        </label>
                        <label>Reference (optional)
                          <input type="text" name="reference" maxlength="100">
                        </label>
                        <button type="submit" name="record_payment" value="1" class="button-link small">Record &amp; receipt</button>
                      </form>
                      <?php endif; ?>
                      <?php if ($canDelete): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this bill?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_bill" value="<?php echo e($bill['billID']); ?>" class="button-link secondary small">Delete</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php if (!empty($paymentsByBill[$bill['billID']])): ?>
                  <?php foreach ($paymentsByBill[$bill['billID']] as $payment): ?>
                    <tr>
                      <td colspan="2">Receipt <?php echo e($payment['paymentID']); ?></td>
                      <td><?php echo e($payment['paymentMethod']); ?></td>
                      <td><?php echo e(number_format((float) $payment['amount'], 2)); ?></td>
                      <td colspan="3"><?php echo e($payment['receivedAt']); ?></td>
                      <td colspan="<?php echo $canUpdate || $canDelete ? 2 : 1; ?>">
                        <a class="button-link secondary small" href="<?php echo e(url('/billing/receipt.php?payment=' . rawurlencode($payment['paymentID']))); ?>" target="_blank" rel="noopener">View receipt</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
