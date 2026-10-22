<script>
  // Close the mobile sidebar when a nav link is tapped.
  document.querySelectorAll('.sidebar nav a, .sidebar .logout-link').forEach(function (link) {
    link.addEventListener('click', function () {
      document.querySelector('.sidebar').classList.remove('open');
    });
  });

  // Auto-dismiss alert banners after a few seconds.
  document.querySelectorAll('.alert').forEach(function (alertEl) {
    setTimeout(function () {
      alertEl.style.transition = 'opacity .4s ease';
      alertEl.style.opacity = '0';
      setTimeout(function () { alertEl.remove(); }, 400);
    }, 4000);
  });
</script>
