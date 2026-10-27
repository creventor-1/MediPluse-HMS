<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user'])) {
    redirect(roleDashboardPath((string) ($_SESSION['user']['role'] ?? '')));
}

$pageTitle = 'Hospital care, connected';
require __DIR__ . '/partials/head.php';
?>
<body class="landing public-home">
  <a class="home-skip-link" href="#main-content">Skip to content</a>

  <header class="home-nav">
    <a class="home-brand" href="<?php echo e(url('/index.php')); ?>" aria-label="<?php echo e(APP_NAME); ?> home">
      <span class="home-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
      </span>
      <span><?php echo e(APP_NAME); ?><small>Hospital Management</small></span>
    </a>

    <nav class="home-nav-links" aria-label="Main navigation">
      <a href="#platform">Platform</a>
      <a href="#work-areas">Work areas</a>
      <a href="#access">Access &amp; privacy</a>
    </nav>

    <div class="home-nav-actions">
      <a class="home-login-link" href="<?php echo e(url('/auth/login.php')); ?>">Staff sign in</a>
      <a class="home-button home-button-small" href="<?php echo e(url('/auth/signup.php')); ?>">Request access</a>
    </div>
  </header>

  <main id="main-content">
    <section class="home-hero" id="platform">
      <div class="home-hero-copy">
        <span class="home-eyebrow"><span class="home-eyebrow-dot"></span> A connected workspace for hospital teams</span>
        <h1>Better organised care starts <span>with a clearer view.</span></h1>
        <p class="home-lead">
          Bring patient records, appointments, laboratory results and hospital operations together
          in one secure workspace for authorised staff.
        </p>
        <div class="home-hero-actions">
          <a class="home-button" href="<?php echo e(url('/auth/login.php')); ?>">
            Open staff workspace
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <a class="home-secondary-link" href="#work-areas">Explore the platform <span aria-hidden="true">↓</span></a>
        </div>
        <div class="home-hero-note">
          <span class="home-note-icon" aria-hidden="true">✓</span>
          <span>Access is assigned by role and permission—not by job title alone.</span>
        </div>
      </div>

      <div class="home-preview-wrap" aria-label="Illustration of the MediPulse staff dashboard">
        <div class="home-preview-glow"></div>
        <div class="home-preview">
          <div class="preview-top">
            <div class="preview-brand">
              <span class="preview-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
              </span>
              <span>MediPulse <small>STAFF WORKSPACE</small></span>
            </div>
            <span class="preview-avatar" aria-hidden="true">MP</span>
          </div>
          <div class="preview-heading">
            <div><span>YOUR WORKSPACE</span><h2>Care, in context.</h2></div>
            <span class="preview-date">TODAY</span>
          </div>
          <div class="preview-metrics">
            <div class="preview-metric">
              <span class="preview-metric-icon patients-icon" aria-hidden="true">＋</span>
              <span><small>Patient records</small><strong>Organised</strong></span>
              <span class="preview-trend">Records</span>
            </div>
            <div class="preview-metric">
              <span class="preview-metric-icon appointments-icon" aria-hidden="true">◷</span>
              <span><small>Appointments</small><strong>Coordinated</strong></span>
              <span class="preview-trend">Schedule</span>
            </div>
          </div>
          <div class="preview-activity">
            <div class="preview-activity-head"><strong>Connected work areas</strong><span>7 AREAS</span></div>
            <div class="preview-area"><span class="preview-area-symbol">P</span><span>Patients</span><span class="preview-area-state">Records</span></div>
            <div class="preview-area"><span class="preview-area-symbol purple">A</span><span>Appointments</span><span class="preview-area-state">Schedule</span></div>
            <div class="preview-area"><span class="preview-area-symbol amber">L</span><span>Lab results</span><span class="preview-area-state">Results</span></div>
          </div>
          <div class="preview-footer"><span><i></i> Permission-aware workspace</span><span>MEDIPULSE HMS</span></div>
        </div>
        <div class="home-float-note">
          <span class="home-float-check" aria-hidden="true">✓</span>
          <span><strong>Role-aware access</strong><small>Only assigned areas open</small></span>
        </div>
      </div>
    </section>

    <section class="home-trust-row" aria-label="Platform highlights">
      <p>One workspace, connected around the work</p>
      <div><span aria-hidden="true">✳</span> Clear workflows</div>
      <div><span aria-hidden="true">⌑</span> Role-based permissions</div>
      <div><span aria-hidden="true">↗</span> Responsive on every screen</div>
    </section>

    <section class="home-work-areas" id="work-areas">
      <div class="home-section-heading">
        <span class="home-section-kicker">THE PLATFORM</span>
        <h2>Hospital operations, thoughtfully connected.</h2>
        <p>Move between the work areas your account is authorised to use. Your dashboard brings permitted summaries together.</p>
      </div>

      <div class="home-module-grid">
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=patients')); ?>">
          <span class="module-icon module-teal" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3.3"/><path d="M3.5 20v-1.2a5.5 5.5 0 0 1 11 0V20M17 8h5m-2.5-2.5v5"/></svg>
          </span>
          <span class="module-card-content"><strong>Patients</strong><small>Maintain patient details and find records when care teams need them.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=appointments')); ?>">
          <span class="module-icon module-violet" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M7.5 3v4M16.5 3v4M3.5 10h17M8 14h3m-3 3h6"/></svg>
          </span>
          <span class="module-card-content"><strong>Appointments</strong><small>Schedule visits and keep appointment details up to date.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=doctors')); ?>">
          <span class="module-icon module-rose" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M9 3v5l-4.5 8.2A3.2 3.2 0 0 0 7.3 21h9.4a3.2 3.2 0 0 0 2.8-4.8L15 8V3M8 3h8M7.5 15h9"/></svg>
          </span>
          <span class="module-card-content"><strong>Doctors</strong><small>Keep the clinician directory and related details organised.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=lab')); ?>">
          <span class="module-icon module-amber" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M9 3v5l-4.5 8.2A3.2 3.2 0 0 0 7.3 21h9.4a3.2 3.2 0 0 0 2.8-4.8L15 8V3M8 3h8M7.5 15h9"/><path d="M10 11h4"/></svg>
          </span>
          <span class="module-card-content"><strong>Lab results</strong><small>Connect test requests and recorded results with patient care.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=billing')); ?>">
          <span class="module-icon module-green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="M3 10h18m-13 4h3"/></svg>
          </span>
          <span class="module-card-content"><strong>Billing</strong><small>Review bills and keep payment status in one place.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=staff')); ?>">
          <span class="module-icon module-blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1m.8-5a4.2 4.2 0 0 1 5.2 4v1"/></svg>
          </span>
          <span class="module-card-content"><strong>Staff</strong><small>Manage staff records and authorised account access.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="home-module-card" href="<?php echo e(url('/auth/login.php?section=reports')); ?>">
          <span class="module-icon module-slate" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 19V5m0 14h17M8 15l3.5-4 3 2 5-6"/><circle cx="19.5" cy="7" r="1.4"/></svg>
          </span>
          <span class="module-card-content"><strong>Reports</strong><small>View operational summaries available to your account.</small></span>
          <span class="module-arrow" aria-hidden="true">↗</span>
        </a>
      </div>
      <p class="home-module-footnote">Sign in to see the areas and actions assigned to your account.</p>
    </section>

    <section class="home-access" id="access">
      <div class="home-access-copy">
        <span class="home-section-kicker">ACCESS THAT MAKES SENSE</span>
        <h2>The right information, for the right role.</h2>
        <p>Permissions are managed independently for each work area. A job title does not automatically unlock every record or action.</p>
        <a class="home-text-link" href="<?php echo e(url('/auth/signup.php')); ?>">Request a staff account <span aria-hidden="true">→</span></a>
      </div>
      <div class="home-access-list">
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Assigned access</strong><small>Sections and actions are permission-controlled.</small></span></div>
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Protected pages</strong><small>Access is checked when a page is opened, too.</small></span></div>
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Account approval</strong><small>New staff requests wait for authorised review.</small></span></div>
      </div>
    </section>

    <section class="home-cta">
      <div>
        <span class="home-section-kicker">MEDIPULSE HMS</span>
        <h2>Ready to get to work?</h2>
        <p>Sign in to continue to your authorised hospital workspace.</p>
      </div>
      <div class="home-cta-actions">
        <a class="home-button home-button-light" href="<?php echo e(url('/auth/login.php')); ?>">Staff sign in <span aria-hidden="true">→</span></a>
        <a class="home-cta-request" href="<?php echo e(url('/auth/signup.php')); ?>">Request access</a>
      </div>
    </section>
  </main>

  <footer class="home-footer">
    <a class="home-brand home-brand-footer" href="<?php echo e(url('/index.php')); ?>">
      <span class="home-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span>
      <span><?php echo e(APP_NAME); ?><small>Hospital Management</small></span>
    </a>
    <p>© <?php echo e(date('Y')); ?> <?php echo e(APP_NAME); ?> · Hospital operations, connected.</p>
    <a href="<?php echo e(url('/auth/login.php')); ?>">Staff sign in</a>
  </footer>
</body>
</html>
