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

$page_title = 'Dashboard';
$nav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
  <div class="stat"><div class="k">Users</div><div class="v"><?= number_format($usersCount) ?></div>
    <div class="s"><?= $newUsers ? 'latest: ' . e($newUsers[0]['name']) : '—' ?></div></div>
  <div class="stat"><div class="k">Wallet liability</div><div class="v"><?= money($walletLiab, 0) ?></div>
    <div class="s">money held on behalf of users</div></div>
  <div class="stat"><div class="k">Gold held</div><div class="v"><?= grams_fmt($goldHeld) ?> g</div>
    <div class="s">≈ <?= money($goldHeld * ($rates['gold']['sell'] ?? 0), 0) ?></div></div>
  <div class="stat"><div class="k">Silver held</div><div class="v"><?= grams_fmt($silvHeld) ?> g</div>
    <div class="s">≈ <?= money($silvHeld * ($rates['silver']['sell'] ?? 0), 0) ?></div></div>
  <div class="stat"><div class="k">Deposited (Razorpay)</div><div class="v"><?= money($deposited, 0) ?></div>
    <div class="s">total of paid orders</div></div>
  <div class="stat"><div class="k">Active SIPs</div><div class="v"><?= number_format($activeSips) ?></div>
    <div class="s">auto-invest plans running</div></div>
  <div class="stat"><div class="k">Pending withdrawals</div>
    <div class="v" style="color:<?= $pendingWd ? '#B45309' : 'inherit' ?>"><?= number_format($pendingWd) ?></div>
    <div class="s"><?= $pendingWd ? 'needs your action' : 'all clear' ?></div></div>
  <div class="stat"><div class="k">Current rates</div>
    <div class="v" style="font-size:15px"><?= lucide('gem', 'ic-14') ?> <?= money($rates['gold']['buy'] ?? 0, 0) ?><br><?= lucide('coins', 'ic-14') ?> <?= money($rates['silver']['buy'] ?? 0, 0) ?></div>
    <div class="s">per gram (buy)</div></div>
</div>

<?php if ($pendingWd): ?>
  <div class="notice"><?= lucide('circle-alert', 'ic-16') ?> <?= $pendingWd ?> withdrawal request<?= $pendingWd > 1 ? 's' : '' ?> waiting for approval —
    <a href="<?= url('admin/withdrawals.php') ?>" style="font-weight:900">review now</a></div>
<?php endif; ?>

<div class="admin-card">
  <h2>Recent activity</h2>
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
</div>

<div class="admin-card">
  <h2>Newest users</h2>
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
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
