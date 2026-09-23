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
$view = admin_view();

$page_title = 'SIPs';
$nav = 'sips';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">SIPs</div>
<p class="page-sub">Auto-invest plans across users</p>

<div class="mini-grid cols-2">
  <div class="mini">
    <div class="mk"><?= lucide('repeat') ?> Active</div>
    <div class="mv" style="color:var(--green)"><?= number_format((int) ($stats['active'] ?? 0)) ?></div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('pause') ?> Paused</div>
    <div class="mv" style="color:var(--gold-dark)"><?= number_format((int) ($stats['paused'] ?? 0)) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title">All plans (<?= count($plans) ?>)</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($plans as $p): ?>
      <div class="list-item">
        <div class="li-icon <?= $p['metal'] === 'gold' ? 'gold' : 'silver' ?>"><?= lucide($p['metal'] === 'gold' ? 'gem' : 'coins') ?></div>
        <div class="li-body">
          <div class="li-title"><?= money($p['amount_inr'], 0) ?> · <?= e($p['frequency']) ?> · <?= e($p['name']) ?>
            <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : ($p['status'] === 'paused' ? 'badge-warning' : 'badge-muted') ?>"><?= e($p['status']) ?></span></div>
          <div class="li-sub">next <?= e($p['next_run']) ?> · fails <?= (int) $p['failed_attempts'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
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
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Recent runs</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($logs as $l): ?>
      <div class="list-item">
        <div class="li-icon <?= $l['status'] === 'success' ? 'money-in' : 'money-out' ?>"><?= lucide('repeat') ?></div>
        <div class="li-body">
          <div class="li-title"><?= e($l['name']) ?> · <?= $l['amount'] !== null ? money($l['amount']) : '—' ?>
            <span class="badge <?= $l['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>"><?= e($l['status']) ?></span></div>
          <div class="li-sub"><?= e($l['run_date']) ?><?= $l['grams'] !== null ? ' · ' . grams_fmt($l['grams']) . ' g' : '' ?><?= $l['reason'] ? ' · ' . e($l['reason']) : '' ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
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
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
