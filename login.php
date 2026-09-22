<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { header('Location: ' . url('index.php')); exit; }

$errors = [];
$old     = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['email'] = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';

    if ($old['email'] === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } elseif (!attempt_login($old['email'], $password)) {
        $errors[] = 'Wrong email or password.';
    } else {
        header('Location: ' . url('index.php'));
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

  <?php if ($errors): ?>
    <div class="flash flash-error"><?= e($errors[0]) ?></div>
  <?php endif; ?>

  <div class="auth-card">
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
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
