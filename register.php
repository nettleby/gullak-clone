<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { header('Location: ' . url('index.php')); exit; }

$errors = [];
$old    = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $res = register_user($old['name'], $old['email'], $old['phone'], $password);
        if ($res['ok']) {
            flash_set('success', 'Welcome to ' . APP_NAME . '! Add money to start investing.');
            header('Location: ' . url('index.php'));
            exit;
        }
        $errors = $res['errors'];
    }
}

$hide_header = true;
$page_title  = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<div class="auth">
  <div class="auth-logo">
    <div class="lg"><?= lucide('piggy-bank') ?></div>
    <h1>Create your gullak</h1>
    <p>Start with ₹10 · 24K gold &amp; 999 silver</p>
  </div>

  <?php if ($errors): ?>
    <div class="flash flash-error">
      <?php foreach ($errors as $er) echo e($er) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <div class="auth-card">
    <form method="post">
      <?= csrf_field() ?>
      <label class="field-label" for="name">Full name</label>
      <input class="field" id="name" name="name" required placeholder="Priya Sharma"
             value="<?= e($old['name']) ?>">

      <label class="field-label" for="email">Email</label>
      <input class="field" type="email" id="email" name="email" required
             placeholder="you@example.com" value="<?= e($old['email']) ?>">

      <label class="field-label" for="phone">Mobile number</label>
      <div class="input-wrap">
        <span class="prefix">+91</span>
        <input class="field" id="phone" name="phone" required inputmode="numeric" maxlength="10"
               placeholder="98765 43210" value="<?= e($old['phone']) ?>">
      </div>
      <p class="field-hint">We use it only for your account records.</p>

      <label class="field-label" for="password">Password</label>
      <div class="pw-wrap">
        <input class="field" type="password" id="password" name="password" required minlength="6"
               placeholder="At least 6 characters">
        <button class="pw-eye" type="button" data-toggle-pw="password" aria-label="Show password"><?= lucide('eye') ?></button>
      </div>
      <div class="pw-meter" data-for="password"><span></span><span></span><span></span></div>

      <label class="field-label" for="confirm">Confirm password</label>
      <div class="pw-wrap">
        <input class="field" type="password" id="confirm" name="confirm" required
               placeholder="Repeat password">
        <button class="pw-eye" type="button" data-toggle-pw="confirm" aria-label="Show password"><?= lucide('eye') ?></button>
      </div>

      <div class="mt20">
        <button class="btn" type="submit"><?= lucide('sparkles') ?> Create account</button>
      </div>
    </form>
  </div>

  <p class="auth-foot">Already have an account? <a href="<?= url('login.php') ?>">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
