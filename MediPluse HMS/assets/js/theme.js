(function () {
  var root = document.documentElement;

  function setTheme(theme) {
    root.dataset.theme = theme;
    try {
      localStorage.setItem('medipulse-theme', theme);
    } catch (error) {
      // The selected theme still applies for this page if storage is unavailable.
    }
    var toggle = document.querySelector('[data-theme-toggle]');
    if (toggle) {
      var nextTheme = theme === 'dark' ? 'light' : 'dark';
      toggle.textContent = 'Switch to ' + nextTheme + ' mode';
      toggle.setAttribute('aria-label', 'Switch to ' + nextTheme + ' mode');
      toggle.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var theme = root.dataset.theme === 'light' ? 'light' : 'dark';
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'theme-toggle';
    toggle.setAttribute('data-theme-toggle', '');
    toggle.addEventListener('click', function () {
      setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
    });
    document.body.appendChild(toggle);
    setTheme(theme);
  });
})();
