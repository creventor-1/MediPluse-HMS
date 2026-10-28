<?php
if (!function_exists('currentUser')) {
    require_once __DIR__ . '/../config.php';
}
if (!function_exists('icon')) {
    require_once __DIR__ . '/icons.php';
}

/**
 * Shared sidebar. Expects $activeNav to be one of:
 * 'dashboard' | 'patients' | 'appointments' | 'doctors' |
 * 'lab' | 'billing' | 'staff' | 'reports'
 *
 * Links are shown according to the logged-in user's role
 * (Chapter Three, Section 3.2.3 / Table 3.2).
 */
$activeNav = $activeNav ?? '';
$user = currentUser();
$userRoleKey = roleKey((string) ($user['role'] ?? ''));
$isPatient = $userRoleKey === 'patient';
$isAdmin = $userRoleKey === 'admin';
$isDoctor = $userRoleKey === 'doctor';
$isBillingOfficer = $userRoleKey === 'billing_officer';
$isReceptionist = $userRoleKey === 'receptionist';
$sectionLabels = [
    'dashboard' => 'Dashboard', 'permissions' => 'Manage Users & Permissions', 'patients' => 'Patients', 'appointments' => 'Appointments',
    'doctors' => 'Doctors', 'lab' => 'Lab Results', 'billing' => 'Billing',
    'staff' => 'Staff', 'reports' => 'Reports',
];
$sectionLabel = $sectionLabels[$activeNav] ?? APP_NAME;
$navItem = static function (string $key, string $path, string $label, string $iconSvg) use ($activeNav) {
    if (!can($key, 'view')) {
        echo '<div class="nav-locked" aria-disabled="true" title="Permission required">'
            . $iconSvg . '<span>' . e($label) . '</span><small>Locked</small></div>';
        return;
    }
    $isActive = $activeNav === $key ? ' class="active"' : '';
    echo '<a href="' . e(url($path)) . '"' . $isActive . '>' . $iconSvg . '<span>' . e($label) . '</span></a>';
};
?>
<script>document.body.classList.add('theme-<?php echo e($activeNav !== '' ? $activeNav : 'dashboard'); ?>');</script>
<div class="mobile-bar">
  <button type="button" class="menu-btn" data-nav-open aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
    <?php echo icon('menu', 'mobile-menu-icon', 22); ?>
  </button>
  <div class="mobile-title">
    <strong><?php echo e(APP_NAME); ?></strong>
    <small><?php echo e($sectionLabel); ?></small>
  </div>
  <span class="mobile-avatar"><?php echo e(strtoupper(substr($user['name'] ?? '?', 0, 1))); ?></span>
</div>
<div class="sidebar-overlay" data-nav-close></div>
<aside class="sidebar" id="app-sidebar">
  <button type="button" class="sidebar-close" data-nav-close aria-label="Close menu">&times;</button>

  <div class="brand">
    <span class="brand-mark"><?php echo icon('stethoscope', 'brand-icon-svg', 22); ?></span>
    <span class="brand-text"><?php echo e(APP_NAME); ?></span>
    <button type="button" class="sidebar-collapse-btn" id="sidebar-collapse-toggle" aria-label="Toggle sidebar width" title="Toggle sidebar width">
      <?php echo icon('chevron-left', 'collapse-icon', 16); ?>
    </button>
  </div>

  <nav>
    <?php if (!$isPatient && !$isDoctor && !$isBillingOfficer && !$isReceptionist): ?>
      <a href="<?php echo e(url(roleDashboardPath((string) ($user['role'] ?? '')))); ?>"<?php echo $activeNav === 'dashboard' ? ' class="active"' : ''; ?>>
        <?php echo icon('activity', 'nav-icon', 18); ?><span>Dashboard</span>
      </a>
    <?php endif; ?>
    <?php if (!$isPatient && !$isBillingOfficer && can('system', 'manage_users')): ?>
      <a href="<?php echo e(url('/admin/permissions.php')); ?>"<?php echo $activeNav === 'permissions' ? ' class="active"' : ''; ?>>
        <?php echo icon('shield', 'nav-icon', 18); ?>
        <span>Manage users &amp; permissions</span>
      </a>
    <?php endif; ?>
    <?php if ($isDoctor): ?>
      <?php $navItem('doctors', '/doctors/index.php', 'Doctors', icon('stethoscope', 'nav-icon', 18)); ?>
    <?php endif; ?>
    <?php if ($isPatient): ?>
      <a href="<?php echo e(url('/patient/portal.php')); ?>"<?php echo $activeNav === 'patient-portal' ? ' class="active"' : ''; ?>>
        <?php echo icon('user', 'nav-icon', 18); ?><span>My patient portal</span>
      </a>
      <a href="<?php echo e(url('/patient/portal.php#doctors')); ?>"><?php echo icon('stethoscope', 'nav-icon', 18); ?><span>Doctors</span></a>
      <a href="<?php echo e(url('/patient/portal.php#appointments')); ?>"><?php echo icon('calendar', 'nav-icon', 18); ?><span>Appointments</span></a>
      <a href="<?php echo e(url('/patient/portal.php#medical-reports')); ?>"><?php echo icon('flask-conical', 'nav-icon', 18); ?><span>Lab Results</span></a>
      <a href="<?php echo e(url('/patient/portal.php#my-bills')); ?>"><?php echo icon('receipt', 'nav-icon', 18); ?><span>My bills</span></a>
    <?php elseif (!$isAdmin): ?>
      <?php $navItem('patients', '/patients/index.php', 'Patients', icon('users', 'nav-icon', 18)); ?>
    <?php endif; ?>
    <?php if (!$isAdmin && !$isBillingOfficer && !$isPatient): ?>
      <?php $navItem('appointments', '/appointments/index.php', 'Appointments', icon('calendar', 'nav-icon', 18)); ?>
      <?php if (!$isDoctor): ?>
        <?php $navItem('doctors', '/doctors/index.php', 'Doctors', icon('stethoscope', 'nav-icon', 18)); ?>
      <?php endif; ?>
      <?php $navItem('lab', '/lab/index.php', 'Lab Results', icon('flask-conical', 'nav-icon', 18)); ?>
    <?php endif; ?>
    <?php if (!$isAdmin && !$isPatient): ?>
      <?php $navItem('billing', '/billing/index.php', 'Billing', icon('credit-card', 'nav-icon', 18)); ?>
    <?php endif; ?>
    <?php if (!$isPatient && !$isAdmin && !$isBillingOfficer): ?>
      <?php $navItem('staff', '/staff/index.php', 'Staff', icon('user-plus', 'nav-icon', 18)); ?>
    <?php endif; ?>
    <?php if (!$isPatient && !$isAdmin): ?>
      <?php $navItem('reports', '/reports/index.php', 'Reports', icon('bar-chart', 'nav-icon', 18)); ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-actions-bar">
      <button type="button" class="theme-toggle-inline" data-theme-toggle aria-label="Switch theme" title="Switch theme">
        <span class="theme-icon-light"><?php echo icon('sun', '', 16); ?></span>
        <span class="theme-icon-dark"><?php echo icon('moon', '', 16); ?></span>
        <span class="theme-toggle-label">Theme</span>
      </button>
    </div>

    <div class="user-chip">
      <span class="avatar"><?php echo e(strtoupper(substr($user['name'] ?? '?', 0, 1))); ?></span>
      <div class="user-chip-info">
        <strong><?php echo e($user['name'] ?? 'Guest'); ?></strong>
        <span><?php echo e($user['role'] ?? ''); ?></span>
      </div>
    </div>
    <form method="POST" action="<?php echo e(url('/auth/logout.php')); ?>" class="logout-form">
      <?php echo csrf_field(); ?>
      <button class="logout-link" type="submit">
        <?php echo icon('log-out', 'nav-icon', 18); ?>
        <span>Logout</span>
      </button>
    </form>
  </div>
</aside>

<script>
(function () {
  var body = document.body;
  var sidebar = document.getElementById('app-sidebar');
  var overlay = document.querySelector('.sidebar-overlay');
  var opener = document.querySelector('[data-nav-open]');
  var collapseBtn = document.getElementById('sidebar-collapse-toggle');

  // Mobile drawer controls
  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('show', open);
    body.classList.toggle('nav-open', open);
    if (opener) opener.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  if (opener) {
    opener.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
  }
  document.querySelectorAll('[data-nav-close]').forEach(function (el) {
    el.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); } });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) { setOpen(false); } });

  // Desktop sidebar collapse toggle with persistence
  try {
    if (localStorage.getItem('medipulse-sidebar-collapsed') === '1' && window.innerWidth > 900) {
      body.classList.add('sidebar-collapsed');
    }
  } catch (err) {}

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      var isCollapsed = body.classList.toggle('sidebar-collapsed');
      try {
        localStorage.setItem('medipulse-sidebar-collapsed', isCollapsed ? '1' : '0');
      } catch (err) {}
    });
  }
})();
</script>