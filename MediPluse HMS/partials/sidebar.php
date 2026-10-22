<?php
if (!function_exists('currentUser')) {
    require_once __DIR__ . '/../config.php';
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
$sectionLabels = [
    'dashboard' => 'Dashboard', 'patients' => 'Patients', 'appointments' => 'Appointments',
    'doctors' => 'Doctors', 'lab' => 'Lab Results', 'billing' => 'Billing',
    'staff' => 'Staff', 'reports' => 'Reports',
];
$sectionLabel = $sectionLabels[$activeNav] ?? APP_NAME;
$roleKeyValue = function_exists('roleKey')
    ? roleKey((string) ($user['role'] ?? ''))
    : strtolower((string) ($user['role'] ?? ''));

$navItem = static function (string $key, string $path, string $label, string $icon, array $roles) use ($activeNav, $roleKeyValue) {
    if (!in_array($roleKeyValue, $roles, true)) {
        return;
    }
    $isActive = $activeNav === $key ? ' class="active"' : '';
    echo '<a href="' . e(url($path)) . '"' . $isActive . '>' . $icon . '<span>' . e($label) . '</span></a>';
};
?>
<script>document.body.classList.add('theme-<?php echo e($activeNav !== '' ? $activeNav : 'dashboard'); ?>');</script>
<div class="mobile-bar">
  <button type="button" class="menu-btn" data-nav-open aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
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
    <span class="brand-mark">🩺</span>
    <span class="brand-text"><?php echo e(APP_NAME); ?></span>
  </div>

  <nav>
    <?php $navItem('dashboard', '/dashboard/index.php', 'Dashboard', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>', ['admin']); ?>
    <?php $navItem('patients', '/patients/index.php', 'Patients', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0116 0v1"/></svg>', ['admin', 'receptionist', 'doctor']); ?>
    <?php $navItem('appointments', '/appointments/index.php', 'Appointments', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>', ['admin', 'receptionist', 'doctor']); ?>
    <?php $navItem('doctors', '/doctors/index.php', 'Doctors', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2v4a3 3 0 006 0V2M6 8v4a6 6 0 0012 0V8M12 18v4"/></svg>', ['admin']); ?>
    <?php $navItem('lab', '/lab/index.php', 'Lab Results', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3h6M10 3v6L4.5 19a1 1 0 00.9 1.5h13.2a1 1 0 00.9-1.5L14 9V3M7.5 15h9"/></svg>', ['admin', 'doctor', 'lab_technician']); ?>
    <?php $navItem('billing', '/billing/index.php', 'Billing', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>', ['admin', 'billing_officer']); ?>
    <?php $navItem('staff', '/staff/index.php', 'Staff', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2 21v-1a7 7 0 0114 0v1M17 4.5a3.5 3.5 0 010 7M22 21v-1a7 7 0 00-4-6.3"/></svg>', ['admin']); ?>
    <?php $navItem('reports', '/reports/index.php', 'Reports', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>', ['admin']); ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <span class="avatar"><?php echo e(strtoupper(substr($user['name'] ?? '?', 0, 1))); ?></span>
      <div>
        <strong><?php echo e($user['name'] ?? 'Guest'); ?></strong>
        <span><?php echo e($user['role'] ?? ''); ?></span>
      </div>
    </div>
    <a class="logout-link" href="<?php echo e(url('/auth/logout.php')); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      <span>Logout</span>
    </a>
  </div>
</aside>

<script>
(function () {
  var body = document.body;
  var sidebar = document.getElementById('app-sidebar');
  var overlay = document.querySelector('.sidebar-overlay');
  var opener = document.querySelector('[data-nav-open]');

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('show', open);
    body.classList.toggle('nav-open', open);
    opener.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  opener.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
  document.querySelectorAll('[data-nav-close]').forEach(function (el) {
    el.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); } });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) { setOpen(false); } });
})();
</script>
