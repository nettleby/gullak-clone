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
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';
        if ($newPass === '' || $confPass === '') {
            flash_set('error', 'Please enter the new password twice.');
        } elseif (strlen($newPass) < 6) {
            flash_set('error', 'New password must be at least 6 characters.');
        } elseif ($newPass !== $confPass) {
            flash_set('error', 'Passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);
            flash_set('success', 'Password updated — share it with the user securely.');
        }
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
$view = admin_view();

$page_title = 'User #' . $id;
$nav = 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="profile-head">
  <div class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></div>
  <div class="p-name"><?= e($u['name']) ?></div>
  <div class="p-sub"><?= e($u['email']) ?> · +91 <?= e($u['phone']) ?></div>
  <div><span class="p-badge"><?= lucide($u['is_active'] ? 'badge-check' : 'octagon-x', 'ic-14') ?> <?= $u['is_active'] ? 'Active' : 'Disabled' ?></span>
  <span class="p-badge"><?= lucide('wallet', 'ic-14') ?> <?= money($u['balance']) ?></span></div>
</div>

<div class="card">
  <div class="card-title">Holdings</div>
  <div class="kv"><span class="k"><?= lucide('gem', 'ic-14') ?> Gold</span><span class="v"><?= grams_fmt($hold['gold']['grams']) ?> g ≈ <?= money($gVal) ?></span></div>
  <div class="kv"><span class="k"><?= lucide('coins', 'ic-14') ?> Silver</span><span class="v"><?= grams_fmt($hold['silver']['grams']) ?> g ≈ <?= money($sVal) ?></span></div>
  <div class="kv"><span class="k">Joined</span><span class="v"><?= e(dt_ist($u['created_at'])) ?></span></div>
  <div class="btn-row mt14">
    <form method="post" style="width:100%"><?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="toggle_active">
      <button class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-green' ?> btn-sm" type="submit" style="width:100%">
        <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-title">Set new password</div>
  <form method="post" data-confirm="Set a new password for this user?">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="reset_password">
    <label class="field-label" for="pw-new">New password (min 6 chars)</label>
    <div class="pw-wrap">
      <input class="field" type="password" id="pw-new" name="new_password" required minlength="6"
             placeholder="••••••••" autocomplete="new-password">
      <button class="pw-eye" type="button" data-toggle-pw="pw-new" aria-label="Show password"><?= lucide('eye') ?></button>
    </div>
    <label class="field-label" for="pw-conf">Confirm new password</label>
    <div class="pw-wrap">
      <input class="field" type="password" id="pw-conf" name="confirm_password" required
             placeholder="Repeat password" autocomplete="new-password">
      <button class="pw-eye" type="button" data-toggle-pw="pw-conf" aria-label="Show password"><?= lucide('eye') ?></button>
    </div>
    <div class="mt14"><button class="btn btn-sm" type="submit" style="width:100%">Set password</button></div>
  </form>
</div>

<div class="card">
  <div class="card-title">Adjust wallet</div>
  <form method="post" class="stack-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="adjust_wallet">
    <input class="field" name="delta" type="number" step="0.01" placeholder="e.g. 500 or -250">
    <input class="field" name="note" placeholder="Reason (optional)">
    <button class="btn btn-sm" type="submit" style="width:100%">Apply adjustment</button>
  </form>
  <p class="field-hint">Support cases, refunds, promos — everything is ledgered.</p>
</div>

<?php if ($sips): ?>
<div class="card">
  <div class="card-title">SIP plans</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($sips as $p): ?>
      <div class="list-item">
        <div class="li-icon <?= $p['metal'] === 'gold' ? 'gold' : 'silver' ?>"><?= lucide('repeat') ?></div>
        <div class="li-body">
          <div class="li-title"><?= money($p['amount_inr'], 0) ?> · <?= e($p['frequency']) ?>
            <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : ($p['status'] === 'paused' ? 'badge-warning' : 'badge-muted') ?>"><?= e($p['status']) ?></span></div>
          <div class="li-sub">next <?= e($p['next_run']) ?> · fails <?= (int) $p['failed_attempts'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
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
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($wds): ?>
<div class="card">
  <div class="card-title">Withdrawals</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($wds as $w): ?>
      <div class="list-item">
        <div class="li-icon money-out"><?= lucide('landmark') ?></div>
        <div class="li-body">
          <div class="li-title"><?= money($w['amount']) ?>
            <span class="badge <?= $w['status'] === 'pending' ? 'badge-warning' : ($w['status'] === 'approved' ? 'badge-success' : 'badge-danger') ?>"><?= e($w['status']) ?></span></div>
          <div class="li-sub"><?= e($w['bank_name']) ?> ···<?= e(substr($w['account_number'], -4)) ?> · <?= e(dt_ist($w['created_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
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
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Last 30 transactions</div>
  <?= admin_view_toggle() ?>
  <?php if ($view === 'card'): ?>
    <div class="list">
      <?php foreach ($txns as $t): [$label, $dir, $sign] = txn_label($t['type']); $wd = (float) $t['wallet_delta']; ?>
      <div class="list-item">
        <div class="li-icon <?= $dir === 'in' ? 'money-in' : ($dir === 'out' ? 'money-out' : 'gold') ?>">
          <?= lucide($dir === 'in' ? 'arrow-down-left' : ($dir === 'out' ? 'arrow-up-right' : 'receipt')) ?>
        </div>
        <div class="li-body">
          <div class="li-title"><?= e($label) ?></div>
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
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
