<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Allow only safe local paths (prevents open-redirect). */
function safe_next(string $n): string
{
    $n = trim($n);
    if ($n === '') return '';
    if (str_contains($n, '://') || str_starts_with($n, '//') || str_contains($n, '\\') || str_contains($n, '..')) {
        return '';
    }
    $n = ltrim($n, '/');
    if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($n, ltrim(BASE_URL, '/'))) {
        $n = ltrim(substr($n, strlen(ltrim(BASE_URL, '/'))), '/');
    }
    if (!preg_match('~^[A-Za-z0-9_\-/\.?=&%#]+$~', $n)) return '';
    if (!preg_match('~\.php([?#].*)?$~', $n)) return '';
    return $n;
}

$next_raw = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$next = safe_next($next_raw);

if (is_logged_in()) {
    header('Location: ' . url($next !== '' && !str_starts_with($next, 'admin') ? $next : 'index.php'));
    exit;
}

$error = '';
$old   = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['email'] = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $next = safe_next((string) ($_POST['next'] ?? ''));

    if ($old['email'] === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!attempt_login($old['email'], $password)) {
        $error = 'Wrong email or password.';
    } else {
        $dest = ($next !== '' && !str_starts_with($next, 'admin')) ? $next : 'index.php';
        header('Location: ' . url($dest));
        exit;
    }
}

$hide_header = true;
$page_title  = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth">
  <div class="auth-logo">
    <div class="lg"><?= lucide('piggy-bank') ?></div>
    <h1><?= e(APP_NAME) ?></h1>
    <p><?= e(APP_TAGLINE) ?> — 24K gold &amp; 999 silver</p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="auth-card">
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
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

  <div class="auth-benefits">
    <span class="ab"><?= lucide('shield-check') ?> Your money &amp; metal tracked in an auditable ledger</span>
    <span class="ab"><?= lucide('repeat') ?> SIPs from ₹10 · daily, weekly or monthly</span>
    <span class="ab"><?= lucide('landmark') ?> Withdraw to your bank anytime</span>
  </div>

  <p class="auth-foot">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
