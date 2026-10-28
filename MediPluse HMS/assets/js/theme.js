(function () {
  var root = document.documentElement;

  function setTheme(theme) {
    root.dataset.theme = theme;
    try {
      localStorage.setItem('medipulse-theme', theme);
    } catch (error) {
      // The selected theme still applies for this page if storage is unavailable.
    }
    var toggles = document.querySelectorAll('[data-theme-toggle]');
    var nextTheme = theme === 'dark' ? 'light' : 'dark';
    toggles.forEach(function (toggle) {
      toggle.setAttribute('aria-label', 'Switch to ' + nextTheme + ' mode');
      toggle.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
      var label = toggle.querySelector('.theme-toggle-label');
      if (label) {
        label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
      } else if (!toggle.querySelector('svg')) {
        toggle.textContent = 'Switch to ' + nextTheme + ' mode';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var theme = root.dataset.theme === 'light' ? 'light' : 'dark';
    var existingToggles = document.querySelectorAll('[data-theme-toggle]');
    if (existingToggles.length === 0) {
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'theme-toggle';
      toggle.setAttribute('data-theme-toggle', '');
      toggle.addEventListener('click', function () {
        setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
      });
      document.body.appendChild(toggle);
    } else {
      existingToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        });
      });
    }
    setTheme(theme);
  });
})();