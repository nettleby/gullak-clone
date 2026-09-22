<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_active') {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash_set('success', 'User status toggled.');
    } elseif ($action === 'reset_password') {
        $newPass = 'gullak-' . bin2hex(random_bytes(4));   // e.g. gullak-1a2b3c4d
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);
        flash_set('success', 'Temporary password for this user: ' . $newPass
            . ' — share it securely; it is not shown again.');
    } elseif ($action === 'adjust_wallet') {
        $delta = dec_to_scaled($_POST['delta'] ?? '', 2) / 100;        // exact 2-decimal ₹
        $note  = trim($_POST['note'] ?? '');
        if (abs($delta) <= 0 || abs($delta) > 1000000) {
            flash_set('error', 'Adjustment must be a non-zero amount up to ₹10,00,000.');
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
                $st->execute([$id]);
                if (!$st->fetch()) { $pdo->prepare('INSERT INTO wallets (user_id) VALUES (?)')->execute([$id]); }
                $st->execute([$id]);
                $newBal = round((float) $st->fetchColumn() + $delta, 2);
                if ($newBal < 0) throw new RuntimeException('Resulting balance cannot be negative.');
                $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $id]);
                ledger($pdo, [
                    'user_id' => $id, 'type' => $delta > 0 ? 'admin_credit' : 'admin_debit',
                    'amount' => abs($delta), 'wallet_delta' => $delta, 'wallet_after' => $newBal,
                    'note' => $note !== '' ? $note : 'Admin adjustment by ' . $admin['username'],
                ]);
                $pdo->commit();
                flash_set('success', 'Wallet adjusted by ' . ($delta > 0 ? '+' : '') . money($delta) . '.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', $ex->getMessage());
            }
        }
    }
    header('Location: ' . url('admin/user-view.php?id=' . $id));
    exit;
}

$st = $pdo->prepare('SELECT u.*, w.balance FROM users u JOIN wallets w ON w.user_id = u.id WHERE u.id = ?');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) { http_response_code(404); exit('User not found.'); }

$hold = get_holdings($id);
$rates = get_rates();
$gVal = $hold['gold']['grams'] * ($rates['gold']['sell'] ?? 0);
$sVal = $hold['silver']['grams'] * ($rates['silver']['sell'] ?? 0);

$st = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30');
$st->execute([$id]);
$txns = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM sip_plans WHERE user_id = ? ORDER BY id DESC');
$st->execute([$id]);
$sips = $st->fetchAll();

$st = $pdo->prepare('SELECT w.*, b.bank_name, b.account_number, b.ifsc
                     FROM withdrawals w JOIN bank_accounts b ON b.id = w.bank_account_id
                     WHERE w.user_id = ? ORDER BY w.id DESC LIMIT 10');
$st->execute([$id]);
$wds = $st->fetchAll();

$page_title = 'User #' . $id;
$nav = 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-card">
  <h2><?= e($u['name']) ?> <span class="muted small">#<?= (int) $u['id'] ?></span>
    <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $u['is_active'] ? 'active' : 'disabled' ?></span></h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><td class="muted">Email</td><td><?= e($u['email']) ?></td></tr>
      <tr><td class="muted">Phone</td><td>+91 <?= e($u['phone']) ?></td></tr>
      <tr><td class="muted">Joined</td><td><?= e(dt_ist($u['created_at'])) ?></td></tr>
      <tr><td class="muted">Wallet</td><td><?= money($u['balance']) ?></td></tr>
      <tr><td class="muted">Gold</td><td><?= grams_fmt($hold['gold']['grams']) ?> g ≈ <?= money($gVal) ?> (invested <?= money($hold['gold']['invested']) ?>)</td></tr>
      <tr><td class="muted">Silver</td><td><?= grams_fmt($hold['silver']['grams']) ?> g ≈ <?= money($sVal) ?> (invested <?= money($hold['silver']['invested']) ?>)</td></tr>
    </table>
  </div>

  <div class="mt14" style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="post"><?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="toggle_active">
      <button class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-green' ?> btn-sm" type="submit">
        <?= $u['is_active'] ? 'Disable user' : 'Enable user' ?>
      </button>
    </form>
    <form method="post" data-confirm="Generate a new random password for this user?"><?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <button class="btn btn-ghost btn-sm" type="submit">Reset password</button>
    </form>
  </div>
</div>

<div class="admin-card">
  <h2>Adjust wallet (manual credit / debit)</h2>
  <form method="post" class="inline-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="adjust_wallet">
    <input class="field" name="delta" type="number" step="0.01" placeholder="e.g. 500 or -250" style="width:170px">
    <input class="field" name="note" placeholder="Reason (optional)">
    <button class="btn btn-sm" type="submit">Apply</button>
  </form>
  <p class="muted small mt8">Use for support cases, refunds, promos etc. Everything is ledgered.</p>
</div>

<?php if ($sips): ?>
<div class="admin-card">
  <h2>SIP plans</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>Metal</th><th class="num">Amount</th><th>Frequency</th><th>Status</th><th>Next run</th><th>Fails</th></tr>
      <?php foreach ($sips as $p): ?>
      <tr>
        <td><?= e($p['metal']) ?></td>
        <td class="num"><?= money($p['amount_inr'], 0) ?></td>
        <td><?= e($p['frequency']) ?></td>
        <td><span class="badge <?= $p['status'] === 'active' ? 'badge-success' : ($p['status'] === 'paused' ? 'badge-warning' : 'badge-muted') ?>"><?= e($p['status']) ?></span></td>
        <td><?= e($p['next_run']) ?></td>
        <td><?= (int) $p['failed_attempts'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($wds): ?>
<div class="admin-card">
  <h2>Withdrawal requests</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th class="num">Amount</th><th>Bank</th><th>Status</th><th>Requested</th></tr>
      <?php foreach ($wds as $w): ?>
      <tr>
        <td class="num"><?= money($w['amount']) ?></td>
        <td><?= e($w['bank_name']) ?> ···<?= e(substr($w['account_number'], -4)) ?></td>
        <td><span class="badge <?= $w['status'] === 'pending' ? 'badge-warning' : ($w['status'] === 'approved' ? 'badge-success' : 'badge-danger') ?>"><?= e($w['status']) ?></span></td>
        <td class="muted"><?= e(dt_ist($w['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="admin-card">
  <h2>Last 30 transactions</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>Type</th><th class="num">Wallet Δ</th><th>Metal</th><th class="num">Grams Δ</th><th class="num">Rate</th><th>Note</th><th>When</th></tr>
      <?php foreach ($txns as $t): [$label] = txn_label($t['type']); ?>
      <tr>
        <td><?= e($label) ?></td>
        <td class="num"><?= (float) $t['wallet_delta'] > 0 ? '+' : ((float) $t['wallet_delta'] < 0 ? '−' : '') ?><?= money(abs((float) $t['wallet_delta'])) ?></td>
        <td><?= e($t['metal'] ?: '—') ?></td>
        <td class="num"><?= $t['grams_delta'] !== null ? ((float) $t['grams_delta'] > 0 ? '+' : '') . grams_fmt($t['grams_delta']) : '—' ?></td>
        <td class="num"><?= $t['rate'] !== null ? money($t['rate']) : '—' ?></td>
        <td class="muted"><?= e($t['note'] ?? '') ?></td>
        <td class="muted"><?= e(date('d M, H:i', strtotime($t['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
