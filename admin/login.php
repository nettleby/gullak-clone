<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (admin_is_logged_in()) { header('Location: ' . url('admin/index.php')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $st->execute([$u]);
    $a = $st->fetch();
    if ($a && password_verify($p, $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $a['id'];
        header('Location: ' . url('admin/index.php'));
        exit;
    }
    $error = 'Invalid admin credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-wrap" style="max-width:420px;padding-top:60px">
  <div class="auth-logo">
    <div class="lg"><?php echo lucide('shield-check'); ?></div>
    <h1>Admin panel</h1>
    <p><?= e(APP_NAME) ?> control room</p>
  </div>

  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

  <div class="admin-card">
    <form method="post">
      <?= admin_csrf_field() ?>
      <label class="field-label" for="username">Username</label>
      <input class="field" id="username" name="username" required autofocus>

      <label class="field-label" for="password">Password</label>
      <input class="field" type="password" id="password" name="password" required>

      <div class="mt14"><button class="btn" type="submit">Enter admin panel</button></div>
    </form>
  </div>
  <p class="muted small center mt14">Default seed: admin / admin123 — change it right after first login.</p>
</div>
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
<script>if (window.lucide) try { lucide.createIcons(); } catch (e) {}</script>
</body>
</html>
