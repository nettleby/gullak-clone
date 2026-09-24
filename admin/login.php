<?php
require_once __DIR__ . '/includes/bootstrap.php';

/** Admin login only accepts admin/... destinations (prevents open-redirect). */
function admin_safe_next(string $n): string
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
    if (!preg_match('~^admin/[A-Za-z0-9_\-/\.?=&%#]+$~', $n)) return '';
    if (!preg_match('~\.php([?#].*)?$~', $n)) return '';
    return $n;
}

$next = admin_safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? ''));

if (admin_is_logged_in()) {
    header('Location: ' . url($next !== '' ? $next : 'admin/index.php'));
    exit;
}

$error = '';
$old   = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $old['username'] = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $next = admin_safe_next((string) ($_POST['next'] ?? ''));

    if ($old['username'] === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([$old['username']]);
        $a = $st->fetch();
        /* readable passwords (owner decision); legacy bcrypt accepted until migrated */
        $ok = false;
        if ($a) {
            if (isset($a['password_plain']) && $a['password_plain'] !== '') {
                $ok = hash_equals((string) $a['password_plain'], $password);
            } elseif (!empty($a['password_hash'])) {
                $ok = password_verify($password, $a['password_hash']);
            }
        }
        if ($a && $ok) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            header('Location: ' . url($next !== '' ? $next : 'admin/index.php'));
            exit;
        }
        $error = 'Invalid admin credentials.';
    }
}

$page_title = 'Admin login';
$nav = 'more';
$hide_tabs = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth">
  <div class="auth-logo">
    <div class="lg"><?= lucide('shield-check') ?></div>
    <h1>Admin panel</h1>
    <p><?= e(APP_NAME) ?> control room</p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="auth-card">
    <form method="post" autocomplete="on">
      <?= admin_csrf_field() ?>
      <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
      <label class="field-label" for="username">Username</label>
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
  </div>

  <p class="auth-foot">Not an admin? <a href="<?= url('index.php') ?>">Back to the app</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
