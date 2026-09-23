<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

$one = fn(string $sql) => (float) $pdo->query($sql)->fetchColumn();

$usersCount  = (int) $one('SELECT COUNT(*) FROM users');
$walletLiab  = $one('SELECT COALESCE(SUM(balance),0) FROM wallets');
$goldHeld    = $one('SELECT COALESCE(SUM(grams),0) FROM holdings WHERE metal="gold"');
$silvHeld    = $one('SELECT COALESCE(SUM(grams),0) FROM holdings WHERE metal="silver"');
$deposited   = $one('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status="paid"');
$activeSips  = (int) $one('SELECT COUNT(*) FROM sip_plans WHERE status="active"');
$pendingWd   = (int) $one('SELECT COUNT(*) FROM withdrawals WHERE status="pending"');

$st = $pdo->query('SELECT id, name, email, phone, created_at FROM users ORDER BY id DESC LIMIT 8');
$newUsers = $st->fetchAll();

$st = $pdo->query('SELECT t.*, u.name FROM transactions t JOIN users u ON u.id = t.user_id
                   ORDER BY t.id DESC LIMIT 10');
$recent = $st->fetchAll();

$rates = get_rates();
$view = admin_view();

$page_title = 'Dashboard';
$nav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Dashboard</div>
<p class="page-sub">Platform at a glance</p>

<div class="stat-grid">
  <div class="stat"><div class="k">Users</div><div class="v"><?= number_format($usersCount) ?></div>
    <div class="s"><?= $newUsers ? 'latest: ' . e($newUsers[0]['name']) : '—' ?></div></div>
  <div class="stat"><div class="k">Wallet liability</div><div class="v"><?= money($walletLiab, 0) ?></div>
    <div class="s">held for users</div></div>
  <div class="stat"><div class="k">Gold held</div><div class="v"><?= grams_fmt($goldHeld) ?> g</div>
    <div class="s">≈ <?= money($goldHeld * ($rates['gold']['sell'] ?? 0), 0) ?></div></div>
  <div class="stat"><div class="k">Silver held</div><div class="v"><?= grams_fmt($silvHeld) ?> g</div>
    <div class="s">≈ <?= money($silvHeld * ($rates['silver']['sell'] ?? 0), 0) ?></div></div>
  <div class="stat"><div class="k">Deposited</div><div class="v"><?= money($deposited, 0) ?></div>
    <div class="s">paid orders</div></div>
  <div class="stat"><div class="k">Active SIPs</div><div class="v"><?= number_format($activeSips) ?></div>
    <div class="s">running plans</div></div>
  <div class="stat"><div class="k">Payouts waiting</div>
    <div class="v" style="color:<?= $pendingWd ? '#B45309' : 'inherit' ?>"><?= number_format($pendingWd) ?></div>
    <div class="s"><?= $pendingWd ? 'needs action' : 'all clear' ?></div></div>
  <div class="stat"><div class="k">Rates /g</div>
    <div class="v" style="font-size:15px"><?= lucide('gem', 'ic-14') ?> <?= money($rates['gold']['buy'] ?? 0, 0) ?><br><?= lucide('coins', 'ic-14') ?> <?= money($rates['silver']['buy'] ?? 0, 0) ?></div>
    <div class="s">buy rates</div></div>
</div>

<?php if ($pendingWd): ?>
  <a class="sip-banner" href="<?= url('admin/withdrawals.php') ?>">
    <span class="sb-icon"><?= lucide('circle-alert') ?></span>
    <span class="sb-body"><span class="sb-title"><?= $pendingWd ?> withdrawal<?= $pendingWd > 1 ? 's' : '' ?> waiting</span>
    <span class="sb-sub">Tap to review and pay out</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
<?php endif; ?>

<div class="card">
  <div class="card-title">Recent activity</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($recent as $t): [$label, $dir, $sign] = txn_label($t['type']); $wd = (float) $t['wallet_delta']; ?>
      <div class="list-item">
        <div class="li-icon <?= $dir === 'in' ? 'money-in' : ($dir === 'out' ? 'money-out' : 'gold') ?>">
          <?= lucide($dir === 'in' ? 'arrow-down-left' : ($dir === 'out' ? 'arrow-up-right' : 'receipt')) ?>
        </div>
        <div class="li-body">
          <div class="li-title"><?= e($label) ?> · <?= e($t['name']) ?></div>
          <div class="li-sub"><?= e($t['metal'] ?: '—') ?><?= $t['grams_delta'] !== null ? ' · ' . grams_fmt($t['grams_delta']) . ' g' : '' ?> · <?= e(date('d M, H:i', strtotime($t['created_at']))) ?></div>
        </div>
        <div class="li-right">
          <div class="li-amt <?= $dir ?>"><?= $wd > 0 ? '+' : ($wd < 0 ? '−' : '') ?><?= money(abs($wd)) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>User</th><th>Type</th><th class="num">Wallet Δ</th><th>Metal</th><th class="num">Grams</th><th>When</th></tr>
      <?php foreach ($recent as $t): [$label] = txn_label($t['type']); ?>
      <tr>
        <td><a href="<?= url('admin/user-view.php?id=' . (int) $t['user_id']) ?>"><?= e($t['name']) ?></a></td>
        <td><?= e($label) ?></td>
        <td class="num"><?= (float) $t['wallet_delta'] > 0 ? '+' : ((float) $t['wallet_delta'] < 0 ? '−' : '') ?><?= money(abs((float) $t['wallet_delta'])) ?></td>
        <td><?= e($t['metal'] ?: '—') ?></td>
        <td class="num"><?= $t['grams_delta'] !== null ? grams_fmt($t['grams_delta']) : '—' ?></td>
        <td class="muted"><?= e(date('d M, H:i', strtotime($t['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Newest users</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($newUsers as $nu): ?>
      <div class="list-item">
        <div class="li-icon gold"><?= lucide('user-round') ?></div>
        <div class="li-body">
          <div class="li-title"><?= e($nu['name']) ?></div>
          <div class="li-sub"><?= e($nu['email']) ?> · <?= e(date('d M Y', strtotime($nu['created_at']))) ?></div>
        </div>
        <div class="li-right">
          <a class="btn btn-sm" href="<?= url('admin/user-view.php?id=' . (int) $nu['id']) ?>">Open</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th></tr>
      <?php foreach ($newUsers as $nu): ?>
      <tr>
        <td class="muted">#<?= (int) $nu['id'] ?></td>
        <td><a href="<?= url('admin/user-view.php?id=' . (int) $nu['id']) ?>"><?= e($nu['name']) ?></a></td>
        <td><?= e($nu['email']) ?></td>
        <td>+91 <?= e($nu['phone']) ?></td>
        <td class="muted"><?= e(date('d M Y', strtotime($nu['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
