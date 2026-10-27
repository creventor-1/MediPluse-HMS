<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

requirePatientRole();

$pdo = getDb();
$userId = (string) currentUser()['id'];
$patientIds = linkedPatientIds($pdo, $userId);
$message = '';
$messageType = '';
$toCents = static function (string $amount): int {
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['book_appointment'])) {
        $patientId = trim($_POST['patient_id'] ?? '');
        $doctorId = trim($_POST['doctor_id'] ?? '');
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');
        $validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appointmentTime) === 1;
        $appointmentValue = $validTime
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $appointmentDate . ' ' . $appointmentTime)
            : false;
        $validDateTime = $appointmentValue !== false
            && $appointmentValue->format('Y-m-d H:i') === $appointmentDate . ' ' . $appointmentTime
            && $appointmentValue > new DateTimeImmutable();

        if (!in_array($patientId, $patientIds, true)) {
            $message = 'Choose one of your linked patient records.';
            $messageType = 'danger';
        } elseif ($doctorId === '' || !$validDateTime) {
            $message = 'Choose a valid doctor and a future appointment date and time.';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $doctorLock = $pdo->prepare('SELECT doctorID FROM tblDoctors WHERE doctorID = ? FOR UPDATE');
                $doctorLock->execute([$doctorId]);
                if (!$doctorLock->fetchColumn()) {
                    $pdo->rollBack();
                    $message = 'That doctor is not available.';
                    $messageType = 'danger';
                } else {
                    $conflict = $pdo->prepare(
                        "SELECT COUNT(*) FROM tblAppointments
                         WHERE doctorID = ? AND appointmentDate = ? AND appointmentTime = ? AND status = 'Booked'"
                    );
                    $conflict->execute([$doctorId, $appointmentDate, $appointmentTime . ':00']);
                    if ((int) $conflict->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $message = 'That doctor already has a booked appointment at this date and time.';
                        $messageType = 'danger';
                    } else {
                        $appointmentId = nextId($pdo, 'tblAppointments', 'appointmentID', 'APT');
                        $pdo->prepare(
                            "INSERT INTO tblAppointments
                                (appointmentID, patientID, doctorID, appointmentDate, appointmentTime, status)
                             VALUES (?, ?, ?, ?, ?, 'Booked')"
                        )->execute([
                            $appointmentId,
                            $patientId,
                            $doctorId,
                            $appointmentDate,
                            $appointmentTime . ':00',
                        ]);
                        $pdo->commit();
                        $message = 'Appointment ' . $appointmentId . ' booked.';
                        $messageType = 'success';
                    }
                }
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Patient appointment booking failed: ' . $error->getMessage());
                $message = 'The appointment could not be booked. Please try again.';
                $messageType = 'danger';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
    } elseif (isset($_POST['request_payment'])) {
        $billId = trim($_POST['bill_id'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');

        if (!in_array($paymentMethod, ['Cash', 'Bank transfer', 'ATM card'], true)) {
            $message = 'Choose cash, bank transfer, or ATM card.';
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();
                $billStmt = $pdo->prepare(
                    "SELECT b.billID, b.patientID, b.amount, b.paymentStatus
                     FROM tblBilling b
                     JOIN tblPatientAccounts pa ON pa.patientID = b.patientID
                     WHERE b.billID = ? AND pa.userID = ? AND pa.accountStatus = 'active'
                     FOR UPDATE"
                );
                $billStmt->execute([$billId, $userId]);
                $bill = $billStmt->fetch();

                if (!$bill) {
                    $pdo->rollBack();
                    $message = 'That bill is not available in your patient account.';
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

                    if ($remainingCents <= 0) {
                        $pdo->rollBack();
                        $message = 'This bill has no outstanding balance.';
                        $messageType = 'danger';
                    } else {
                        $pendingStmt = $pdo->prepare(
                            "SELECT requestID FROM tblPatientPaymentRequests
                             WHERE billID = ? AND userID = ? AND status = 'pending'
                             LIMIT 1 FOR UPDATE"
                        );
                        $pendingStmt->execute([$billId, $userId]);

                        if ($pendingStmt->fetchColumn()) {
                            $pdo->rollBack();
                            $message = 'A payment request for this bill is already awaiting staff verification.';
                            $messageType = 'info';
                        } else {
                            $requestId = 'PRQ' . strtoupper(bin2hex(random_bytes(4)));
                            $pdo->prepare(
                                'INSERT INTO tblPatientPaymentRequests
                                    (requestID, userID, patientID, billID, amount, paymentMethod)
                                 VALUES (?, ?, ?, ?, ?, ?)'
                            )->execute([
                                $requestId,
                                $userId,
                                $bill['patientID'],
                                $billId,
                                number_format($remainingCents / 100, 2, '.', ''),
                                $paymentMethod,
                            ]);
                            $pdo->commit();
                            $message = 'Your ' . $paymentMethod . ' payment request was sent to billing staff. The bill will remain unpaid until staff verifies and records the payment.';
                            $messageType = 'success';
                        }
                    }
                }
            } catch (PDOException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Patient payment request failed: ' . $error->getMessage());
                $message = 'Your payment request could not be saved. Please try again or contact billing staff.';
                $messageType = 'danger';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
    } else {
        http_response_code(400);
        exit('Unsupported patient portal action.');
    }
}

$patients = [];
$doctors = [];
$appointments = [];
$labResults = [];
$bills = [];
$paymentRequests = [];
if ($patientIds !== []) {
    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
    $patientsStmt = $pdo->prepare(
        "SELECT patientID, fullName, gender, dateOfBirth, phoneNumber, address
         FROM tblPatients WHERE patientID IN ($placeholders) ORDER BY fullName"
    );
    $patientsStmt->execute($patientIds);
    $patients = $patientsStmt->fetchAll();

    $doctors = $pdo->query(
        'SELECT doctorID, fullName, specialty FROM tblDoctors ORDER BY fullName, doctorID'
    )->fetchAll();

    $appointmentsStmt = $pdo->prepare(
        "SELECT a.appointmentID, a.patientID, a.appointmentDate, a.appointmentTime, a.status,
                p.fullName AS patientName, d.fullName AS doctorName, d.specialty,
                attending.fullName AS attendedByName
         FROM tblAppointments a
         JOIN tblPatients p ON p.patientID = a.patientID
         JOIN tblDoctors d ON d.doctorID = a.doctorID
         LEFT JOIN tblDoctors attending ON attending.doctorID = a.attendedByDoctorID
         WHERE a.patientID IN ($placeholders)
         ORDER BY a.appointmentDate DESC, a.appointmentTime DESC"
    );
    $appointmentsStmt->execute($patientIds);
    $appointments = $appointmentsStmt->fetchAll();

    $labStmt = $pdo->prepare(
        "SELECT l.labResultID, l.patientID, l.testType, l.resultValue, l.dateRecorded,
                p.fullName AS patientName, d.fullName AS doctorName
         FROM tblLabResults l
         JOIN tblPatients p ON p.patientID = l.patientID
         JOIN tblDoctors d ON d.doctorID = l.requestedBy
         WHERE l.patientID IN ($placeholders)
         ORDER BY COALESCE(l.dateRecorded, '1000-01-01') DESC, l.labResultID DESC"
    );
    $labStmt->execute($patientIds);
    $labResults = $labStmt->fetchAll();

    $billsStmt = $pdo->prepare(
        "SELECT b.billID, b.patientID, b.appointmentID, b.amount, b.paymentStatus, b.dateIssued,
                p.fullName AS patientName, a.appointmentDate,
                COALESCE(payment_totals.paidAmount, 0.00) AS paidAmount
         FROM tblBilling b
         JOIN tblPatients p ON p.patientID = b.patientID
         JOIN tblAppointments a ON a.appointmentID = b.appointmentID
         LEFT JOIN (
             SELECT billID, SUM(amount) AS paidAmount FROM tblPayments GROUP BY billID
         ) payment_totals ON payment_totals.billID = b.billID
         WHERE b.patientID IN ($placeholders)
         ORDER BY b.dateIssued DESC, b.billID DESC"
    );
    $billsStmt->execute($patientIds);
    $bills = $billsStmt->fetchAll();

    $requestsStmt = $pdo->prepare(
        "SELECT requestID, billID, patientID, amount, paymentMethod, status, requestedAt
         FROM tblPatientPaymentRequests
         WHERE userID = ? AND patientID IN ($placeholders)
         ORDER BY requestedAt DESC, requestID DESC"
    );
    $requestsStmt->execute(array_merge([$userId], $patientIds));
    $paymentRequests = $requestsStmt->fetchAll();
}

$pendingRequestsByBill = [];
foreach ($paymentRequests as $request) {
    if ($request['status'] === 'pending') {
        $pendingRequestsByBill[$request['billID']] = true;
    }
}

$pageTitle = 'My patient portal';
require __DIR__ . '/../partials/head.php';
?>
<body>
  <div class="layout">
    <?php $activeNav = 'patient-portal'; require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <p class="eyebrow">Private patient account</p>
          <h2>My patient portal</h2>
        </div>
      </div>

      <?php if ($message !== ''): ?>
        <div class="alert <?php echo e($messageType); ?>" role="status"><?php echo e($message); ?></div>
      <?php endif; ?>

      <?php if ($patientIds === []): ?>
        <section class="panel">
          <div class="empty-state">
            Your account is not linked to a patient record yet. Please contact the hospital administrator to verify and link your account.
          </div>
        </section>
      <?php else: ?>
        <section class="panel">
          <div class="panel-header"><h3>My personal details</h3></div>
          <div class="patient-profile-grid">
            <?php foreach ($patients as $patient): ?>
              <article class="patient-profile-card">
                <h4><?php echo e($patient['fullName']); ?></h4>
                <dl>
                  <div><dt>Patient ID</dt><dd><?php echo e($patient['patientID']); ?></dd></div>
                  <div><dt>Gender</dt><dd><?php echo e($patient['gender']); ?></dd></div>
                  <div><dt>Date of birth</dt><dd><?php echo e($patient['dateOfBirth']); ?></dd></div>
                  <div><dt>Phone</dt><dd><?php echo e($patient['phoneNumber'] ?: 'Not recorded'); ?></dd></div>
                  <div><dt>Address</dt><dd><?php echo e($patient['address'] ?: 'Not recorded'); ?></dd></div>
                </dl>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="panel" id="doctors">
          <div class="panel-header"><h3>Doctors</h3></div>
          <p class="hint">Browse the hospital doctor directory and specialties when choosing an appointment.</p>
          <?php if ($doctors === []): ?>
            <div class="empty-state">No doctors are currently listed.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Doctor</th><th>Specialty</th></tr></thead>
                <tbody>
                  <?php foreach ($doctors as $doctor): ?>
                    <tr><td><?php echo e($doctor['fullName']); ?></td><td><?php echo e($doctor['specialty'] ?: 'General practice'); ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="panel" id="appointments">
          <div class="panel-header"><h3>Book an appointment</h3></div>
          <p class="hint">Appointments are booked only for your verified patient record. A time already booked with a doctor cannot be selected.</p>
          <?php if ($doctors === []): ?>
            <div class="empty-state">Appointments cannot be booked until a doctor is listed.</div>
          <?php else: ?>
            <form method="POST" class="form-grid patient-appointment-form">
              <?php echo csrf_field(); ?>
              <label>Patient record
                <select name="patient_id" required>
                  <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo e($patient['patientID']); ?>"><?php echo e($patient['fullName']); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Doctor
                <select name="doctor_id" required>
                  <option value="">Choose a doctor</option>
                  <?php foreach ($doctors as $doctor): ?>
                    <option value="<?php echo e($doctor['doctorID']); ?>"><?php echo e($doctor['fullName'] . ' — ' . ($doctor['specialty'] ?: 'General practice')); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Date
                <input type="date" name="appointment_date" min="<?php echo e(date('Y-m-d')); ?>" required>
              </label>
              <label>Time
                <input type="time" name="appointment_time" required>
              </label>
              <div class="form-actions"><button type="submit" name="book_appointment" value="1">Book appointment</button></div>
            </form>
          <?php endif; ?>
        </section>

        <section class="panel">
          <div class="panel-header"><h3>My appointments</h3></div>
          <?php if ($appointments === []): ?>
            <div class="empty-state">You have no appointments yet.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Date</th><th>Time</th><th>Referred doctor</th><th>Specialty</th><th>Doctor who attended</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($appointments as $appointment): ?>
                    <tr>
                      <td><?php echo e($appointment['appointmentDate']); ?></td>
                      <td><?php echo e(substr($appointment['appointmentTime'], 0, 5)); ?></td>
                      <td><?php echo e($appointment['doctorName']); ?></td>
                      <td><?php echo e($appointment['specialty'] ?: 'General practice'); ?></td>
                      <td><?php echo e($appointment['attendedByName'] ?: 'Not recorded'); ?></td>
                      <td><span class="status <?php echo e(strtolower($appointment['status'])); ?>"><?php echo e($appointment['status']); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="panel" id="medical-reports">
          <div class="panel-header"><h3>My lab results and medical reports</h3></div>
          <p class="hint">Only clinician-entered reports connected to your linked patient record are displayed here.</p>
          <?php if ($labResults === []): ?>
            <div class="empty-state">No clinician-entered lab results or reports are available yet.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Patient</th><th>Test/report</th><th>Result</th><th>Requested by</th><th>Date</th></tr></thead>
                <tbody>
                  <?php foreach ($labResults as $labResult): ?>
                    <tr>
                      <td><?php echo e($labResult['patientName']); ?></td>
                      <td><?php echo e($labResult['testType']); ?></td>
                      <td><?php echo $labResult['resultValue'] !== null && $labResult['resultValue'] !== ''
                          ? e($labResult['resultValue'])
                          : '<span class="status pending">Pending</span>'; ?></td>
                      <td><?php echo e($labResult['doctorName']); ?></td>
                      <td><?php echo e($labResult['dateRecorded'] ?: 'Pending'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          <p class="hint">This portal displays available lab results; it does not create a diagnosis or replace advice from your clinician.</p>
        </section>

        <section class="panel">
          <div class="panel-header" id="my-bills"><h3>My bills</h3></div>
          <p class="hint">Only bills connected to your approved patient record are shown. Charges are created by hospital staff; patients cannot create or change bill amounts.</p>
          <?php if ($bills === []): ?>
            <div class="empty-state">There are no bills on your linked patient record.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr><th>Bill</th><th>Appointment</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Payment option</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($bills as $bill):
                      $balance = max(0, (float) $bill['amount'] - (float) $bill['paidAmount']);
                      if ($bill['paymentStatus'] === 'Paid' && (float) $bill['paidAmount'] === 0.0) {
                          $bill['paidAmount'] = $bill['amount'];
                          $balance = 0.0;
                      }
                  ?>
                    <tr>
                      <td><?php echo e($bill['billID']); ?></td>
                      <td><?php echo e($bill['appointmentDate']); ?></td>
                      <td><?php echo e(number_format((float) $bill['amount'], 2)); ?></td>
                      <td><?php echo e(number_format((float) $bill['paidAmount'], 2)); ?></td>
                      <td><?php echo e(number_format($balance, 2)); ?></td>
                      <td><span class="status <?php echo e(strtolower($bill['paymentStatus'])); ?>"><?php echo e($bill['paymentStatus']); ?></span></td>
                      <td>
                        <?php if ($balance <= 0): ?>
                          <span>Nothing due</span>
                        <?php elseif (isset($pendingRequestsByBill[$bill['billID']])): ?>
                          <span class="status pending">Payment request pending</span>
                        <?php else: ?>
                          <form method="POST" class="patient-payment-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="bill_id" value="<?php echo e($bill['billID']); ?>">
                            <label>
                              Choose method
                              <select name="payment_method" required>
                                <option value="">Select</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank transfer">Bank transfer</option>
                                <option value="ATM card">ATM card</option>
                              </select>
                            </label>
                            <button type="submit" name="request_payment" value="1">Request payment</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          <p class="hint">Payment choices send a request to billing staff; they do not charge a card or verify a bank transfer. Your bill changes to Paid only after staff confirms and records the payment.</p>
        </section>

        <?php if ($paymentRequests !== []): ?>
        <section class="panel">
          <div class="panel-header"><h3>My payment requests</h3></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Request</th><th>Bill</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th></tr></thead>
              <tbody>
                <?php foreach ($paymentRequests as $request): ?>
                  <tr>
                    <td><?php echo e($request['requestID']); ?></td>
                    <td><?php echo e($request['billID']); ?></td>
                    <td><?php echo e(number_format((float) $request['amount'], 2)); ?></td>
                    <td><?php echo e($request['paymentMethod']); ?></td>
                    <td><span class="status <?php echo e($request['status'] === 'pending' ? 'pending' : strtolower($request['status'])); ?>"><?php echo e(ucfirst($request['status'])); ?></span></td>
                    <td><?php echo e($request['requestedAt']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
  <?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
