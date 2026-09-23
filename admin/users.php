<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT u.*, w.balance,
          (SELECT grams FROM holdings WHERE user_id = u.id AND metal="gold")   AS gold_g,
          (SELECT grams FROM holdings WHERE user_id = u.id AND metal="silver") AS silver_g
        FROM users u JOIN wallets w ON w.user_id = u.id';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?';
    $args = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY u.id DESC LIMIT 100';
$st = $pdo->prepare($sql);
$st->execute($args);
$users = $st->fetchAll();
$view = admin_view();

$page_title = 'Users';
$nav = 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Users</div>
<p class="page-sub"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?><?= $q ? ' matching "' . e($q) . '"' : '' ?></p>

<div class="card">
  <form method="get">
    <div class="search-wrap">
      <?= lucide('search') ?>
      <input class="field" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone…">
    </div>
    <div class="btn-row">
      <button class="btn btn-sm" type="submit" style="width:100%">Search</button>
      <?php if ($q): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/users.php') ?>" style="width:100%">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">Accounts</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($users as $u2): ?>
      <div class="list-item">
        <div class="li-icon <?= $u2['is_active'] ? 'gold' : 'money-out' ?>"><?= lucide('user-round') ?></div>
        <div class="li-body">
          <div class="li-title"><?= e($u2['name']) ?>
            <span class="badge <?= $u2['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $u2['is_active'] ? 'active' : 'disabled' ?></span></div>
          <div class="li-sub"><?= money($u2['balance']) ?> · <?= grams_fmt($u2['gold_g']) ?> g gold · <?= grams_fmt($u2['silver_g']) ?> g silver</div>
        </div>
        <div class="li-right">
          <a class="btn btn-sm" href="<?= url('admin/user-view.php?id=' . (int) $u2['id']) ?>">Open</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th class="num">Wallet</th>
          <th class="num">Gold g</th><th class="num">Silver g</th><th>Status</th><th>Joined</th></tr>
      <?php foreach ($users as $u2): ?>
      <tr>
        <td class="muted">#<?= (int) $u2['id'] ?></td>
        <td><a href="<?= url('admin/user-view.php?id=' . (int) $u2['id']) ?>"><?= e($u2['name']) ?></a></td>
        <td><?= e($u2['email']) ?></td>
        <td>+91 <?= e($u2['phone']) ?></td>
        <td class="num"><?= money($u2['balance']) ?></td>
        <td class="num"><?= grams_fmt($u2['gold_g']) ?></td>
        <td class="num"><?= grams_fmt($u2['silver_g']) ?></td>
        <td><span class="badge <?= $u2['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $u2['is_active'] ? 'active' : 'disabled' ?></span></td>
        <td class="muted"><?= e(date('d M Y', strtotime($u2['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
