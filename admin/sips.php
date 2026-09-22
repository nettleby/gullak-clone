<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

$stats = $pdo->query(
    'SELECT status, COUNT(*) n FROM sip_plans GROUP BY status'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->query('SELECT p.*, u.name FROM sip_plans p JOIN users u ON u.id = p.user_id
                   ORDER BY p.id DESC LIMIT 200');
$plans = $st->fetchAll();

$st = $pdo->query('SELECT l.*, u.name FROM sip_logs l JOIN users u ON u.id = l.user_id
                   ORDER BY l.id DESC LIMIT 25');
$logs = $st->fetchAll();

$page_title = 'SIPs';
$nav = 'sips';
require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
  <div class="stat"><div class="k">Active</div><div class="v" style="color:#15803D"><?= number_format((int) ($stats['active'] ?? 0)) ?></div></div>
  <div class="stat"><div class="k">Paused</div><div class="v" style="color:#B45309"><?= number_format((int) ($stats['paused'] ?? 0)) ?></div></div>
  <div class="stat"><div class="k">Cancelled</div><div class="v"><?= number_format((int) ($stats['cancelled'] ?? 0)) ?></div></div>
</div>

<div class="admin-card">
  <h2>All plans</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>#</th><th>User</th><th>Metal</th><th class="num">Instalment</th><th>Frequency</th>
          <th>Status</th><th>Next run</th><th>Fails</th><th>Created</th></tr>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td class="muted"><?= (int) $p['id'] ?></td>
        <td><a href="<?= url('admin/user-view.php?id=' . (int) $p['user_id']) ?>"><?= e($p['name']) ?></a></td>
        <td><?= lucide($p['metal'] === 'gold' ? 'gem' : 'coins', 'ic-16') ?> <?= e($p['metal']) ?></td>
        <td class="num"><?= money($p['amount_inr'], 0) ?></td>
        <td><?= e($p['frequency']) ?></td>
        <td><span class="badge <?= $p['status'] === 'active' ? 'badge-success' : ($p['status'] === 'paused' ? 'badge-warning' : 'badge-muted') ?>"><?= e($p['status']) ?></span></td>
        <td><?= e($p['next_run']) ?></td>
        <td><?= (int) $p['failed_attempts'] ?></td>
        <td class="muted"><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="admin-card">
  <h2>Recent runs (all users)</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>User</th><th>Date</th><th>Status</th><th class="num">Amount</th><th class="num">Grams</th><th>Reason</th></tr>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= e($l['name']) ?></td>
        <td class="muted"><?= e($l['run_date']) ?></td>
        <td><span class="badge <?= $l['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>"><?= e($l['status']) ?></span></td>
        <td class="num"><?= $l['amount'] !== null ? money($l['amount']) : '—' ?></td>
        <td class="num"><?= $l['grams'] !== null ? grams_fmt($l['grams']) : '—' ?></td>
        <td class="muted"><?= e($l['reason'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
