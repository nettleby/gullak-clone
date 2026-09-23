<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();

$page_title = 'More';
$nav = 'more';
require __DIR__ . '/includes/header.php';
?>
<div class="page-title">More</div>
<p class="page-sub">Signed in as <?= e($admin['username']) ?></p>

<div class="menu">
  <a class="menu-item" href="<?= url('admin/change-password.php') ?>">
    <span class="mi-icon"><?= lucide('key-round') ?></span>
    <span class="mi-body"><span class="mi-title">Change password</span>
    <span class="mi-sub">Update your admin login password</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('index.php') ?>">
    <span class="mi-icon tone-green"><?= lucide('store') ?></span>
    <span class="mi-body"><span class="mi-title">View user app</span>
    <span class="mi-sub">Open <?= e(APP_NAME) ?> as users see it</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('admin/logout.php') ?>" data-confirm="Log out of the admin panel?">
    <span class="mi-icon tone-red"><?= lucide('log-out') ?></span>
    <span class="mi-body"><span class="mi-title">Log out</span>
    <span class="mi-sub">End this admin session</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
