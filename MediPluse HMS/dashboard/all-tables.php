<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$db = getDb();

$labels = [
  'patients'        => 'Patients',
  'doctors'         => 'Doctors',
  'appointments'    => 'Appointments',
  'users'           => 'System Users',
  'tblpatients'     => 'Patients (old)',
  'tbldoctors'      => 'Doctors (old)',
  'tblappointments' => 'Appointments (old)',
  'tblusers'        => 'Users (old)',
  'tblstaff'        => 'Staff',
  'tblbilling'      => 'Billing',
  'tbllabresults'   => 'Lab Results',
];

$tables = [];
foreach ($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $name) {
    $count = $db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
    $tables[] = [
        'name'  => $name,
        'label' => $labels[$name] ?? ucfirst($name),
        'count' => (int) $count,
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>All Tables | <?= e(APP_NAME) ?></title>
<style>
  body { font-family: Inter, Arial, sans-serif; background:#f4f7fc; margin:0; padding:30px; color:#0f172a; }
  h1 { margin:0 0 5px; }
  p.sub { color:#64748b; margin:0 0 25px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px; }
  .card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .card small { color:#64748b; font-weight:600; }
  .card h2 { margin:8px 0 4px; font-size:34px; }
  .card span { font-size:12px; color:#94a3b8; }
  a.back { display:inline-block; margin-bottom:20px; color:#2563eb; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
  <a class="back" href="<?= e(url('/dashboard/index.php')) ?>">&larr; Back to Dashboard</a>
  <h1>Database Overview</h1>
  <p class="sub">Every table in <?= e(DB_NAME) ?>, with its number of records</p>

  <div class="grid">
    <?php foreach ($tables as $t): ?>
      <div class="card">
        <small><?= e($t['label']) ?></small>
        <h2><?= $t['count'] ?></h2>
        <span>table: <?= e($t['name']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>