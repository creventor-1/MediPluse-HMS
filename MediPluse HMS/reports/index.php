<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireSectionPermission('reports', 'view');

$pdo = getDb();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';

/** Builds " AND column >= ? AND column <= ?" plus its parameters. */
$range = static function (string $column) use ($from, $to): array {
    $sql = '';
    $params = [];
    if ($from !== '') {
        $sql .= " AND $column >= ?";
        $params[] = $from;
    }
    if ($to !== '') {
        $sql .= " AND $column <= ?";
        $params[] = $to;
    }
    return [$sql, $params];
};

$rows = static function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$count = static function (string $table) use ($pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
};

[$apptSql, $apptParams] = $range('appointmentDate');
[$billSql, $billParams] = $range('dateIssued');

$canViewAppointments = can('appointments', 'view');
$canViewDoctors = can('doctors', 'view');
$canViewPatients = can('patients', 'view');
$canViewStaff = can('staff', 'view');
$canViewLab = can('lab', 'view');
$canViewBilling = can('billing', 'view');

$apptByStatus = $canViewAppointments
    ? $rows("SELECT status, COUNT(*) AS total FROM tblAppointments WHERE 1=1 $apptSql GROUP BY status ORDER BY status", $apptParams)
    : [];

$apptByDoctor = $canViewAppointments && $canViewDoctors
    ? $rows(
        "SELECT d.fullName, COUNT(a.appointmentID) AS total
         FROM tblDoctors d
         LEFT JOIN tblAppointments a ON a.doctorID = d.doctorID " . str_replace('appointmentDate', 'a.appointmentDate', $apptSql) . "
         GROUP BY d.doctorID, d.fullName ORDER BY total DESC, d.fullName",
        $apptParams
    )
    : [];

$billing = $canViewBilling
    ? $rows(
        "SELECT paymentStatus, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
         FROM tblBilling WHERE 1=1 $billSql GROUP BY paymentStatus",
        $billParams
    )
    : [];
$billTotals = ['Paid' => ['total' => 0, 'amount' => 0], 'Unpaid' => ['total' => 0, 'amount' => 0]];
foreach ($billing as $b) {
    $billTotals[$b['paymentStatus']] = ['total' => (int) $b['total'], 'amount' => (float) $b['amount']];
}

$labPending = $canViewLab
    ? (int) $pdo->query('SELECT COUNT(*) FROM tblLabResults WHERE resultValue IS NULL')->fetchColumn()
    : null;
$labDone = $canViewLab
    ? (int) $pdo->query('SELECT COUNT(*) FROM tblLabResults WHERE resultValue IS NOT NULL')->fetchColumn()
    : null;
$hasReportData = $canViewAppointments || $canViewDoctors || $canViewPatients
    || $canViewStaff || $canViewLab || $canViewBilling;

$periodLabel = ($from !== '' || $to !== '')
    ? ($from !== '' ? $from : 'the beginning') . ' to ' . ($to !== '' ? $to : 'today')
    : 'All time';

$pageTitle = 'Reports';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'reports'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Administration</p>
          <h2>Administrative Report</h2>
        </div>
        <button type="button" class="button-link secondary" onclick="window.print()"><?php echo icon('printer', '', 16); ?> Print report</button>
      </div>

      <?php if ($hasReportData): ?>
      <section class="form-card">
        <h3>Report Period</h3>
        <p class="hint">Applies to appointments and billing. Currently showing: <?php echo e($periodLabel); ?>.</p>
        <form method="GET" class="form-grid">
          <label>
            From
            <input type="date" name="from" value="<?php echo e($from); ?>">
          </label>
          <label>
            To
            <input type="date" name="to" value="<?php echo e($to); ?>">
          </label>
          <div class="form-actions">
            <button type="submit"><?php echo icon('filter', '', 16); ?> Generate</button>
            <a href="<?php echo e(url('/reports/index.php')); ?>" class="button-link secondary">Reset</a>
          </div>
        </form>
      </section>
      <?php else: ?>
      <section class="panel"><p class="empty-state">No source-section permissions are assigned, so no protected summaries are displayed.</p></section>
      <?php endif; ?>

      <div class="stats-grid">
        <?php if ($canViewPatients): ?>
        <div class="stat-card"><div class="stat-icon"><?php echo icon('users'); ?></div><div><span class="label">Registered patients</span><strong><?php echo e($count('tblPatients')); ?></strong></div></div>
        <?php endif; ?>
        <?php if ($canViewDoctors): ?>
        <div class="stat-card"><div class="stat-icon"><?php echo icon('stethoscope'); ?></div><div><span class="label">Doctors</span><strong><?php echo e($count('tblDoctors')); ?></strong></div></div>
        <?php endif; ?>
        <?php if ($canViewStaff): ?>
        <div class="stat-card"><div class="stat-icon"><?php echo icon('user-plus'); ?></div><div><span class="label">Staff accounts</span><strong><?php echo e($count('tblStaff')); ?></strong></div></div>
        <?php endif; ?>
        <?php if ($canViewLab): ?>
        <div class="stat-card"><div class="stat-icon"><?php echo icon('flask-conical'); ?></div><div><span class="label">Lab tests (pending / done)</span><strong><?php echo e($labPending . ' / ' . $labDone); ?></strong></div></div>
        <?php endif; ?>
      </div>

      <?php if ($canViewAppointments): ?>
      <section class="panel">
        <div class="panel-header"><h3>Appointments by Status</h3></div>
        <table>
          <thead><tr><th>Status</th><th>Total</th></tr></thead>
          <tbody>
            <?php if (empty($apptByStatus)): ?>
              <tr><td colspan="2"><?php echo renderEmptyState('calendar', 'No appointments in this period', 'Try expanding the date range filter.'); ?></td></tr>
            <?php else: ?>
              <?php foreach ($apptByStatus as $row): ?>
                <tr><td><?php echo statusBadge($row['status']); ?></td><td><?php echo e($row['total']); ?></td></tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
      <?php endif; ?>

      <?php if ($canViewAppointments && $canViewDoctors): ?>
      <section class="panel">
        <div class="panel-header"><h3>Appointments per Doctor</h3></div>
        <table>
          <thead><tr><th>Doctor</th><th>Appointments</th></tr></thead>
          <tbody>
            <?php if (empty($apptByDoctor)): ?>
              <tr><td colspan="2"><?php echo renderEmptyState('stethoscope', 'No appointments recorded', 'No appointments found for doctors in this period.'); ?></td></tr>
            <?php else: ?>
              <?php foreach ($apptByDoctor as $row): ?>
                <tr><td><?php echo e($row['fullName']); ?></td><td><?php echo e($row['total']); ?></td></tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
      <?php endif; ?>

      <?php if ($canViewBilling): ?>
      <section class="panel">
        <div class="panel-header"><h3>Billing Summary</h3></div>
        <table>
          <thead><tr><th>Payment status</th><th>Bills</th><th>Amount</th></tr></thead>
          <tbody>
            <?php foreach ($billTotals as $status => $t): ?>
              <tr>
                <td><?php echo statusBadge($status); ?></td>
                <td><?php echo e($t['total']); ?></td>
                <td><?php echo e(number_format($t['amount'], 2)); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td><strong>Total billed</strong></td>
              <td><strong><?php echo e($billTotals['Paid']['total'] + $billTotals['Unpaid']['total']); ?></strong></td>
              <td><strong><?php echo e(number_format($billTotals['Paid']['amount'] + $billTotals['Unpaid']['amount'], 2)); ?></strong></td>
            </tr>
          </tbody>
        </table>
      </section>
      <?php endif; ?>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
