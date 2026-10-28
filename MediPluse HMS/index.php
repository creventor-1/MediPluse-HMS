<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/helpers.php';

if (isset($_SESSION['user'])) {
    redirect(roleDashboardPath((string) ($_SESSION['user']['role'] ?? '')));
}

$pageTitle = 'Hospital care, connected';
require __DIR__ . '/partials/head.php';
?>
<style>
  .home-portal-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-top: 26px;
  }
  .home-portal-card {
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 16px;
    padding: 22px 20px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .home-portal-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px -8px rgba(0, 0, 0, 0.09);
  }
  .home-portal-card.patient-card {
    border-top: 4px solid #0284c7;
  }
  .home-portal-card.staff-card {
    border-top: 4px solid #0d9488;
  }
  .portal-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 4px 10px;
    border-radius: 999px;
    width: fit-content;
    margin-bottom: 10px;
  }
  .patient-badge {
    background: #e0f2fe;
    color: #0369a1;
  }
  .staff-badge {
    background: #ccfbf1;
    color: #0f766e;
  }
  .home-portal-card h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px;
  }
  .home-portal-card p {
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.5;
    margin: 0 0 16px;
    flex-grow: 1;
  }
  .portal-card-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .portal-btn-patient {
    background: linear-gradient(135deg, #0284c7, #0369a1) !important;
    color: #ffffff !important;
    justify-content: center;
    padding: 10px 18px !important;
    font-size: 0.9rem !important;
    border-radius: 10px !important;
  }
  .portal-btn-staff {
    background: linear-gradient(135deg, #0d9488, #0f766e) !important;
    color: #ffffff !important;
    justify-content: center;
    padding: 10px 18px !important;
    font-size: 0.9rem !important;
    border-radius: 10px !important;
  }
  .portal-register-link {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    text-align: center;
    text-decoration: none;
    padding: 4px;
    transition: color 0.15s ease;
  }
  .portal-register-link:hover {
    color: #0284c7;
    text-decoration: underline;
  }
  .home-patient-nav-btn {
    font-size: 0.88rem;
    font-weight: 600;
    color: #0284c7 !important;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    background: rgba(2, 132, 199, 0.09);
    transition: background 0.15s ease;
  }
  .home-patient-nav-btn:hover {
    background: rgba(2, 132, 199, 0.18);
  }
</style>
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
      <a class="home-patient-nav-btn" href="<?php echo e(url('/auth/login.php?type=patient')); ?>" title="Access Patient Portal">
        <?php echo icon('user', '', 15); ?> Patient Portal
      </a>
      <a class="home-login-link" href="<?php echo e(url('/auth/login.php?type=staff')); ?>" title="Sign in as Staff">
        <?php echo icon('stethoscope', '', 15); ?> Staff Sign In
      </a>
      <a class="home-button home-button-small" href="<?php echo e(url('/auth/signup.php?type=patient')); ?>">
        Register
      </a>
    </div>
  </header>

  <main id="main-content">
    <section class="home-hero" id="platform">
      <div class="home-hero-copy">
        <span class="home-eyebrow"><span class="home-eyebrow-dot"></span> Connected Healthcare Platform</span>
        <h1>Better organised care starts <span>with a clearer view.</span></h1>
        <p class="home-lead">
          Providing patients with direct access to their health records, test results, and appointments—and giving hospital clinical teams a unified operational workspace.
        </p>

        <!-- Dual-Portal Pathways for Patients & Staff -->
        <div class="home-portal-cards">
          <!-- Patient Portal Card -->
          <div class="home-portal-card patient-card">
            <div>
              <div class="portal-badge patient-badge"><?php echo icon('user', '', 13); ?> For Patients &amp; Visitors</div>
              <h3>Patient Health Portal</h3>
              <p>View laboratory results, review hospital bills, and book consultations with specialist doctors.</p>
            </div>
            <div class="portal-card-buttons">
              <a class="home-button portal-btn-patient" href="<?php echo e(url('/auth/login.php?type=patient')); ?>">
                Patient Sign In
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </a>
              <a class="portal-register-link" href="<?php echo e(url('/auth/signup.php?type=patient')); ?>">
                Create patient account →
              </a>
            </div>
          </div>

          <!-- Staff Workspace Card -->
          <div class="home-portal-card staff-card">
            <div>
              <div class="portal-badge staff-badge"><?php echo icon('stethoscope', '', 13); ?> For Hospital Staff</div>
              <h3>Staff &amp; Clinical Workspace</h3>
              <p>Operational access for Doctors, Receptionists, Lab Scientists, Billing Officers, and Administration.</p>
            </div>
            <div class="portal-card-buttons">
              <a class="home-button portal-btn-staff" href="<?php echo e(url('/auth/login.php?type=staff')); ?>">
                Staff Sign In
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </a>
              <a class="portal-register-link" href="<?php echo e(url('/auth/signup.php?type=staff')); ?>">
                Request staff access →
              </a>
            </div>
          </div>
        </div>

        <div class="home-hero-actions" style="margin-top: 24px;">
          <a class="home-secondary-link" href="#work-areas">Explore hospital departments <span aria-hidden="true">↓</span></a>
        </div>

        <div class="home-hero-note">
          <span class="home-note-icon" aria-hidden="true">✓</span>
          <span>Patients register directly with instant access; staff accounts require administrative authorization.</span>
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
              <span>MediPulse <small>HEALTHCARE WORKSPACE</small></span>
            </div>
            <span class="preview-avatar" aria-hidden="true">MP</span>
          </div>
          <div class="preview-heading">
            <div><span>CONNECTED CARE</span><h2>Care, in context.</h2></div>
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
        <p>Permissions are managed independently for each work area. Patients enjoy private personal portals, while clinician and staff actions are role-protected.</p>
        <div style="display:flex; gap:16px; margin-top:14px;">
          <a class="home-text-link" href="<?php echo e(url('/auth/signup.php?type=patient')); ?>">Register as a Patient <span aria-hidden="true">→</span></a>
          <a class="home-text-link" href="<?php echo e(url('/auth/signup.php?type=staff')); ?>">Apply for staff access <span aria-hidden="true">→</span></a>
        </div>
      </div>
      <div class="home-access-list">
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Patient Privacy</strong><small>Patients only view their own appointments, results, and bills.</small></span></div>
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Protected Clinical Pages</strong><small>Staff access is strictly checked by assigned section permissions.</small></span></div>
        <div><span class="access-check" aria-hidden="true">✓</span><span><strong>Account Approval</strong><small>New staff requests wait for authorized administrative review.</small></span></div>
      </div>
    </section>

    <section class="home-cta">
      <div>
        <span class="home-section-kicker">MEDIPULSE HMS</span>
        <h2>Ready to access your care or workspace?</h2>
        <p>Sign in to your private patient portal or your authorized hospital department.</p>
      </div>
      <div class="home-cta-actions">
        <a class="home-button home-button-light" href="<?php echo e(url('/auth/login.php?type=patient')); ?>">Patient Portal <span aria-hidden="true">→</span></a>
        <a class="home-button" href="<?php echo e(url('/auth/login.php?type=staff')); ?>" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.4); color: #fff;">Staff Sign In <span aria-hidden="true">→</span></a>
        <a class="home-cta-request" href="<?php echo e(url('/auth/signup.php?type=patient')); ?>">Register as Patient</a>
      </div>
    </section>
  </main>

  <footer class="home-footer">
    <a class="home-brand home-brand-footer" href="<?php echo e(url('/index.php')); ?>">
      <span class="home-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span>
      <span><?php echo e(APP_NAME); ?><small>Hospital Management</small></span>
    </a>
    <p>© <?php echo e(date('Y')); ?> <?php echo e(APP_NAME); ?> · Hospital care and operations, connected.</p>
    <div style="display:flex; gap:16px;">
      <a href="<?php echo e(url('/auth/login.php?type=patient')); ?>">Patient Portal</a>
      <span style="opacity:0.4;">·</span>
      <a href="<?php echo e(url('/auth/login.php?type=staff')); ?>">Staff sign in</a>
    </div>
  </footer>
</body>
</html>
