</div>
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    try { window.lucide.createIcons(); } catch (e) {}
  }
  function mgSwal() { return (typeof window.Swal !== 'undefined' && window.Swal.fire) ? window.Swal : null; }
  function mgConfirm(message) {
    var S = mgSwal();
    if (!S) return Promise.resolve(window.confirm(message));
    return S.fire({
      icon: 'warning',
      title: 'Are you sure?',
      text: message,
      showCancelButton: true,
      confirmButtonText: 'Yes, continue',
      cancelButtonText: 'Cancel',
      buttonsStyling: false,
      customClass: {
        popup: 'swal-mg-popup', title: 'swal-mg-title', htmlContainer: 'swal-mg-text',
        confirmButton: 'swal-mg-confirm', cancelButton: 'swal-mg-cancel', actions: 'swal-mg-actions'
      }
    }).then(function (r) { return !!r.isConfirmed; });
  }
  function mgSubmitForm(form, btn) {
    if (typeof form.requestSubmit === 'function') { try { btn ? form.requestSubmit(btn) : form.requestSubmit(); return; } catch (e) {} }
    form.submit();
  }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (f.dataset.mgOk === '1') { f.dataset.mgOk = ''; return; }
      var S = mgSwal();
      if (!S) {
        if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
        return;
      }
      ev.preventDefault();
      mgConfirm(f.getAttribute('data-confirm')).then(function (ok) {
        if (!ok) return;
        f.dataset.mgOk = '1';
        mgSubmitForm(f, null);
      });
    });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (ev) {
      if (b.dataset.mgOk === '1') { b.dataset.mgOk = ''; return; }
      var S = mgSwal();
      if (!S) {
        if (!window.confirm(b.getAttribute('data-confirm'))) {
          ev.preventDefault();
          ev.stopImmediatePropagation();
        }
        return;
      }
      ev.preventDefault();
      ev.stopImmediatePropagation();
      mgConfirm(b.getAttribute('data-confirm')).then(function (ok) {
        if (!ok || !b.form) return;
        b.dataset.mgOk = '1';
        mgSubmitForm(b.form, b);
      });
    });
  });
  document.querySelectorAll('a[data-confirm]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      var href = a.getAttribute('href');
      var S = mgSwal();
      if (!S) {
        if (!window.confirm(a.getAttribute('data-confirm'))) ev.preventDefault();
        return;
      }
      ev.preventDefault();
      mgConfirm(a.getAttribute('data-confirm')).then(function (ok) {
        if (ok && href) window.location.href = href;
      });
    });
  });
  (function mgFlash() {
    var S = mgSwal();
    var flashes = Array.prototype.slice.call(document.querySelectorAll('.flash'));
    if (!S || !flashes.length) return;
    function mgVisible(el) {
      if (!el || el.hasAttribute('hidden') || (el.closest && el.closest('[hidden]'))) return false;
      try {
        var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
        if (cs && (cs.display === 'none' || cs.visibility === 'hidden')) return false;
      } catch (e) {}
      if (typeof el.offsetParent === 'undefined') return true;
      return el.offsetParent !== null;
    }
    var queue = flashes.filter(mgVisible).map(function (el) {
      return { ok: !el.classList.contains('flash-error'), text: el.textContent.trim() };
    }).filter(function (f) { return f.text !== ''; });
    if (!queue.length) return;
    document.body.classList.add('mg-swal-flash');
    (function next(i) {
      if (i >= queue.length) return;
      var f = queue[i];
      if (f.ok) {
        S.fire({
          toast: true, position: 'top', icon: 'success', title: f.text,
          showConfirmButton: false, timer: 3500, timerProgressBar: true,
          buttonsStyling: false, customClass: { popup: 'swal-mg-toast' }
        }).then(function () { next(i + 1); });
      } else {
        S.fire({
          icon: 'error', title: 'Something needs attention', text: f.text,
          confirmButtonText: 'OK', buttonsStyling: false,
          customClass: {
            popup: 'swal-mg-popup', title: 'swal-mg-title', htmlContainer: 'swal-mg-text',
            confirmButton: 'swal-mg-confirm', actions: 'swal-mg-actions'
          }
        }).then(function () { next(i + 1); });
      }
    })(0);
  })();
});
</script>
</body>
</html>
