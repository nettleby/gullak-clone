</div>
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    try { window.lucide.createIcons(); } catch (e) {}
  }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (ev) {
      if (!window.confirm(b.getAttribute('data-confirm'))) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
      }
    });
  });
});
</script>
</body>
</html>
