<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireRole(['admin', 'billing_officer']);

$pdo = getDb();
$roleKey = currentRoleKey();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['generate_bill'])) {
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

    if (isset($_POST['set_status'])) {
        $newStatus = ($_POST['new_status'] ?? '') === 'Paid' ? 'Paid' : 'Unpaid';
        $pdo->prepare('UPDATE tblBilling SET paymentStatus = ? WHERE billID = ?')
            ->execute([$newStatus, trim($_POST['set_status'])]);
        $message = 'Payment status updated to ' . $newStatus . '.';
        $messageType = 'success';
    }

    if ($roleKey === 'admin' && isset($_POST['delete_bill'])) {
        $pdo->prepare('DELETE FROM tblBilling WHERE billID = ?')->execute([trim($_POST['delete_bill'])]);
        $message = 'Bill deleted.';
        $messageType = 'success';
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
    'SELECT b.*, p.fullName AS patientName, a.appointmentDate
     FROM tblBilling b
     JOIN tblPatients p ON p.patientID = b.patientID
     JOIN tblAppointments a ON a.appointmentID = b.appointmentID
     ORDER BY b.billID DESC'
)->fetchAll();

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
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

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
              <th>Status</th>
              <th>Date issued</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bills)): ?>
              <tr>
                <td colspan="7">
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
                  <td><span class="status <?php echo e(strtolower($bill['paymentStatus'])); ?>"><?php echo e($bill['paymentStatus']); ?></span></td>
                  <td><?php echo e($bill['dateIssued'] ?: 'N/A'); ?></td>
                  <td>
                    <div class="list-actions">
                      <form method="POST" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="new_status" value="<?php echo $bill['paymentStatus'] === 'Paid' ? 'Unpaid' : 'Paid'; ?>">
                        <button type="submit" name="set_status" value="<?php echo e($bill['billID']); ?>" class="button-link small">
                          <?php echo $bill['paymentStatus'] === 'Paid' ? 'Mark unpaid' : 'Record payment'; ?>
                        </button>
                      </form>
                      <?php if ($roleKey === 'admin'): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this bill?');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_bill" value="<?php echo e($bill['billID']); ?>" class="button-link secondary small">Delete</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
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
