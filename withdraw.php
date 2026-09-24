<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$pdo = db();
$uid = $user ? (int) $user['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();   // withdrawals move money — guests log in first (returns here after)
    $uid = (int) $user['id'];
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_account') {
        $holder = trim($_POST['holder_name'] ?? '');
        $accno  = preg_replace('/\s+/', '', $_POST['account_number'] ?? '');
        $ifsc   = strtoupper(trim($_POST['ifsc'] ?? ''));
        $bank   = trim($_POST['bank_name'] ?? '');

        if (mb_strlen($holder) < 2 || !preg_match('/^\d{9,18}$/', $accno)
            || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc) || mb_strlen($bank) < 2) {
            flash_set('error', 'Please check the account details (account no. 9-18 digits, valid IFSC like HDFC0001234).');
        } else {
            $pdo->prepare('INSERT INTO bank_accounts (user_id, holder_name, account_number, ifsc, bank_name)
                           VALUES (?,?,?,?,?)')
                ->execute([$uid, $holder, $accno, $ifsc, $bank]);
            flash_set('success', 'Bank account saved.');
        }

    } elseif ($action === 'request') {
        $bankId = (int) ($_POST['bank_account_id'] ?? 0);
        $amount = dec_to_scaled($_POST['amount'] ?? '', 2) / 100;    // exact 2-decimal ₹

        $st = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND user_id = ?');
        $st->execute([$bankId, $uid]);
        $ok = $st->fetch();

        if (!$ok) {
            flash_set('error', 'Please select a bank account.');
        } elseif ($amount < MIN_WITHDRAW) {
            flash_set('error', 'Minimum withdrawal is ' . money(MIN_WITHDRAW, 0) . '.');
        } else {
            /* deduct immediately (locked) — refunded if admin rejects */
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
                $st->execute([$uid]);
                $balance = (float) $st->fetchColumn();
                if ($balance + 0.001 < $amount) throw new RuntimeException('Insufficient wallet balance.');

                $newBal = round($balance - $amount, 2);
                $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $uid]);
                $pdo->prepare('INSERT INTO withdrawals (user_id, bank_account_id, amount) VALUES (?,?,?)')
                    ->execute([$uid, $bankId, $amount]);
                ledger($pdo, [
                    'user_id' => $uid, 'type' => 'withdraw_request', 'amount' => $amount,
                    'wallet_delta' => -$amount, 'wallet_after' => $newBal,
                    'note' => 'Withdrawal to bank — pending admin approval',
                ]);
                $pdo->commit();
                flash_set('success', 'Withdrawal requested for ' . money($amount)
                    . '. It usually settles in 1-2 working days after approval.');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', $ex->getMessage());
            }
        }
    }
    header('Location: ' . url('withdraw.php'));
    exit;
}

$balance = wallet_balance($uid);

$st = $pdo->prepare('SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY id DESC');
$st->execute([$uid]);
$banks = $st->fetchAll();

$st = $pdo->prepare('SELECT w.*, b.bank_name, b.account_number, b.ifsc
                      FROM withdrawals w JOIN bank_accounts b ON b.id = w.bank_account_id
                     WHERE w.user_id = ? ORDER BY w.id DESC LIMIT 10');
$st->execute([$uid]);
$reqs = $st->fetchAll();

$active_tab = 'wallet';
$page_title  = 'Withdraw';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Withdraw to bank</div>
<p class="page-sub">Requests are approved by the platform and paid out manually.</p>

<!-- how it works -->
<div class="card">
  <div class="card-title">How it works</div>
  <div class="timeline">
    <div class="tl-step">
      <div class="tl-rail"><span class="tl-dot done"></span><span class="tl-line"></span></div>
      <div class="tl-body"><div class="tl-title">You request</div><div class="tl-sub">Amount is locked from your wallet instantly</div></div>
    </div>
    <div class="tl-step">
      <div class="tl-rail"><span class="tl-dot"></span><span class="tl-line"></span></div>
      <div class="tl-body"><div class="tl-title">We review</div><div class="tl-sub">Usually approved within a few hours</div></div>
    </div>
    <div class="tl-step">
      <div class="tl-rail"><span class="tl-dot"></span></div>
      <div class="tl-body"><div class="tl-title">Money arrives</div><div class="tl-sub">1-2 working days to your bank · rejections are refunded in full</div></div>
    </div>
  </div>
  <div class="kv" style="margin-top:6px"><span class="k">Wallet balance</span><span class="v"><?= $user ? money($balance) : '—' ?></span></div>
  <div class="kv"><span class="k">Minimum withdrawal</span><span class="v"><?= money(MIN_WITHDRAW, 0) ?></span></div>
</div>

<?php if (!$user): ?>
<?= guest_cta('Log in to withdraw', 'Save bank accounts and request payouts once you are logged in.') ?>
<?php endif; ?>

<?php if ($banks): ?>
<div class="card">
  <div class="card-title">Request a withdrawal</div>
  <form method="post" data-confirm="Request this withdrawal? The amount locks from your wallet until it settles.">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="request">

    <label class="field-label" for="bank_account_id">Deposit to</label>
    <select class="field" id="bank_account_id" name="bank_account_id" required>
      <?php foreach ($banks as $b): ?>
        <option value="<?= (int) $b['id'] ?>">
          <?= e($b['bank_name']) ?> ···<?= e(substr($b['account_number'], -4)) ?> (<?= e($b['ifsc']) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <label class="field-label" for="amount">Amount (₹)</label>
    <div class="input-wrap">
      <span class="prefix">₹</span>
      <input class="field" id="amount" name="amount" type="number" min="<?= MIN_WITHDRAW ?>"
             step="10" max="<?= e($balance) ?>" inputmode="decimal" required>
    </div>

    <div class="mt14">
      <button class="btn" type="submit"><?= lucide('landmark') ?> Withdraw</button>
    </div>
  </form>
  <p class="field-hint mt8">Manage saved accounts in <a href="<?= url('settings.php#bank') ?>" style="color:var(--primary);font-weight:800">Settings → Bank accounts</a>.</p>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= $banks ? 'Add another bank account' : 'Add your bank account first' ?></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_account">

      <label class="field-label" for="holder_name">Account holder</label>
      <input class="field" id="holder_name" name="holder_name" required placeholder="<?= $user ? e($user['name']) : 'Full name as per bank' ?>">

    <label class="field-label" for="account_number">Account number</label>
    <input class="field" id="account_number" name="account_number" required inputmode="numeric"
           placeholder="9 to 18 digits">

    <label class="field-label" for="ifsc">IFSC code</label>
    <input class="field" id="ifsc" name="ifsc" required placeholder="HDFC0001234" maxlength="11"
           style="text-transform:uppercase">

    <label class="field-label" for="bank_name">Bank name</label>
    <input class="field" id="bank_name" name="bank_name" required placeholder="HDFC Bank">

    <div class="mt14">
      <button class="btn btn-ghost" type="submit"><?= lucide('landmark') ?> Save account</button>
    </div>
  </form>
</div>

<?php if ($reqs): ?>
<div class="card">
  <div class="card-title">Withdrawal requests</div>
  <div class="list">
    <?php foreach ($reqs as $r): ?>
      <div class="list-item">
        <div class="li-icon money-out"><?= lucide('landmark') ?></div>
        <div class="li-body">
          <div class="li-title"><?= e($r['bank_name']) ?> ···<?= e(substr($r['account_number'], -4)) ?></div>
          <div class="li-sub"><?= e(dt_ist($r['created_at'])) ?>
            <?php if ($r['admin_note']): ?> · <?= e($r['admin_note']) ?><?php endif; ?>
          </div>
        </div>
        <div class="li-right">
          <div class="li-amt out">−<?= money($r['amount']) ?></div>
          <span class="badge <?= $r['status'] === 'pending' ? 'badge-warning' : ($r['status'] === 'approved' ? 'badge-success' : 'badge-danger') ?>">
            <?= e($r['status']) ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
