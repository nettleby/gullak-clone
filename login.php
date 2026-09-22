<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Allow only safe local paths (prevents open-redirect). */
function safe_next(string $n): string
{
    $n = trim($n);
    if ($n === '') return '';
    // Strip absolute URL / base prefix attempts
    if (str_contains($n, '://') || str_starts_with($n, '//') || str_contains($n, '\\') || str_contains($n, '..')) {
        return '';
    }
    $n = ltrim($n, '/');
    // If it still starts with the base folder, strip it (handles REQUEST_URI passthrough)
    if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($n, ltrim(BASE_URL, '/'))) {
        $n = ltrim(substr($n, strlen(ltrim(BASE_URL, '/'))), '/');
    }
    if (!preg_match('~^[A-Za-z0-9_\-/\.?=&%#]+$~', $n)) return '';
    // Must look like an app page
    if (!preg_match('~\.php([?#].*)?$~', $n) && !preg_match('~^(admin(/[A-Za-z0-9_\-?=&#\.]*?)?)?$~', $n)) {
        // allow plain 'admin' too
        if ($n !== 'admin') return '';
    }
    return $n;
}

$tab = strtolower($_GET['tab'] ?? $_POST['login_type'] ?? 'user');
if ($tab !== 'admin') $tab = 'user';

$next_raw = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$next = safe_next($next_raw);

// Already logged in? Keep user + admin coexisting: redirect only the matching tab.
if ($tab === 'user' && is_logged_in()) {
    header('Location: ' . url($next !== '' && !str_starts_with($next, 'admin') ? $next : 'index.php'));
    exit;
}
if ($tab === 'admin' && unified_admin_is_logged_in()) {
    header('Location: ' . url($next !== '' ? $next : 'admin/index.php'));
    exit;
}

$user_error = '';
$admin_error = '';
$old = ['email' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = strtolower($_POST['login_type'] ?? 'user');
    if ($type !== 'admin') $type = 'user';
    $tab = $type; // stay on the submitted tab on error
    $next = safe_next((string) ($_POST['next'] ?? ''));

    if ($type === 'user') {
        csrf_check();
        $old['email'] = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';

        if ($old['email'] === '' || $password === '') {
            $user_error = 'Please enter both email and password.';
        } elseif (!attempt_login($old['email'], $password)) {
            $user_error = 'Wrong email or password.';
        } else {
            $dest = ($next !== '' && !str_starts_with($next, 'admin')) ? $next : 'index.php';
            header('Location: ' . url($dest));
            exit;
        }
    } else {
        // Admin: validate inside the MGAADM session so user session is untouched.
        $old['username'] = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $sent_csrf = (string) ($_POST['csrf'] ?? '');

        if ($old['username'] === '' || $password === '') {
            $admin_error = 'Please enter both username and password.';
        } else {
            $ok = with_admin_session(function () use ($old, $password, $sent_csrf) {
                if (!admin_csrf_check_active($sent_csrf)) {
                    http_response_code(419);
                    exit('Your session expired. Please go back and try again.');
                }
                return attempt_admin_login_active($old['username'], $password);
            });
            if ($ok) {
                $dest = $next !== '' ? $next : 'admin/index.php';
                header('Location: ' . url($dest));
                exit;
            }
            $admin_error = 'Invalid admin credentials.';
        }
    }
}

// CSRF tokens for both panes (admin token fetched via session swap, user session restored after).
$user_csrf  = csrf_token();
$admin_csrf = unified_admin_csrf_token();

$hide_header = true;
$page_title  = 'Log in';
require __DIR__ . '/includes/header.php';

$tab_user_url  = url('login.php?tab=user' . ($next !== '' ? '&next=' . urlencode($next) : ''));
$tab_admin_url = url('login.php?tab=admin' . ($next !== '' ? '&next=' . urlencode($next) : ''));
?>
<div class="auth">
  <div class="auth-logo">
    <div class="lg"><?= lucide('piggy-bank') ?></div>
    <h1><?= e(APP_NAME) ?></h1>
    <p><?= e(APP_TAGLINE) ?> — 24K gold &amp; 999 silver</p>
  </div>

  <div class="auth-tabs" role="tablist" aria-label="Login type">
    <a href="<?= e($tab_user_url) ?>" role="tab" aria-selected="<?= $tab === 'user' ? 'true' : 'false' ?>"
       class="auth-tab <?= $tab === 'user' ? 'active' : '' ?>" data-tab-link="user"><?= lucide('user-round') ?> User</a>
    <a href="<?= e($tab_admin_url) ?>" role="tab" aria-selected="<?= $tab === 'admin' ? 'true' : 'false' ?>"
       class="auth-tab <?= $tab === 'admin' ? 'active' : '' ?>" data-tab-link="admin"><?= lucide('shield-check') ?> Admin</a>
  </div>

  <div class="auth-card" id="pane-user" <?= $tab === 'user' ? '' : 'hidden' ?>>
    <?php if ($user_error): ?>
      <div class="flash flash-error"><?= e($user_error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e($user_csrf) ?>">
      <input type="hidden" name="login_type" value="user">
      <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
      <label class="field-label" for="email">Email</label>
      <input class="field" type="email" id="email" name="email" required
             placeholder="you@example.com" value="<?= e($old['email']) ?>">

      <label class="field-label" for="password">Password</label>
      <div class="pw-wrap">
        <input class="field" type="password" id="password" name="password" required
               placeholder="••••••••">
        <button class="pw-eye" type="button" data-toggle-pw="password" aria-label="Show password"><?= lucide('eye') ?></button>
      </div>

      <div class="mt20">
        <button class="btn" type="submit"><?= lucide('log-in') ?> Log in</button>
      </div>
    </form>
  </div>

  <div class="auth-card" id="pane-admin" <?= $tab === 'admin' ? '' : 'hidden' ?>>
    <?php if ($admin_error): ?>
      <div class="flash flash-error"><?= e($admin_error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e($admin_csrf) ?>">
      <input type="hidden" name="login_type" value="admin">
      <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
      <label class="field-label" for="username">Admin username</label>
      <input class="field" id="username" name="username" required autocomplete="username"
             placeholder="admin" value="<?= e($old['username']) ?>">

      <label class="field-label" for="admin-password">Password</label>
      <div class="pw-wrap">
        <input class="field" type="password" id="admin-password" name="password" required
               placeholder="••••••••" autocomplete="current-password">
        <button class="pw-eye" type="button" data-toggle-pw="admin-password" aria-label="Show password"><?= lucide('eye') ?></button>
      </div>

      <div class="mt20">
        <button class="btn" type="submit"><?= lucide('shield-check') ?> Enter admin panel</button>
      </div>
    </form>
    <p class="field-hint center mt14">Admins only. User accounts use the User tab.</p>
  </div>

  <div class="auth-benefits">
    <span class="ab"><?= lucide('shield-check') ?> Your money &amp; metal tracked in an auditable ledger</span>
    <span class="ab"><?= lucide('repeat') ?> SIPs from ₹10 · daily, weekly or monthly</span>
    <span class="ab"><?= lucide('landmark') ?> Withdraw to your bank anytime</span>
  </div>

  <?php if ($tab === 'user'): ?>
    <p class="auth-foot">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
  <?php else: ?>
    <p class="auth-foot">Not an admin? <a href="<?= e($tab_user_url) ?>">Log in as user</a></p>
  <?php endif; ?>
</div>
<script>
(function () {
  var links = document.querySelectorAll('[data-tab-link]');
  var panes = { user: document.getElementById('pane-user'), admin: document.getElementById('pane-admin') };
  function show(t) {
    links.forEach(function (a) {
      var on = a.getAttribute('data-tab-link') === t;
      a.classList.toggle('active', on);
      a.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (panes.user) panes.user.hidden = (t !== 'user');
    if (panes.admin) panes.admin.hidden = (t !== 'admin');
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('tab', t);
      window.history.replaceState(null, '', u.toString());
    } catch (e) {}
  }
  links.forEach(function (a) {
    a.addEventListener('click', function (ev) {
      ev.preventDefault();
      show(a.getAttribute('data-tab-link'));
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
