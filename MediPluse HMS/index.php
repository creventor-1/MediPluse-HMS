<?php
require_once __DIR__ . '/config.php';

// Signed-in users go straight to their own page.
if (isset($_SESSION['user'])) {
    redirect(roleDashboardPath((string) ($_SESSION['user']['role'] ?? '')));
}

$pageTitle = 'Welcome';
require __DIR__ . '/partials/head.php';
?>
<body class="landing">

  <header class="landing-nav">
    <a class="landing-brand" href="<?php echo e(url('/index.php')); ?>">
      <span class="mark">🩺</span>
      <span><?php echo e(APP_NAME); ?></span>
    </a>
    <nav class="landing-links">
      <a href="#features">Features</a>
      <a href="#roles">Who uses it</a>
      <a class="button-link small" href="<?php echo e(url('/auth/login.php')); ?>">Login</a>
    </nav>
  </header>

  <section class="hero">
    <div>
      <span class="badge">🏥 Hospital Management System</span>
      <h1>Hospital records and care, <span>all in one place.</span></h1>
      <p class="lead">
        MediPulse HMS replaces paper files with one secure web system for patient registration,
        appointments, laboratory results, billing and administrative reporting.
      </p>
      <div class="hero-actions">
        <a class="button-link btn-lg" href="<?php echo e(url('/auth/login.php')); ?>">Sign in to your account</a>
        <a class="button-link secondary btn-lg" href="#features">See what it does</a>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <div class="float-card c1">
        <span class="ico">🧑‍⚕️</span>
        <div><strong>Patient records</strong><span>Registered once, found instantly</span></div>
      </div>
      <div class="float-card c2">
        <span class="ico">📅</span>
        <div><strong>Appointments</strong><span>No double-booked doctors</span></div>
      </div>
      <div class="float-card c3">
        <span class="ico">🧪</span>
        <div><strong>Lab results</strong><span>Requested and recorded digitally</span></div>
      </div>
    </div>
  </section>

  <section class="section" id="features">
    <div class="section-head">
      <span class="badge">Features</span>
      <h2>Everything a hospital desk needs</h2>
      <p>Each module is built around the daily workflow of hospital staff.</p>
    </div>
    <div class="feature-grid">
      <article class="feature-card">
        <div class="ico">🧑‍⚕️</div>
        <h3>Patient Registration</h3>
        <p>Register patients with a unique ID and keep their details in a single searchable record.</p>
      </article>
      <article class="feature-card">
        <div class="ico">📅</div>
        <h3>Appointment Scheduling</h3>
        <p>Book, reschedule and track appointments by doctor, date and time, with clash detection.</p>
      </article>
      <article class="feature-card">
        <div class="ico">🧪</div>
        <h3>Laboratory Results</h3>
        <p>Doctors request tests and laboratory staff record the results against the right patient.</p>
      </article>
      <article class="feature-card">
        <div class="ico">💳</div>
        <h3>Billing &amp; Payments</h3>
        <p>Generate a bill for each appointment and record payments as they are received.</p>
      </article>
      <article class="feature-card">
        <div class="ico">🔐</div>
        <h3>Role-based Access</h3>
        <p>Every user signs in and only sees the pages that belong to their role.</p>
      </article>
      <article class="feature-card">
        <div class="ico">📊</div>
        <h3>Administrative Reports</h3>
        <p>Summaries of appointments, laboratory activity and billing for any chosen period.</p>
      </article>
    </div>
  </section>

  <section class="section alt" id="roles">
    <div class="section-head">
      <span class="badge">Who uses it</span>
      <h2>Built for every role in the hospital</h2>
      <p>Five types of staff, each with a focused workspace.</p>
    </div>
    <div class="role-grid">
      <article class="role-card"><span class="ico">🛡️</span><div><h3>Administrator</h3><p>Manages accounts and views reports.</p></div></article>
      <article class="role-card"><span class="ico">🩺</span><div><h3>Doctor</h3><p>Sees own appointments and requests tests.</p></div></article>
      <article class="role-card"><span class="ico">🗂️</span><div><h3>Receptionist</h3><p>Registers patients and books appointments.</p></div></article>
      <article class="role-card"><span class="ico">🔬</span><div><h3>Lab Technician</h3><p>Records laboratory test results.</p></div></article>
      <article class="role-card"><span class="ico">🧾</span><div><h3>Billing Officer</h3><p>Issues bills and records payments.</p></div></article>
    </div>
  </section>

  <section class="cta-band">
    <h2>Ready to get started?</h2>
    <p>Sign in with the account created for you by the hospital administrator.</p>
    <a class="button-link btn-lg" href="<?php echo e(url('/auth/login.php')); ?>">Go to login</a>
  </section>

  <footer class="landing-footer">
    © <?php echo e(date('Y')); ?> <?php echo e(APP_NAME); ?> · Final Year Project, Department of Computer Science
  </footer>

</body>
</html>
