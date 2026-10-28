<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('billing', 'view');

$paymentId = trim($_GET['payment'] ?? '');
$statement = getDb()->prepare(
    'SELECT pay.paymentID, pay.amount, pay.balanceAfter, pay.paymentMethod, pay.reference, pay.receivedAt,
            b.billID, b.amount AS billAmount, b.paymentStatus, b.patientID,
            p.fullName AS patientName, a.appointmentID, a.appointmentDate,
            COALESCE(u.displayName, u.username, \'Former staff member\') AS receivedBy
     FROM tblPayments pay
     JOIN tblBilling b ON b.billID = pay.billID
     JOIN tblPatients p ON p.patientID = b.patientID
     JOIN tblAppointments a ON a.appointmentID = b.appointmentID
     LEFT JOIN tblUsers u ON u.userID = pay.receivedBy
     WHERE pay.paymentID = ?'
);
$statement->execute([$paymentId]);
$receipt = $statement->fetch();
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

$balance = (float) $receipt['balanceAfter'];
$totalPaid = max(0, (float) $receipt['billAmount'] - $balance);

$pageTitle = 'Receipt ' . $receipt['paymentID'];
require __DIR__ . '/../partials/head.php';
?>
<body class="receipt-body">
  <main class="receipt-card">
    <header class="receipt-heading">
      <div>
        <p class="eyebrow">Payment receipt</p>
        <h1><?php echo e(APP_NAME); ?></h1>
        <p>Hospital Management System</p>
      </div>
      <strong class="receipt-number"><?php echo e($receipt['paymentID']); ?></strong>
    </header>

    <section class="receipt-details" aria-label="Receipt details">
      <div><span>Patient</span><strong><?php echo e($receipt['patientName']); ?></strong></div>
      <div><span>Patient ID</span><strong><?php echo e($receipt['patientID']); ?></strong></div>
      <div><span>Bill number</span><strong><?php echo e($receipt['billID']); ?></strong></div>
      <div><span>Appointment</span><strong><?php echo e($receipt['appointmentID']); ?></strong></div>
      <div><span>Payment date</span><strong><?php echo e(date('M d, Y H:i', strtotime($receipt['receivedAt']))); ?></strong></div>
      <div><span>Received by</span><strong><?php echo e($receipt['receivedBy']); ?></strong></div>
      <div><span>Payment method</span><strong><?php echo e($receipt['paymentMethod']); ?></strong></div>
      <?php if (!empty($receipt['reference'])): ?>
        <div><span>Reference</span><strong><?php echo e($receipt['reference']); ?></strong></div>
      <?php endif; ?>
    </section>

    <section class="receipt-amounts">
      <div><span>Bill total</span><strong><?php echo e(number_format((float) $receipt['billAmount'], 2)); ?></strong></div>
      <div><span>This payment</span><strong><?php echo e(number_format((float) $receipt['amount'], 2)); ?></strong></div>
      <div><span>Total received</span><strong><?php echo e(number_format($totalPaid, 2)); ?></strong></div>
      <div class="receipt-balance"><span>Balance after this payment</span><strong><?php echo e(number_format($balance, 2)); ?></strong></div>
    </section>

    <p class="receipt-note">This receipt confirms that the payment above was recorded by hospital staff.</p>
    <div class="receipt-actions">
      <button type="button" onclick="window.print()"><?php echo icon('printer', '', 16); ?> Print receipt</button>
      <a class="button-link secondary" href="<?php echo e(url('/billing/index.php')); ?>"><?php echo icon('chevron-left', '', 16); ?> Back to billing</a>
    </div>
  </main>
</body>
</html>
