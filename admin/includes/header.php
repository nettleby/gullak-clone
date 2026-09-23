<?php
/** Admin app shell — mirrors the user app (topbar + bottom tabbar).
 * Expects $nav (dashboard|prices|users|withdrawals|sips|more) and $page_title. */
$admin = admin_current();
$navItems = [
    'dashboard'   => ['admin/index.php',      'Home',    'house'],
    'prices'      => ['admin/prices.php',      'Prices',  'badge-indian-rupee'],
    'users'       => ['admin/users.php',       'Users',   'users'],
    'withdrawals' => ['admin/withdrawals.php', 'Payouts', 'landmark'],
    'sips'        => ['admin/sips.php',        'SIPs',    'repeat'],
    'more'        => ['admin/more.php',        'More',    'menu'],
];
$tabKey = in_array(($nav ?? ''), ['dashboard', 'prices', 'users', 'withdrawals', 'sips'], true) ? $nav : 'more';

/* cheap actionable count for the bell (mirrors the user-app pattern) */
$pendingWd = 0;
if ($admin) {
    try {
        $pendingWd = (int) db()->query('SELECT COUNT(*) FROM withdrawals WHERE status = "pending"')->fetchColumn();
    } catch (Throwable $e) { $pendingWd = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F172A">
<title>Admin · <?= e($page_title ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="app">
  <header class="topbar topbar-admin">
    <a class="brand" href="<?= url('admin/index.php') ?>">
      <span class="brand-mark"><?= lucide('shield-check') ?></span>
      <span class="brand-name">Admin</span>
      <span class="badge badge-warning">CONTROL ROOM</span>
    </a>
    <div class="topbar-actions">
      <a class="icon-btn" href="<?= url('admin/withdrawals.php') ?>" aria-label="Pending withdrawals">
        <?= lucide('bell') ?>
        <?php if ($pendingWd > 0): ?><span class="dot-alert"><?= (int) $pendingWd ?></span><?php endif; ?>
      </a>
    </div>
  </header>
<main class="screen">
<?= flash_render() ?>
