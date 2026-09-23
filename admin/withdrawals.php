<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $id   = (int) ($_POST['id'] ?? 0);
    $act  = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    $st = $pdo->prepare('SELECT w.* FROM withdrawals w WHERE w.id = ?');
    $st->execute([$id]);
    $w = $st->fetch();

    if (!$w || $w['status'] !== 'pending') {
        flash_set('error', 'Request not found or already processed.');
    } elseif ($act === 'approve' || $act === 'reject') {
        try {
            $pdo->beginTransaction();

            if ($act === 'approve') {
                $pdo->prepare('UPDATE withdrawals SET status = "approved", processed_at = NOW(),
                               processed_by = ?, admin_note = ? WHERE id = ? AND status = "pending"')
                    ->execute([$admin['username'], $note ?: null, $id]);
            } else {
                /* refund the money back to the wallet */
                $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
                $st->execute([$w['user_id']]);
                $newBal = round((float) $st->fetchColumn() + (float) $w['amount'], 2);
                $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $w['user_id']]);

                $pdo->prepare('UPDATE withdrawals SET status = "rejected", processed_at = NOW(),
                               processed_by = ?, admin_note = ? WHERE id = ? AND status = "pending"')
                    ->execute([$admin['username'], $note ?: null, $id]);

                ledger($pdo, [
                    'user_id' => (int) $w['user_id'], 'type' => 'withdraw_refund',
                    'amount' => (float) $w['amount'], 'wallet_delta' => (float) $w['amount'],
                    'wallet_after' => $newBal,
                    'note' => 'Withdrawal rejected' . ($note ? ' — ' . $note : ''),
                ]);
            }
            $pdo->commit();
            flash_set('success', 'Request #' . $id . ' ' . ($act === 'approve' ? 'approved — remember to make the actual bank transfer!' : 'rejected and refunded to wallet.'));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_set('error', 'Action failed: ' . $ex->getMessage());
        }
    }
    header('Location: ' . url('admin/withdrawals.php'));
    exit;
}

$st = $pdo->query('SELECT w.*, u.name, u.email, b.bank_name, b.holder_name, b.account_number, b.ifsc
                   FROM withdrawals w
                   JOIN users u ON u.id = w.user_id
                   JOIN bank_accounts b ON b.id = w.bank_account_id
                   ORDER BY FIELD(w.status,"pending","approved","rejected"), w.id DESC
                   LIMIT 200');
$reqs = $st->fetchAll();

$pending = array_values(array_filter($reqs, fn($r) => $r['status'] === 'pending'));
$done    = array_values(array_filter($reqs, fn($r) => $r['status'] !== 'pending'));
$view = admin_view();

$page_title = 'Withdrawals';
$nav = 'withdrawals';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Payouts</div>
<p class="page-sub"><?= count($pending) ?> waiting · <?= count($done) ?> processed</p>

<div class="card">
  <div class="card-title">Pending (<?= count($pending) ?>)</div>
  <?php if (!$pending): ?>
    <div class="empty">
      <div class="ico"><?= lucide('badge-check') ?></div>
      <h3>All caught up</h3>
      <p>No withdrawal requests waiting.</p>
    </div>
  <?php else: ?>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <?php foreach ($pending as $w): ?>
    <div class="plan">
      <div class="plan-top">
        <div class="li-icon money-out"><?= lucide('landmark') ?></div>
        <div style="flex:1;min-width:0">
          <div class="plan-amount"><?= money($w['amount']) ?></div>
          <div class="plan-meta"><a href="<?= url('admin/user-view.php?id=' . (int) $w['user_id']) ?>"><?= e($w['name']) ?></a> · <?= e(dt_ist($w['created_at'])) ?></div>
        </div>
        <span class="badge badge-warning">pending</span>
      </div>
      <div class="kv"><span class="k">Bank</span><span class="v"><?= e($w['bank_name']) ?></span></div>
      <div class="kv"><span class="k">Account</span><span class="v"><?= e($w['holder_name']) ?> ···<?= e(substr($w['account_number'], -4)) ?></span></div>
      <div class="kv"><span class="k">IFSC</span><span class="v mono"><?= e($w['ifsc']) ?></span></div>
      <form method="post" class="mt8">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
        <input class="field" name="note" placeholder="Note (optional)">
        <div class="btn-row mt8">
          <button class="btn btn-green btn-sm" name="action" value="approve" type="submit" style="width:100%">Approve</button>
          <button class="btn btn-danger btn-sm" name="action" value="reject" type="submit" style="width:100%"
                  data-confirm="Reject and refund <?= money($w['amount']) ?> to the user's wallet?">Reject</button>
        </div>
      </form>
    </div>
    <?php endforeach; ?>
    <p class="field-hint">Approving marks the request as paid out — transfer manually via your bank/UPI.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>#</th><th>User</th><th class="num">Amount</th><th>Bank account</th><th>Requested</th><th>Action</th></tr>
      <?php foreach ($pending as $w): ?>
      <tr>
        <td class="muted"><?= (int) $w['id'] ?></td>
        <td><a href="<?= url('admin/user-view.php?id=' . (int) $w['user_id']) ?>"><?= e($w['name']) ?></a>
          <div class="muted small"><?= e($w['email']) ?></div></td>
        <td class="num"><b><?= money($w['amount']) ?></b></td>
        <td><?= e($w['bank_name']) ?><div class="muted small"><?= e($w['holder_name']) ?> · <?= e($w['account_number']) ?> · <?= e($w['ifsc']) ?></div></td>
        <td class="muted"><?= e(dt_ist($w['created_at'])) ?></td>
        <td>
          <form method="post" class="inline-form" style="flex-direction:column;align-items:flex-start">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <input class="field" name="note" placeholder="Note (optional)" style="width:150px">
            <div style="display:flex;gap:6px;margin-top:6px">
              <button class="btn btn-green btn-sm" name="action" value="approve" type="submit">Approve</button>
              <button class="btn btn-danger btn-sm" name="action" value="reject" type="submit"
                      data-confirm="Reject and refund <?= money($w['amount']) ?> to the user's wallet?">Reject</button>
            </div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <p class="muted small mt8">Approving marks the request as paid out — this clone does not move real money;
     transfer manually via your bank/UPI.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Processed history</div>
  <?php if (!$done): ?>
    <p class="muted">No processed requests yet.</p>
  <?php else: ?>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($done as $w): ?>
      <div class="list-item">
        <div class="li-icon <?= $w['status'] === 'approved' ? 'money-in' : 'money-out' ?>"><?= lucide('landmark') ?></div>
        <div class="li-body">
          <div class="li-title"><?= money($w['amount']) ?> · <?= e($w['name']) ?>
            <span class="badge <?= $w['status'] === 'approved' ? 'badge-success' : 'badge-danger' ?>"><?= e($w['status']) ?></span></div>
          <div class="li-sub"><?= e($w['admin_note'] ?: '—') ?> · <?= e(dt_ist($w['processed_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>#</th><th>User</th><th class="num">Amount</th><th>Status</th><th>Note</th><th>Processed</th><th>By</th></tr>
      <?php foreach ($done as $w): ?>
      <tr>
        <td class="muted"><?= (int) $w['id'] ?></td>
        <td><?= e($w['name']) ?></td>
        <td class="num"><?= money($w['amount']) ?></td>
        <td><span class="badge <?= $w['status'] === 'approved' ? 'badge-success' : 'badge-danger' ?>"><?= e($w['status']) ?></span></td>
        <td class="muted"><?= e($w['admin_note'] ?: '—') ?></td>
        <td class="muted"><?= e(dt_ist($w['processed_at'])) ?></td>
        <td class="muted"><?= e($w['processed_by'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
