<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requireRole(['admin', 'lab_technician']);

$pdo = getDb();
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');

if ($id === '') {
    redirect('/lab/index.php');
}

$stmt = $pdo->prepare(
    'SELECT l.*, p.fullName AS patientName FROM tblLabResults l
     JOIN tblPatients p ON p.patientID = l.patientID WHERE l.labResultID = ?'
);
$stmt->execute([$id]);
$lab = $stmt->fetch();

if (!$lab) {
    redirect('/lab/index.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {
    verify_csrf();

    $result = trim($_POST['result'] ?? '');
    $date = trim($_POST['date'] ?? '');

    if ($result === '' || $date === '') {
        $message = 'Result and date recorded are required.';
        $messageType = 'danger';
    } else {
        $pdo->prepare('UPDATE tblLabResults SET resultValue = ?, dateRecorded = ? WHERE labResultID = ?')
            ->execute([$result, $date, $id]);
        redirect('/lab/index.php');
    }

    $lab['resultValue'] = $result;
    $lab['dateRecorded'] = $date;
}

$pageTitle = 'Enter Lab Result';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'lab'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Laboratory</p>
          <h2>Enter Lab Result</h2>
        </div>
        <a href="<?php echo e(url('/lab/index.php')); ?>" class="button-link secondary">Back to lab results</a>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
      <?php endif; ?>

      <section class="form-card">
        <h3><?php echo e($lab['testType']); ?> - <?php echo e($lab['patientName']); ?></h3>
        <p class="hint">Record ID <?php echo e($lab['labResultID']); ?></p>
        <form method="POST" class="form-grid">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo e($lab['labResultID']); ?>">
          <label style="grid-column: 1 / -1;">
            Result
            <textarea name="result" rows="4" maxlength="255" required><?php echo e($lab['resultValue']); ?></textarea>
          </label>
          <label>
            Date recorded
            <input type="date" name="date" max="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($lab['dateRecorded'] ?: date('Y-m-d')); ?>" required>
          </label>
          <div class="form-actions">
            <button type="submit" name="save_result">Save Result</button>
            <a href="<?php echo e(url('/lab/index.php')); ?>" class="button-link secondary">Cancel</a>
          </div>
        </form>
      </section>
    </main>
  </div>

  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
