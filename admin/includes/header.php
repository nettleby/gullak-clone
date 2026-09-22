<?php
/** Admin shell — expects $nav (current page key) and $page_title. */
$admin = admin_current();
$navItems = [
    'dashboard'   => ['admin/index.php',       'Dashboard',   'chart-no-axes-column'],
    'prices'      => ['admin/prices.php',       'Prices',      'badge-indian-rupee'],
    'users'       => ['admin/users.php',        'Users',       'users'],
    'withdrawals' => ['admin/withdrawals.php',  'Withdrawals', 'landmark'],
    'sips'        => ['admin/sips.php',         'SIPs',        'repeat'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · <?= e($page_title ?? APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-wrap">
  <nav class="admin-nav">
    <a class="brand" href="<?= url('admin/index.php') ?>">
      <span class="brand-mark"><?= lucide('piggy-bank') ?></span>
      <span class="brand-name"><?= e(APP_NAME) ?> Admin</span>
    </a>
    <span class="spacer"></span>
    <?php foreach ($navItems as $key => [$href, $label, $icon]): ?>
      <a class="navlink <?= (($nav ?? '') === $key) ? 'active' : '' ?>" href="<?= url($href) ?>">
        <?= lucide($icon, 'ic-14') ?> <?= e($label) ?>
      </a>
    <?php endforeach; ?>
    <a class="navlink" href="<?= url('admin/change-password.php') ?>"><?= lucide('key-round', 'ic-14') ?> Password</a>
    <a class="navlink" href="<?= url('admin/logout.php') ?>" style="color:#DC2626" data-confirm="Log out of the admin panel?"><?= lucide('log-out', 'ic-14') ?> Log out</a>
  </nav>
  <?= flash_render() ?>
